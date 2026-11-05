<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CODRemittanceStatus;
use App\Enums\CommissionRuleScope;
use App\Enums\DeliveryAssignmentStatus;
use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\QCStatus;
use App\Enums\QuickFulfilmentStatus;
use App\Enums\RefundStatus;
use App\Enums\ReturnStatus;
use App\Enums\SettlementStatus;
use App\Enums\ShipmentStatus;
use App\Enums\StandardFulfilmentStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\CodRemittance;
use App\Models\CommissionEntry;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryPartner;
use App\Models\FulfilmentOrder;
use App\Models\InventoryLocation;
use App\Models\NdrCase;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\RtoCase;
use App\Models\Seller;
use App\Models\SellerSettlement;
use App\Models\SettlementItem;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use App\Models\Store;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdersPaymentsAndLogisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_and_items_lifecycle(): void
    {
        $customer = Customer::factory()->create();
        $cart = Cart::factory()->create(['customer_id' => $customer->id]);

        $variant1 = ProductVariant::factory()->create(['selling_price' => 500.00]);
        $variant2 = ProductVariant::factory()->create(['selling_price' => 250.00]);

        CartItem::create([
            'cart_id' => $cart->id,
            'variant_id' => $variant1->id,
            'quantity' => 2,
            'price_snapshot' => 500.00,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'variant_id' => $variant2->id,
            'quantity' => 3,
            'price_snapshot' => 250.00,
        ]);

        $this->assertCount(2, $cart->items);
        // (500 * 2) + (250 * 3) = 1000 + 750 = 1750
        $this->assertEquals(1750.00, $cart->totalAmount());
        $this->assertEquals($cart->id, $customer->cart->id);
    }

    public function test_parent_order_and_split_fulfilment_orders(): void
    {
        $customer = Customer::factory()->create();
        $seller1 = Seller::factory()->create();
        $seller2 = Seller::factory()->create();

        $store1 = Store::factory()->create(['seller_id' => $seller1->id]);
        $location1 = InventoryLocation::factory()->create(['seller_id' => $seller1->id, 'store_id' => $store1->id]);

        // Parent Order
        $order = Order::create([
            'order_number' => 'ORD-TEST-0001',
            'customer_id' => $customer->id,
            'address_snapshot' => [
                'name' => 'Alice Johnson',
                'line1' => 'Flat 101, Green Heights',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'pincode' => '560034',
            ],
            'subtotal' => 2000.00,
            'discount' => 100.00,
            'delivery_fee' => 40.00,
            'tax' => 360.00,
            'total' => 2300.00,
            'payment_method' => PaymentMethod::UPI,
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::PLACED,
        ]);

        // Child/Split Fulfilment Order 1 (Quick Commerce from Dark Store)
        $fo1 = FulfilmentOrder::create([
            'order_id' => $order->id,
            'fulfilment_order_number' => 'FO-QC-0001',
            'seller_id' => $seller1->id,
            'store_id' => $store1->id,
            'inventory_location_id' => $location1->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => QuickFulfilmentStatus::STORE_ACCEPTED->value,
            'subtotal' => 1200.00,
            'commission_amount' => 120.00,
            'seller_payable' => 1080.00,
            'estimated_delivery_at' => now()->addMinutes(25),
        ]);

        // Child/Split Fulfilment Order 2 (Standard Marketplace Delivery)
        $fo2 = FulfilmentOrder::create([
            'order_id' => $order->id,
            'fulfilment_order_number' => 'FO-STD-0002',
            'seller_id' => $seller2->id,
            'store_id' => null,
            'inventory_location_id' => null,
            'fulfilment_type' => FulfilmentType::STANDARD,
            'status' => StandardFulfilmentStatus::CONFIRMED->value,
            'subtotal' => 800.00,
            'commission_amount' => 80.00,
            'seller_payable' => 720.00,
            'estimated_delivery_at' => now()->addDays(2),
        ]);

        $this->assertCount(2, $order->fulfilmentOrders);
        $this->assertEquals(PaymentStatus::PAID, $order->payment_status);
        $this->assertEquals(FulfilmentType::QUICK, $fo1->fulfilment_type);
        $this->assertEquals(FulfilmentType::STANDARD, $fo2->fulfilment_type);

        // Check seller scoping
        $seller1Orders = FulfilmentOrder::forSeller($seller1->id)->get();
        $this->assertCount(1, $seller1Orders);
        $this->assertEquals($fo1->id, $seller1Orders->first()->id);
    }

    public function test_order_items_and_status_history(): void
    {
        $fo = FulfilmentOrder::factory()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $user = User::factory()->create();

        $item = OrderItem::create([
            'fulfilment_order_id' => $fo->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'title' => 'Noise Pulse Smartwatch',
            'variant_name' => 'Jet Black',
            'sku' => 'NP-SW-BLK',
            'qty' => 1,
            'price' => 1499.00,
            'tax' => 269.82,
            'discount' => 0.00,
            'total' => 1768.82,
        ]);

        $this->assertEquals('1499.00', (string) $item->price);
        $this->assertEquals('1768.82', (string) $item->total);

        // Record status history on fulfilment order
        $history = OrderStatusHistory::create([
            'orderable_type' => FulfilmentOrder::class,
            'orderable_id' => $fo->id,
            'from_status' => 'placed',
            'to_status' => 'confirmed',
            'note' => 'Order accepted by vendor',
            'changed_by' => $user->id,
        ]);

        $this->assertCount(1, $fo->statusHistory);
        $this->assertEquals('confirmed', $fo->statusHistory->first()->to_status);
    }

    public function test_payments_and_refunds(): void
    {
        $order = Order::factory()->create(['total' => 1500.00]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'razorpay',
            'gateway_order_id' => 'order_K231jklmn',
            'gateway_payment_id' => 'pay_P982310',
            'method' => PaymentMethod::UPI,
            'amount' => 1500.00,
            'currency' => 'INR',
            'status' => PaymentStatus::PAID,
            'raw_response' => ['status' => 'captured', 'method' => 'upi'],
            'verified_at' => now(),
        ]);

        $this->assertEquals(PaymentStatus::PAID, $payment->status);
        $this->assertCount(1, $order->payments);

        // Issue refund
        $refund = Refund::create([
            'payment_id' => $payment->id,
            'amount' => 500.00,
            'reason' => 'Partial cancellation',
            'status' => RefundStatus::PROCESSED,
            'gateway_refund_id' => 'rfnd_R129302',
            'raw_response' => ['status' => 'processed'],
            'processed_at' => now(),
        ]);

        $this->assertCount(1, $payment->refunds);
        $this->assertEquals(RefundStatus::PROCESSED, $refund->status);
    }

    public function test_commission_rules_entries_and_seller_settlement(): void
    {
        $seller = Seller::factory()->create();
        $category = Category::factory()->create(['commission_percent' => 12.00]);

        // Category specific rule
        $rule = CommissionRule::create([
            'name' => 'Fashion 12% Rule',
            'scope' => CommissionRuleScope::CATEGORY,
            'category_id' => $category->id,
            'seller_id' => null,
            'percent' => 12.00,
            'fixed_fee' => 5.00,
            'priority' => 10,
            'is_active' => true,
        ]);

        $this->assertTrue(CommissionRule::active()->where('id', $rule->id)->exists());

        $fo = FulfilmentOrder::factory()->create([
            'seller_id' => $seller->id,
            'subtotal' => 2000.00,
        ]);

        // Commission entry: 12% of 2000 = 240 + 5 fixed = 245 commission, gateway fee 40 => net 1715
        $entry = CommissionEntry::create([
            'fulfilment_order_id' => $fo->id,
            'rule_id' => $rule->id,
            'base_amount' => 2000.00,
            'commission' => 245.00,
            'gateway_fee' => 40.00,
            'shipping_adjustment' => 0.00,
            'net_payable' => 1715.00,
        ]);

        $this->assertEquals('1715.00', (string) $entry->net_payable);

        // Payout settlement
        $settlement = SellerSettlement::create([
            'seller_id' => $seller->id,
            'settlement_number' => 'SETTLE-2026-0001',
            'period_start' => now()->subDays(7)->toDateString(),
            'period_end' => now()->toDateString(),
            'gross' => 2000.00,
            'commission' => 245.00,
            'adjustments' => 40.00,
            'net' => 1715.00,
            'status' => SettlementStatus::PAID,
            'paid_at' => now(),
            'reference' => 'UTR9988221100',
        ]);

        SettlementItem::create([
            'seller_settlement_id' => $settlement->id,
            'fulfilment_order_id' => $fo->id,
            'commission_entry_id' => $entry->id,
            'amount' => 1715.00,
            'type' => 'order_payout',
        ]);

        $this->assertCount(1, $settlement->items);
        $this->assertEquals(SettlementStatus::PAID, $settlement->status);
    }

    public function test_logistics_shipments_tracking_and_delivery_assignments(): void
    {
        $fo = FulfilmentOrder::factory()->create();

        // OneStall Cargo Shipment
        $shipment = Shipment::create([
            'fulfilment_order_id' => $fo->id,
            'shipment_number' => 'SHP-ONESTALL-001',
            'provider' => 'onestall_cargo',
            'awb' => 'AWB9988776655',
            'courier' => 'OneStall Cargo Ground',
            'service' => 'Express',
            'status' => ShipmentStatus::PICKED_UP,
            'pickup_status' => 'picked_up',
            'label_url' => 'labels/shp001.pdf',
            'cod_amount' => 0.00,
            'weight' => 2.50,
            'charges' => 95.00,
        ]);

        // Tracking event
        $tracking = ShipmentTracking::create([
            'shipment_id' => $shipment->id,
            'status' => 'picked_up',
            'location' => 'Bengaluru Whitefield Hub',
            'event_time' => now(),
            'raw' => ['scan_by' => 'Agent #42', 'hub' => 'BLR-01'],
        ]);

        $this->assertCount(1, $shipment->tracking);
        $this->assertEquals('AWB9988776655', $shipment->awb);

        // Hyper-local delivery partner assignment
        $partner = DeliveryPartner::factory()->create([
            'name' => 'Ramesh Kumar',
            'phone' => '9876543210',
            'is_active' => true,
        ]);

        $assignment = DeliveryAssignment::create([
            'fulfilment_order_id' => $fo->id,
            'shipment_id' => $shipment->id,
            'delivery_partner_id' => $partner->id,
            'status' => DeliveryAssignmentStatus::ACCEPTED,
            'assigned_at' => now()->subMinutes(10),
            'accepted_at' => now()->subMinutes(5),
        ]);

        $this->assertEquals(DeliveryAssignmentStatus::ACCEPTED, $assignment->status);
        $this->assertEquals($partner->id, $assignment->deliveryPartner->id);
    }

    public function test_returns_ndr_rto_and_cod_remittances(): void
    {
        $fo = FulfilmentOrder::factory()->create();
        $orderItem = OrderItem::factory()->create(['fulfilment_order_id' => $fo->id]);
        $shipment = Shipment::factory()->create(['fulfilment_order_id' => $fo->id, 'cod_amount' => 1200.00]);
        $seller = $fo->seller;

        // Return Request with QC
        $return = OrderReturn::create([
            'return_number' => 'RET-2026-0001',
            'order_item_id' => $orderItem->id,
            'reason' => 'Damaged in transit',
            'status' => ReturnStatus::REQUESTED,
            'qc_status' => QCStatus::PENDING,
        ]);

        $this->assertEquals(ReturnStatus::REQUESTED, $return->status);
        $this->assertEquals(QCStatus::PENDING, $return->qc_status);

        // NDR case
        $ndr = NdrCase::create([
            'shipment_id' => $shipment->id,
            'reason' => 'Customer unavailable / Door locked',
            'attempts' => 1,
            'status' => 'reattempt_scheduled',
            'customer_action' => 'deliver_tomorrow',
            'action_notes' => 'Customer called, requested afternoon delivery',
        ]);

        $this->assertCount(1, $shipment->ndrCases);
        $this->assertEquals(1, $ndr->attempts);

        // RTO case
        $rto = RtoCase::create([
            'shipment_id' => $shipment->id,
            'reason' => 'Refused at doorstep by customer',
            'status' => 'initiated',
            'initiated_at' => now(),
        ]);

        $this->assertCount(1, $shipment->rtoCases);

        // COD Remittance
        $remittance = CodRemittance::create([
            'shipment_id' => $shipment->id,
            'seller_id' => $seller->id,
            'amount' => 1200.00,
            'status' => CODRemittanceStatus::COLLECTED,
            'collected_at' => now(),
            'reference' => 'CASH-REC-9988',
        ]);

        $this->assertEquals(CODRemittanceStatus::COLLECTED, $remittance->status);
        $this->assertEquals($seller->id, $remittance->seller->id);
    }
}
