<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CODRemittanceStatus;
use App\Enums\FulfilmentType;
use App\Enums\InventoryLocationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\RefundStatus;
use App\Enums\SellerStatus;
use App\Enums\StoreStatus;
use App\Models\Category;
use App\Models\CodRemittance;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\FulfilmentOrder;
use App\Models\Inventory;
use App\Models\InventoryLocation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\Store;
use App\Models\StoreTiming;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\Payment\Drivers\CashfreeGateway;
use App\Services\Payment\Drivers\RazorpayGateway;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\Payment\PaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Customer $customer;
    protected CustomerAddress $address;
    protected Seller $seller;
    protected Store $store;
    protected InventoryLocation $location;
    protected Product $product;
    protected ProductVariant $variant;
    protected PaymentService $paymentService;
    protected InventoryService $inventoryService;
    protected PaymentGatewayManager $gatewayManager;

    protected string $razorpayKeyId = 'rzp_test_testkey123';
    protected string $razorpayKeySecret = 'rzp_test_sec_xyz789';
    protected string $razorpayWebhookSecret = 'rzp_wh_secret_abc123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentService = app(PaymentService::class);
        $this->inventoryService = app(InventoryService::class);
        $this->gatewayManager = app(PaymentGatewayManager::class);

        // Configure test payment gateway settings
        Setting::set('payment_gateway_default', 'razorpay');
        Setting::set('razorpay_key_id', $this->razorpayKeyId);
        Setting::set('razorpay_key_secret', $this->razorpayKeySecret);
        Setting::set('razorpay_webhook_secret', $this->razorpayWebhookSecret);

        // Setup base User & Customer
        $this->user = User::factory()->create([
            'email' => 'shopper@test.local',
            'phone' => '9876543210',
            'status' => 'active',
        ]);

        $this->customer = Customer::create([
            'user_id' => $this->user->id,
            'loyalty_points' => 0,
        ]);

        $this->address = CustomerAddress::create([
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'name' => 'Shopper Residence',
            'phone' => '9876543210',
            'line1' => 'Flat 202, Sunshine Apts',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560034',
            'latitude' => 12.9352,
            'longitude' => 77.6245,
            'is_default' => true,
        ]);

        // Category
        $category = Category::create([
            'name' => 'Groceries',
            'slug' => 'groceries',
            'is_active' => true,
        ]);

        // Seller & Store
        $sellerUser = User::factory()->create(['status' => 'active']);
        $this->seller = Seller::create([
            'user_id' => $sellerUser->id,
            'business_name' => 'Koramangala Mart',
            'legal_name' => 'Koramangala Mart Private Limited',
            'slug' => 'koramangala-mart',
            'status' => SellerStatus::APPROVED,
        ]);

        $this->store = Store::create([
            'seller_id' => $this->seller->id,
            'name' => 'Koramangala Express Store',
            'slug' => 'koramangala-express-store',
            'status' => StoreStatus::ACTIVE,
            'is_active' => true,
            'is_quick_enabled' => true,
            'latitude' => 12.9355,
            'longitude' => 77.6248,
            'delivery_radius_km' => 7.0,
        ]);

        $this->location = InventoryLocation::create([
            'store_id' => $this->store->id,
            'seller_id' => $this->seller->id,
            'name' => 'Koramangala Store Shelf',
            'code' => 'KOR-ST-01',
            'type' => InventoryLocationType::STORE,
            'is_active' => true,
        ]);

        // Product & Variant
        $this->product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $category->id,
            'title' => 'Farm Fresh Milk 1L',
            'slug' => 'farm-fresh-milk-1l',
            'type' => ProductType::SIMPLE,
            'status' => ProductStatus::ACTIVE,
            'is_quick_delivery_capable' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'name' => '1L Pack',
            'sku' => 'MILK-1L',
            'selling_price' => 75.00,
            'mrp' => 80.00,
            'is_active' => true,
        ]);

        Inventory::create([
            'variant_id' => $this->variant->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
            'low_stock_threshold' => 5,
        ]);
    }

    /**
     * Helper to create an order with fulfilment order and inventory reservation.
     */
    protected function createTestOrder(float $total = 150.00, PaymentMethod $method = PaymentMethod::UPI): Order
    {
        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'customer_id' => $this->customer->id,
            'customer_address_id' => $this->address->id,
            'address_snapshot' => [
                'name' => $this->address->name,
                'phone' => $this->address->phone,
                'line1' => $this->address->line1,
                'city' => $this->address->city,
                'state' => $this->address->state,
                'pincode' => $this->address->pincode,
            ],
            'subtotal' => $total - 20,
            'delivery_fee' => 20.00,
            'tax_total' => 0.00,
            'discount_total' => 0.00,
            'total' => $total,
            'status' => OrderStatus::PLACED,
            'payment_status' => $method === PaymentMethod::COD ? PaymentStatus::COD_PENDING : PaymentStatus::PENDING,
            'payment_method' => $method,
            'currency' => 'INR',
        ]);

        $fo = FulfilmentOrder::create([
            'order_id' => $order->id,
            'fulfilment_order_number' => 'FO-' . strtoupper(uniqid()),
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'inventory_location_id' => $this->location->id,
            'subtotal' => $total - 20,
            'delivery_fee' => 20.00,
            'commission_amount' => 15.00,
            'seller_payable' => $total - 15.00,
            'status' => OrderStatus::PLACED,
        ]);

        OrderItem::create([
            'fulfilment_order_id' => $fo->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'title' => $this->product->title,
            'variant_name' => $this->variant->name,
            'sku' => $this->variant->sku,
            'qty' => 2,
            'price' => 65.00,
            'tax' => 0.00,
            'discount' => 0.00,
            'total' => 130.00,
        ]);

        // Reserve stock
        $inv = Inventory::where('variant_id', $this->variant->id)
            ->where('location_id', $this->location->id)
            ->firstOrFail();

        $this->inventoryService->reserve(
            $inv,
            2,
            $order->order_number
        );

        return $order;
    }

    public function test_initiate_online_payment_creates_gateway_order_via_razorpay(): void
    {
        $order = $this->createTestOrder(250.00);

        Http::fake([
            'https://api.razorpay.com/v1/orders' => Http::response([
                'id' => 'order_test_rzp_987654',
                'entity' => 'order',
                'amount' => 25000,
                'currency' => 'INR',
                'receipt' => $order->order_number,
                'status' => 'created',
            ], 200),
        ]);

        $initData = $this->paymentService->initiateOnlinePayment($order);

        $this->assertEquals('razorpay', $initData['gateway']);
        $this->assertEquals('order_test_rzp_987654', $initData['gateway_order_id']);
        $this->assertEquals($this->razorpayKeyId, $initData['key_id']);
        $this->assertEquals(250.00, $initData['amount']);
        $this->assertEquals($order->order_number, $initData['order_number']);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'gateway' => 'razorpay',
            'gateway_order_id' => 'order_test_rzp_987654',
            'status' => PaymentStatus::PENDING->value,
        ]);
    }

    public function test_payment_pay_route_renders_checkout_screen(): void
    {
        $order = $this->createTestOrder(150.00);

        Http::fake([
            'https://api.razorpay.com/v1/orders' => Http::response([
                'id' => 'order_test_rzp_112233',
                'amount' => 15000,
                'currency' => 'INR',
                'receipt' => $order->order_number,
            ], 200),
        ]);

        $response = $this->actingAs($this->user)->get(route('payment.pay', $order->order_number));

        $response->assertStatus(200);
        $response->assertViewIs('storefront.payment-checkout');
        $response->assertSee('Complete Your Payment');
        $response->assertSee('order_test_rzp_112233');
    }

    public function test_payment_pay_redirects_if_order_already_paid(): void
    {
        $order = $this->createTestOrder(150.00);
        $order->payment_status = PaymentStatus::PAID;
        $order->save();

        $response = $this->actingAs($this->user)->get(route('payment.pay', $order->order_number));

        $response->assertRedirect(route('checkout.success', $order->order_number));
    }

    public function test_browser_callback_with_valid_signature_marks_order_paid_and_processing(): void
    {
        $order = $this->createTestOrder(300.00);

        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'razorpay',
            'gateway_order_id' => 'order_rzp_valid_123',
            'amount' => 300.00,
            'currency' => 'INR',
            'status' => PaymentStatus::PENDING,
        ]);

        $gatewayPaymentId = 'pay_rzp_mock_456';
        $signature = hash_hmac('sha256', "{$payment->gateway_order_id}|{$gatewayPaymentId}", $this->razorpayKeySecret);

        $response = $this->actingAs($this->user)->post(route('payment.callback'), [
            'razorpay_order_id' => $payment->gateway_order_id,
            'razorpay_payment_id' => $gatewayPaymentId,
            'razorpay_signature' => $signature,
        ]);

        $response->assertRedirect(route('checkout.success', $order->order_number));
        $response->assertSessionHas('success');

        $payment->refresh();
        $order->refresh();

        $this->assertEquals(PaymentStatus::PAID, $payment->status);
        $this->assertEquals($gatewayPaymentId, $payment->gateway_payment_id);
        $this->assertNotNull($payment->verified_at);

        $this->assertEquals(PaymentStatus::PAID, $order->payment_status);
        $this->assertEquals(OrderStatus::PROCESSING, $order->status);

        // Fulfilment orders transitioned to PROCESSING
        $fo = $order->fulfilmentOrders()->first();
        $this->assertEquals('processing', is_object($fo->status) ? $fo->status->value : $fo->status);

        // Status history created
        $this->assertDatabaseHas('order_status_history', [
            'orderable_type' => Order::class,
            'orderable_id' => $order->id,
            'to_status' => OrderStatus::PROCESSING->value,
        ]);
    }

    public function test_browser_callback_is_idempotent(): void
    {
        $order = $this->createTestOrder(300.00);

        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'razorpay',
            'gateway_order_id' => 'order_rzp_idem_123',
            'amount' => 300.00,
            'currency' => 'INR',
            'status' => PaymentStatus::PENDING,
        ]);

        $gatewayPaymentId = 'pay_rzp_idem_456';
        $signature = hash_hmac('sha256', "{$payment->gateway_order_id}|{$gatewayPaymentId}", $this->razorpayKeySecret);

        // Call once
        $this->actingAs($this->user)->post(route('payment.callback'), [
            'razorpay_order_id' => $payment->gateway_order_id,
            'razorpay_payment_id' => $gatewayPaymentId,
            'razorpay_signature' => $signature,
        ]);

        // Call again with same data
        $response2 = $this->actingAs($this->user)->post(route('payment.callback'), [
            'razorpay_order_id' => $payment->gateway_order_id,
            'razorpay_payment_id' => $gatewayPaymentId,
            'razorpay_signature' => $signature,
        ]);

        $response2->assertRedirect(route('checkout.success', $order->order_number));

        // Still PAID without errors
        $payment->refresh();
        $this->assertEquals(PaymentStatus::PAID, $payment->status);
    }

    public function test_browser_callback_with_tampered_signature_fails_and_releases_inventory(): void
    {
        $order = $this->createTestOrder(200.00);

        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'razorpay',
            'gateway_order_id' => 'order_rzp_tamper_123',
            'amount' => 200.00,
            'currency' => 'INR',
            'status' => PaymentStatus::PENDING,
        ]);

        $invBefore = Inventory::where('variant_id', $this->variant->id)
            ->where('location_id', $this->location->id)
            ->first();
        $this->assertEquals(2, $invBefore->quantity_reserved);

        // Submit bad signature
        $response = $this->actingAs($this->user)->post(route('payment.callback'), [
            'razorpay_order_id' => $payment->gateway_order_id,
            'razorpay_payment_id' => 'pay_tampered_999',
            'razorpay_signature' => 'invalid_forged_signature_hash',
        ]);

        $response->assertRedirect(route('payment.retry', $order->order_number));
        $response->assertSessionHas('error');

        $payment->refresh();
        $order->refresh();

        $this->assertEquals(PaymentStatus::FAILED, $payment->status);
        $this->assertEquals(PaymentStatus::FAILED, $order->payment_status);

        // Inventory reservation must have been released!
        $invAfter = Inventory::where('variant_id', $this->variant->id)
            ->where('location_id', $this->location->id)
            ->first();
        $this->assertEquals(0, $invAfter->quantity_reserved);
    }

    public function test_browser_callback_with_client_reported_error_fails_and_allows_retry(): void
    {
        $order = $this->createTestOrder(200.00);

        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'razorpay',
            'gateway_order_id' => 'order_rzp_cancel_123',
            'amount' => 200.00,
            'currency' => 'INR',
            'status' => PaymentStatus::PENDING,
        ]);

        $response = $this->actingAs($this->user)->post(route('payment.callback'), [
            'error' => [
                'code' => 'BAD_REQUEST_ERROR',
                'description' => 'Payment was cancelled by user',
                'metadata' => [
                    'order_id' => $payment->gateway_order_id,
                ],
            ],
        ]);

        $response->assertRedirect(route('payment.retry', $order->order_number));

        $payment->refresh();
        $this->assertEquals(PaymentStatus::FAILED, $payment->status);
    }

    public function test_payment_retry_page_renders_with_try_again_button(): void
    {
        $order = $this->createTestOrder(200.00);
        $order->payment_status = PaymentStatus::FAILED;
        $order->save();

        $response = $this->actingAs($this->user)->get(route('payment.retry', $order->order_number));

        $response->assertStatus(200);
        $response->assertViewIs('storefront.payment-retry');
        $response->assertSee('Payment Incomplete');
        $response->assertSee(route('payment.pay', $order->order_number));
    }

    public function test_webhook_with_invalid_signature_is_rejected_with_400(): void
    {
        $payload = json_encode(['event' => 'payment.captured']);

        $response = $this->postJson(
            route('payment.webhook', ['gateway' => 'razorpay']),
            ['event' => 'payment.captured'],
            ['X-Razorpay-Signature' => 'completely_invalid_signature']
        );

        $response->assertStatus(400);
        $response->assertJson(['status' => 'error', 'message' => 'Invalid webhook signature.']);
    }

    public function test_webhook_payment_captured_marks_payment_and_order_paid(): void
    {
        $order = $this->createTestOrder(180.00);

        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'razorpay',
            'gateway_order_id' => 'order_rzp_wh_180',
            'amount' => 180.00,
            'currency' => 'INR',
            'status' => PaymentStatus::PENDING,
        ]);

        $webhookPayload = [
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_rzp_wh_captured_999',
                        'order_id' => $payment->gateway_order_id,
                        'amount' => 18000,
                        'status' => 'captured',
                    ],
                ],
            ],
        ];

        $rawBody = json_encode($webhookPayload);
        $signature = hash_hmac('sha256', $rawBody, $this->razorpayWebhookSecret);

        $response = $this->call(
            'POST',
            route('payment.webhook', ['gateway' => 'razorpay']),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
            ],
            $rawBody
        );

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $payment->refresh();
        $order->refresh();

        $this->assertEquals(PaymentStatus::PAID, $payment->status);
        $this->assertEquals('pay_rzp_wh_captured_999', $payment->gateway_payment_id);
        $this->assertEquals(PaymentStatus::PAID, $order->payment_status);
        $this->assertEquals(OrderStatus::PROCESSING, $order->status);
    }

    public function test_webhook_payment_failed_event_releases_inventory(): void
    {
        $order = $this->createTestOrder(180.00);

        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'razorpay',
            'gateway_order_id' => 'order_rzp_wh_fail_180',
            'amount' => 180.00,
            'currency' => 'INR',
            'status' => PaymentStatus::PENDING,
        ]);

        $webhookPayload = [
            'event' => 'payment.failed',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_rzp_wh_fail_000',
                        'order_id' => $payment->gateway_order_id,
                        'amount' => 18000,
                        'error_description' => 'Card expired',
                    ],
                ],
            ],
        ];

        $rawBody = json_encode($webhookPayload);
        $signature = hash_hmac('sha256', $rawBody, $this->razorpayWebhookSecret);

        $response = $this->call(
            'POST',
            route('payment.webhook', ['gateway' => 'razorpay']),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
            ],
            $rawBody
        );

        $response->assertStatus(200);

        $payment->refresh();
        $order->refresh();

        $this->assertEquals(PaymentStatus::FAILED, $payment->status);
        $this->assertEquals(PaymentStatus::FAILED, $order->payment_status);

        // Reserved inventory released
        $invAfter = Inventory::where('variant_id', $this->variant->id)
            ->where('location_id', $this->location->id)
            ->first();
        $this->assertEquals(0, $invAfter->quantity_reserved);
    }

    public function test_cod_flow_creates_cod_pending_and_collect_cod_payment_records_remittance(): void
    {
        $order = $this->createTestOrder(220.00, PaymentMethod::COD);

        // Initially COD Pending
        $this->assertEquals(PaymentStatus::COD_PENDING, $order->payment_status);

        // When delivery agent delivers and collects cash:
        $payment = $this->paymentService->collectCodPayment($order, 220.00, 'CHQ-COD-9988');

        $this->assertEquals(PaymentStatus::PAID, $payment->status);
        $this->assertEquals(PaymentMethod::COD, $payment->method);

        $order->refresh();
        $this->assertEquals(PaymentStatus::PAID, $order->payment_status);

        // Check CodRemittance record was created
        $this->assertDatabaseHas('cod_remittances', [
            'seller_id' => $this->seller->id,
            'amount' => 205.00, // 220 - 15 commission
            'status' => CODRemittanceStatus::COLLECTED->value,
            'reference' => 'CHQ-COD-9988',
        ]);
    }

    public function test_full_and_partial_refunds_via_gateway_api(): void
    {
        $order = $this->createTestOrder(300.00);

        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'razorpay',
            'gateway_payment_id' => 'pay_rzp_refund_test',
            'amount' => 300.00,
            'currency' => 'INR',
            'status' => PaymentStatus::PAID,
        ]);
        $order->payment_status = PaymentStatus::PAID;
        $order->save();

        Http::fake([
            'https://api.razorpay.com/v1/payments/pay_rzp_refund_test/refund' => Http::response([
                'id' => 'rfnd_test_123',
                'entity' => 'refund',
                'amount' => 10000,
                'currency' => 'INR',
                'payment_id' => 'pay_rzp_refund_test',
                'status' => 'processed',
            ], 200),
        ]);

        // 1. Partial refund of 100.00
        $refund1 = $this->paymentService->processRefund($payment, 100.00, 'Item returned partially');

        $this->assertEquals(100.00, (float) $refund1->amount);
        $this->assertEquals(RefundStatus::PROCESSED, $refund1->status);
        $this->assertEquals('rfnd_test_123', $refund1->gateway_refund_id);

        $order->refresh();
        $this->assertEquals(PaymentStatus::PARTIALLY_REFUNDED, $order->payment_status);

        // 2. Full remaining refund of 200.00
        Http::fake([
            'https://api.razorpay.com/v1/payments/pay_rzp_refund_test/refund' => Http::response([
                'id' => 'rfnd_test_456',
                'entity' => 'refund',
                'amount' => 20000,
                'currency' => 'INR',
                'payment_id' => 'pay_rzp_refund_test',
                'status' => 'processed',
            ], 200),
        ]);

        $refund2 = $this->paymentService->processRefund($payment, 200.00, 'Order completely refunded');

        $order->refresh();
        $this->assertEquals(PaymentStatus::REFUNDED, $order->payment_status);

        // 3. Excess refund throws InvalidArgumentException
        $this->expectException(\InvalidArgumentException::class);
        $this->paymentService->processRefund($payment, 50.00, 'Over refund');
    }

    public function test_reconcile_payments_command_flags_mismatches_and_syncs_status(): void
    {
        $order = $this->createTestOrder(120.00);

        // Local payment says PENDING
        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'razorpay',
            'gateway_payment_id' => 'pay_remote_captured_888',
            'amount' => 120.00,
            'currency' => 'INR',
            'status' => PaymentStatus::PENDING,
            'created_at' => Carbon::now()->subHours(2),
        ]);

        // Remote gateway says CAPTURED
        Http::fake([
            'https://api.razorpay.com/v1/payments/pay_remote_captured_888' => Http::response([
                'id' => 'pay_remote_captured_888',
                'entity' => 'payment',
                'amount' => 12000,
                'status' => 'captured',
                'currency' => 'INR',
            ], 200),
        ]);

        // Run without --sync
        $this->artisan('payments:reconcile --days=2')
            ->expectsOutputToContain('payment mismatch(es)')
            ->assertExitCode(0);

        // Local status remains PENDING
        $payment->refresh();
        $this->assertEquals(PaymentStatus::PENDING, $payment->status);

        // Run with --sync
        $this->artisan('payments:reconcile --days=2 --sync')
            ->expectsOutputToContain('Successfully auto-synced 1 payment(s)')
            ->assertExitCode(0);

        // Local status is now PAID and order is updated
        $payment->refresh();
        $order->refresh();
        $this->assertEquals(PaymentStatus::PAID, $payment->status);
        $this->assertEquals(PaymentStatus::PAID, $order->payment_status);
    }

    public function test_cashfree_driver_resolves_and_creates_order_payload(): void
    {
        $driver = $this->gatewayManager->driver('cashfree');
        $this->assertInstanceOf(CashfreeGateway::class, $driver);
        $this->assertEquals('cashfree', $driver->getIdentifier());

        $order = $this->createTestOrder(400.00);

        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'cashfree',
            'amount' => 400.00,
            'currency' => 'INR',
            'status' => PaymentStatus::PENDING,
        ]);

        Http::fake([
            'https://sandbox.cashfree.com/pg/orders' => Http::response([
                'order_id' => $order->order_number,
                'order_amount' => 400.00,
                'order_currency' => 'INR',
                'payment_session_id' => 'session_mock_cf_123',
            ], 200),
        ]);

        $result = $driver->createOrder($order, $payment);

        $this->assertEquals($order->order_number, $result['gateway_order_id']);
        $this->assertEquals(400.00, $result['amount']);
    }

    public function test_sensitive_card_details_are_sanitized_in_gateway_responses(): void
    {
        $order = $this->createTestOrder(100.00);

        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'razorpay',
            'amount' => 100.00,
            'currency' => 'INR',
            'status' => PaymentStatus::PENDING,
        ]);

        Http::fake([
            'https://api.razorpay.com/v1/payments/pay_card_sensitive' => Http::response([
                'id' => 'pay_card_sensitive',
                'amount' => 10000,
                'status' => 'captured',
                'card' => [
                    'number' => '4111111111111234',
                    'cvv' => '123',
                    'network' => 'Visa',
                ],
            ], 200),
        ]);

        $gateway = $this->gatewayManager->driver('razorpay');
        $fetched = $gateway->fetchPayment('pay_card_sensitive');

        // Number must be masked, and CVV must be completely omitted
        $this->assertEquals('**** **** **** 1234', $fetched['card']['number']);
        $this->assertArrayNotHasKey('cvv', $fetched['card']);
    }
}
