<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Actions\Order\PlaceOrderAction;
use App\Enums\AddressType;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Services\Fulfilment\FulfilmentService;
use App\Services\Storefront\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected FulfilmentService $fulfilmentService,
        protected PlaceOrderAction $placeOrderAction
    ) {}

    /**
     * Single-Page Checkout Screen (/checkout).
     */
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->guest(route('login'))->with('notice', 'Please sign in to proceed with checkout.');
        }

        $customer = $user->customer ?: Customer::firstOrCreate(['user_id' => $user->id]);
        $sessionId = $request->session()->getId();
        $cart = $this->cartService->getActiveCart($user, $sessionId);

        // Ensure cart has active items
        if ($cart->activeItems->isEmpty()) {
            return redirect()->route('cart.index')->with('notice', 'Your cart is empty. Add items before checking out.');
        }

        // Addresses
        $addresses = $customer->addresses()->latest('is_default')->latest('id')->get();
        $selectedAddressId = (int) $request->query('address_id', $addresses->firstWhere('is_default', true)?->id ?? $addresses->first()?->id ?? 0);
        $selectedAddress = $addresses->firstWhere('id', $selectedAddressId) ?? $addresses->first();

        // Fulfilment Plan
        $location = $selectedAddress ? [
            'lat' => $selectedAddress->lat,
            'lng' => $selectedAddress->lng,
            'city' => $selectedAddress->city,
            'state' => $selectedAddress->state,
            'pincode' => $selectedAddress->pincode,
        ] : [
            'lat' => (float) setting('default_lat', 12.9716),
            'lng' => (float) setting('default_lng', 77.5946),
            'city' => 'Bengaluru',
            'pincode' => '560001',
        ];

        $fulfilmentPlan = $this->fulfilmentService->createPlan($cart, $location);
        $summary = $this->cartService->getSummary($cart, $user);

        // Adjust summary delivery fee with plan's computed delivery fee
        $summary['delivery_fee'] = $fulfilmentPlan->totalDeliveryFee;
        $summary['formatted_delivery_fee'] = $fulfilmentPlan->totalDeliveryFee == 0 ? 'FREE' : format_currency($fulfilmentPlan->totalDeliveryFee);
        $summary['total_payable'] = max(0.0, round($summary['subtotal'] - $summary['coupon_discount'] + $fulfilmentPlan->totalDeliveryFee, 2));
        $summary['formatted_total_payable'] = format_currency($summary['total_payable']);

        // Check COD Eligibility
        $codEligible = true;
        $codReason = null;
        $maxCodAmount = (float) setting('max_cod_amount', 5000.00);

        if (!(bool) setting('cod_enabled', true)) {
            $codEligible = false;
            $codReason = 'Cash on Delivery is currently disabled by platform.';
        } elseif ($summary['total_payable'] > $maxCodAmount) {
            $codEligible = false;
            $codReason = 'Order total of ' . format_currency($summary['total_payable']) . ' exceeds the maximum COD limit of ' . format_currency($maxCodAmount) . '.';
        } else {
            foreach ($cart->activeItems as $item) {
                if (!$item->variant?->product?->is_cod_allowed) {
                    $codEligible = false;
                    $codReason = "Item '{$item->variant?->product?->title}' is not eligible for Cash on Delivery.";
                    break;
                }
            }
        }

        return view('storefront.checkout', [
            'customer' => $customer,
            'cart' => $cart,
            'summary' => $summary,
            'addresses' => $addresses,
            'selectedAddress' => $selectedAddress,
            'fulfilmentPlan' => $fulfilmentPlan,
            'codEligible' => $codEligible,
            'codReason' => $codReason,
            'maxCodAmount' => $maxCodAmount,
        ]);
    }

    /**
     * Add new delivery address from checkout modal.
     */
    public function addAddress(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $customer = $user->customer ?: Customer::firstOrCreate(['user_id' => $user->id]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'type' => ['nullable', 'string', 'in:home,work,other'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'pincode' => ['required', 'string', 'regex:/^[1-9][0-9]{5}$/'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if (!empty($validated['is_default'])) {
            $customer->addresses()->update(['is_default' => false]);
        }

        $address = CustomerAddress::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'type' => $validated['type'] ?? AddressType::HOME->value,
            'line1' => $validated['line1'],
            'line2' => $validated['line2'] ?? null,
            'landmark' => $validated['landmark'] ?? null,
            'city' => $validated['city'],
            'state' => $validated['state'],
            'pincode' => $validated['pincode'],
            'lat' => !empty($validated['lat']) ? (float) $validated['lat'] : null,
            'lng' => !empty($validated['lng']) ? (float) $validated['lng'] : null,
            'is_default' => !empty($validated['is_default']),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Delivery address added successfully',
                'address' => $address,
            ]);
        }

        return redirect()->route('checkout.index', ['address_id' => $address->id])->with('success', 'Address added successfully.');
    }

    /**
     * Place order action.
     */
    public function placeOrder(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $customer = $user->customer ?: Customer::firstOrCreate(['user_id' => $user->id]);
        $sessionId = $request->session()->getId();
        $cart = $this->cartService->getActiveCart($user, $sessionId);

        $validated = $request->validate([
            'address_id' => ['required', 'integer', 'exists:customer_addresses,id'],
            'payment_method' => ['required', 'string', 'in:online,cod,upi,card,netbanking'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $address = CustomerAddress::where('customer_id', $customer->id)
            ->where('id', $validated['address_id'])
            ->firstOrFail();

        try {
            $order = $this->placeOrderAction->execute(
                customer: $customer,
                address: $address,
                cart: $cart,
                paymentMethod: $validated['payment_method'],
                idempotencyKey: $validated['idempotency_key'],
                notes: $validated['notes'] ?? null
            );

            $targetUrl = strtolower($validated['payment_method']) === 'cod'
                ? route('checkout.success', $order->order_number)
                : route('payment.pay', $order->order_number);

            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'success',
                    'order_number' => $order->order_number,
                    'redirect_url' => $targetUrl,
                ]);
            }

            return redirect($targetUrl);
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Order confirmation & tracking screen.
     */
    public function success(Request $request, string $orderNumber): View
    {
        $order = Order::where('order_number', $orderNumber)
            ->with(['fulfilmentOrders.items', 'fulfilmentOrders.seller', 'customer.user'])
            ->firstOrFail();

        return view('storefront.order-success', [
            'order' => $order,
        ]);
    }
}
