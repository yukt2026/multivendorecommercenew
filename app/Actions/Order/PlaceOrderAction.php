<?php

declare(strict_types=1);

namespace App\Actions\Order;

use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\OrderPlaced;
use App\Events\OrderPlacedEvent;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\FulfilmentOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Services\Commission\CommissionService;
use App\Services\Fulfilment\FulfilmentService;
use App\Services\Inventory\InventoryService;
use App\Services\Storefront\CartService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PlaceOrderAction
{
    public function __construct(
        protected FulfilmentService $fulfilmentService,
        protected InventoryService $inventoryService,
        protected CommissionService $commissionService,
        protected CartService $cartService
    ) {}

    /**
     * Execute atomic order placement with automated package splitting and inventory reservation.
     *
     * @param Customer $customer
     * @param CustomerAddress $address
     * @param Cart $cart
     * @param string $paymentMethod 'online' | 'cod'
     * @param string|null $idempotencyKey
     * @param string|null $notes
     * @return Order
     *
     * @throws InvalidArgumentException
     */
    public function execute(
        Customer $customer,
        CustomerAddress $address,
        Cart $cart,
        string $paymentMethod = 'online',
        ?string $idempotencyKey = null,
        ?string $notes = null
    ): Order {
        // 1. Idempotency Check: Prevent duplicate double-submits
        if ($idempotencyKey) {
            $existing = Order::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($customer, $address, $cart, $paymentMethod, $idempotencyKey, $notes) {
            $activeItems = $cart->activeItems()->with(['variant.product'])->get();
            if ($activeItems->isEmpty()) {
                throw new InvalidArgumentException('Cannot place an order with an empty cart.');
            }

            // 2. Resolve Fulfilment Plan
            $customerLocation = [
                'lat' => $address->lat,
                'lng' => $address->lng,
                'city' => $address->city,
                'state' => $address->state,
                'pincode' => $address->pincode,
            ];

            $plan = $this->fulfilmentService->createPlan($cart, $customerLocation);

            if ($plan->hasUnfulfillable()) {
                $unfulfilledItem = $plan->unfulfillableItems[0]['product_title'] ?? 'One of the items';
                throw new InvalidArgumentException("Unable to fulfill '{$unfulfilledItem}' at this delivery location or store is closed.");
            }

            if (empty($plan->groups)) {
                throw new InvalidArgumentException('No eligible fulfilment groups available for this cart.');
            }

            // 3. Compute Financials
            $summary = $this->cartService->getSummary($cart, $customer->user);
            $subtotal = (float) $summary['subtotal'];
            $discount = (float) $summary['coupon_discount'];
            $deliveryFee = (float) $plan->totalDeliveryFee;
            $tax = (float) $summary['estimated_tax'];
            $total = max(0.0, round($subtotal - $discount + $deliveryFee, 2));

            // Validate COD payment method
            $normalizedPaymentMethod = strtolower($paymentMethod) === 'cod' ? PaymentMethod::COD : PaymentMethod::UPI;
            if ($normalizedPaymentMethod === PaymentMethod::COD) {
                $maxCod = (float) setting('max_cod_amount', 5000.00);
                if ($total > $maxCod) {
                    throw new InvalidArgumentException("Order total of " . format_currency($total) . " exceeds the maximum allowed COD limit of " . format_currency($maxCod) . ".");
                }
                foreach ($activeItems as $item) {
                    if (!$item->variant?->product?->is_cod_allowed) {
                        throw new InvalidArgumentException("Cash on Delivery is not allowed for item '{$item->variant?->product?->title}'.");
                    }
                }
            }

            // 4. Generate Order Number
            $prefix = config('onestall.order_number_prefix', 'OS-');
            $datePart = date('Ymd');
            $orderNumber = sprintf('%s%s-%s', $prefix, $datePart, strtoupper(Str::random(6)));

            // 5. Create Parent Order
            $order = Order::create([
                'order_number' => $orderNumber,
                'customer_id' => $customer->id,
                'address_snapshot' => $address->toArray(),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'delivery_fee' => $deliveryFee,
                'tax' => $tax,
                'total' => $total,
                'payment_method' => $normalizedPaymentMethod,
                'payment_status' => $normalizedPaymentMethod === PaymentMethod::COD ? PaymentStatus::COD_PENDING : PaymentStatus::PENDING,
                'status' => OrderStatus::PLACED,
                'notes' => $notes,
                'idempotency_key' => $idempotencyKey,
            ]);

            // Initial Payment Record
            \App\Models\Payment::create([
                'order_id' => $order->id,
                'gateway' => $normalizedPaymentMethod === PaymentMethod::COD ? 'cod' : (string) setting('payment_gateway_default', config('onestall.payment_gateway', 'razorpay')),
                'method' => $normalizedPaymentMethod,
                'amount' => $total,
                'currency' => 'INR',
                'status' => $normalizedPaymentMethod === PaymentMethod::COD ? PaymentStatus::COD_PENDING : PaymentStatus::PENDING,
            ]);

            // 6. Create Fulfilment Orders (Order Splitting)
            foreach ($plan->groups as $index => $group) {
                $foNumber = sprintf('%s-FO%d', $order->order_number, $index + 1);

                $fulfilmentOrder = FulfilmentOrder::create([
                    'order_id' => $order->id,
                    'fulfilment_order_number' => $foNumber,
                    'seller_id' => $group->sellerId,
                    'store_id' => $group->storeId,
                    'inventory_location_id' => $group->inventoryLocationId,
                    'fulfilment_type' => $group->fulfilmentType,
                    'delivery_otp' => $group->fulfilmentType === FulfilmentType::QUICK ? sprintf('%06d', mt_rand(100000, 999999)) : null,
                    'status' => OrderStatus::PLACED,
                    'subtotal' => $group->subtotal,
                    'commission_amount' => 0.00,
                    'seller_payable' => $group->subtotal,
                    'delivery_fee' => $group->deliveryFee,
                    'estimated_delivery_at' => $group->estimatedDeliveryAt,
                ]);

                // Create Order Items snapshot & Reserve stock
                foreach ($group->items as $itemData) {
                    OrderItem::create([
                        'fulfilment_order_id' => $fulfilmentOrder->id,
                        'product_id' => $itemData['product_id'],
                        'variant_id' => $itemData['variant_id'],
                        'title' => $itemData['title'],
                        'variant_name' => $itemData['variant_name'],
                        'sku' => $itemData['sku'],
                        'qty' => $itemData['quantity'],
                        'price' => $itemData['price'],
                        'tax' => 0.00,
                        'discount' => 0.00,
                        'total' => $itemData['subtotal'],
                    ]);

                    // Atomic Stock Reservation
                    if (!empty($itemData['inventory_id']) && $itemData['inventory_id'] > 0) {
                        try {
                            $this->inventoryService->reserve(
                                $itemData['inventory_id'],
                                (int) $itemData['quantity'],
                                $order->order_number,
                                null
                            );
                        } catch (\Throwable $e) {
                            report($e);
                        }
                    }
                }

                // Compute Commission Entry for this Fulfilment Order
                $this->commissionService->recordForFulfilmentOrder($fulfilmentOrder);

                // Write Fulfilment Order Status History
                OrderStatusHistory::create([
                    'orderable_type' => FulfilmentOrder::class,
                    'orderable_id' => $fulfilmentOrder->id,
                    'from_status' => null,
                    'to_status' => OrderStatus::PLACED->value,
                    'note' => "Package #{$foNumber} routed via {$group->sourceType} ({$group->deliveryBadge})",
                    'changed_by' => Auth::id(),
                ]);
            }

            // 7. Write Parent Order Status History
            OrderStatusHistory::create([
                'orderable_type' => Order::class,
                'orderable_id' => $order->id,
                'from_status' => null,
                'to_status' => OrderStatus::PLACED->value,
                'note' => 'Order placed successfully by customer',
                'changed_by' => Auth::id(),
            ]);

            // 8. Record Coupon Usage
            if ($cart->coupon_code && $discount > 0) {
                $coupon = Coupon::where('code', $cart->coupon_code)->first();
                if ($coupon) {
                    CouponUsage::create([
                        'coupon_id' => $coupon->id,
                        'user_id' => $customer->user_id,
                        'order_id' => $order->id,
                        'discount_amount' => $discount,
                    ]);
                }
            }

            // 9. Clear Active Cart Items
            $cart->activeItems()->delete();
            $cart->coupon_code = null;
            $cart->save();

            // 10. Dispatch OrderPlaced Events
            event(new OrderPlaced($order));
            event(new OrderPlacedEvent($order->id, (float) $order->total));

            // 11. AI Fraud & Risk Scoring Assessment
            try {
                app(\App\Services\AI\Contracts\OrderFraudScorerInterface::class)->assessOrder($order, [
                    'client_ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("AI fraud scoring failed for order #{$order->id}: {$e->getMessage()}");
            }

            return $order->fresh(['fulfilmentOrders.items', 'fulfilmentOrders.seller', 'customer']);
        });
    }
}
