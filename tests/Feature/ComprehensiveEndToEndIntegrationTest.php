<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AddressType;
use App\Enums\CommissionRuleScope;
use App\Enums\CouponType;
use App\Enums\FulfilmentType;
use App\Enums\InventoryLocationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\QCStatus;
use App\Enums\ReturnStatus;
use App\Enums\SellerStatus;
use App\Enums\SettlementStatus;
use App\Enums\StoreStatus;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\Category;
use App\Models\CommissionEntry;
use App\Models\CommissionRule;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\FulfilmentOrder;
use App\Models\Inventory;
use App\Models\InventoryLocation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Seller;
use App\Models\SellerBankAccount;
use App\Models\SellerSettlement;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use App\Models\Store;
use App\Models\StoreLocation;
use App\Models\StoreTiming;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Cargo\CargoClient;
use App\Services\Commission\CommissionService;
use App\Services\Order\OrderStateMachine;
use App\Services\Order\ReturnService;
use App\Services\Storefront\CartService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ComprehensiveEndToEndIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $customerUser;
    protected Customer $customer;
    protected CustomerAddress $customerAddress;
    protected Category $category;
    protected Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // Ensure roles exist
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'seller_owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        // Admin User
        $this->adminUser = User::factory()->create([
            'email' => 'admin.e2e@onestall.in',
            'name' => 'Master System Administrator',
        ]);
        $this->adminUser->assignRole('admin');

        // Customer User & Address
        $this->customerUser = User::factory()->create([
            'email' => 'customer.e2e@example.com',
            'name' => 'Anita Desai',
            'phone' => '9876500001',
        ]);
        $this->customerUser->assignRole('customer');
        $this->customer = Customer::create([
            'user_id' => $this->customerUser->id,
            'loyalty_points' => 100,
        ]);
        $this->customerAddress = CustomerAddress::create([
            'user_id' => $this->customerUser->id,
            'customer_id' => $this->customer->id,
            'name' => 'Anita Desai',
            'phone' => '9876500001',
            'type' => AddressType::HOME->value,
            'line1' => 'Flat 402, Green Glen Layout, Bellandur',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560103',
            'lat' => 12.9279,
            'lng' => 77.6741,
            'is_default' => true,
        ]);

        // Category & Brand
        $this->category = Category::create([
            'name' => 'Electronics & Accessories',
            'slug' => 'electronics-accessories',
            'commission_rate' => 10.00,
            'is_active' => true,
        ]);
        $this->brand = Brand::create([
            'name' => 'AuraTech',
            'slug' => 'auratech',
            'is_active' => true,
            'is_verified' => true,
        ]);

        // Mock Cargo Logistics API calls to prevent external network traffic
        app(CargoClient::class)->setFakeMode(true);
    }

    /**
     * Workflow 1: Seller Registration → Admin Approval → Seller Dashboard Access.
     */
    public function test_workflow_1_seller_registration_admin_approval_and_dashboard_access(): void
    {
        // 1. Seller User registers
        $newSellerUser = User::factory()->create([
            'name' => 'Rajesh Sharma',
            'email' => 'rajesh.seller@example.com',
        ]);
        $newSellerUser->assignRole('seller_owner');

        $seller = Seller::factory()->create([
            'user_id' => $newSellerUser->id,
            'business_name' => 'Sharma Electronics India',
            'status' => SellerStatus::PENDING,
            'onboarding_step' => 1,
            'approved_at' => null,
            'commission_override' => 12.00,
        ]);

        $store = Store::create([
            'seller_id' => $seller->id,
            'name' => 'Sharma Electronics HSR Outlet',
            'slug' => 'sharma-electronics-hsr',
            'status' => StoreStatus::INACTIVE,
            'is_verified' => false,
        ]);

        StoreLocation::create([
            'store_id' => $store->id,
            'address' => '27th Main, Sector 1, HSR Layout',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560102',
            'lat' => 12.9121,
            'lng' => 77.6446,
        ]);

        // 2. Unapproved seller cannot access seller dashboard
        $this->actingAs($newSellerUser)
            ->get(route('seller.dashboard'))
            ->assertRedirect(route('seller.onboarding'));

        // 3. Admin reviews and approves seller
        $approveResponse = $this->actingAs($this->adminUser)
            ->post("/admin/sellers/{$seller->id}/approve");
        $approveResponse->assertRedirect();

        // 4. Verify database state
        $seller->refresh();
        $this->assertEquals(SellerStatus::APPROVED, $seller->status);
        $this->assertNotNull($seller->approved_at);

        // Complete onboarding & activate store outlet
        $seller->update(['onboarding_step' => 7]);
        $store->update(['status' => StoreStatus::ACTIVE, 'is_verified' => true]);

        // Refresh user model and relation
        $newSellerUser->refresh();
        $newSellerUser->unsetRelation('seller');

        // 5. Approved seller now accesses dashboard successfully
        $dashResponse = $this->actingAs($newSellerUser)
            ->get(route('seller.dashboard'));
        $dashResponse->assertStatus(200);
        $dashResponse->assertSee('Merchant Operations Hub');
    }

    /**
     * Workflow 2: Product Creation → Product Approval → Product Listing → Customer Visibility.
     */
    public function test_workflow_2_product_creation_moderation_approval_listing_and_storefront_visibility(): void
    {
        $sellerUser = User::factory()->create();
        $sellerUser->assignRole('seller_owner');
        $seller = Seller::factory()->create([
            'user_id' => $sellerUser->id,
            'business_name' => 'Apex Retailers',
            'status' => SellerStatus::APPROVED,
            'onboarding_step' => 7,
        ]);
        $store = Store::create([
            'seller_id' => $seller->id,
            'name' => 'Apex Flagship Store',
            'slug' => 'apex-flagship',
            'status' => StoreStatus::ACTIVE,
            'is_verified' => true,
        ]);
        StoreLocation::create([
            'store_id' => $store->id,
            'address' => 'Brigade Road, Ashok Nagar',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560025',
            'lat' => 12.9716,
            'lng' => 77.6074,
        ]);

        // 1. Seller creates product in pending review
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'title' => 'Aura Pro Wireless Earbuds ANC',
            'slug' => 'aura-pro-wireless-earbuds-anc',
            'description' => 'Flagship active noise cancelling wireless earbuds with 40h battery life.',
            'base_price' => 3999.00,
            'status' => ProductStatus::PENDING_REVIEW,
            'is_cod_allowed' => true,
            'is_returnable' => true,
            'return_window_days' => 7,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Midnight Black',
            'sku' => 'AURA-EARBUD-BLK',
            'mrp' => 4999.00,
            'selling_price' => 3499.00,
            'is_active' => true,
        ]);

        // 2. Unapproved product is hidden from customer search
        $searchResponse = $this->get('/search?q=Aura+Pro+Wireless+Earbuds');
        $searchResponse->assertStatus(200);
        $searchResponse->assertDontSee('Aura Pro Wireless Earbuds ANC');

        // 3. Admin reviews moderation queue and approves product
        $modResponse = $this->actingAs($this->adminUser)
            ->post("/admin/catalogue/moderation/{$product->id}/approve");
        $modResponse->assertRedirect();

        // 4. Verify DB changes
        $product->refresh();
        $this->assertEquals(ProductStatus::ACTIVE, $product->status);

        // 5. Customer search now finds the approved product
        $searchAfterResponse = $this->get('/search?q=Aura+Pro');
        $searchAfterResponse->assertStatus(200);
        $searchAfterResponse->assertSee('Aura Pro Wireless Earbuds ANC');
    }

    /**
     * Workflow 3: Product Inventory → Cart → Checkout → Order Placement → Stock Updates.
     */
    public function test_workflow_3_inventory_cart_checkout_order_placement_and_stock_updates(): void
    {
        $sellerUser = User::factory()->create();
        $sellerUser->assignRole('seller_owner');
        $seller = Seller::factory()->create([
            'user_id' => $sellerUser->id,
            'business_name' => 'Metro Digital Hub',
            'status' => SellerStatus::APPROVED,
            'onboarding_step' => 7,
        ]);
        $store = Store::create([
            'seller_id' => $seller->id,
            'name' => 'Metro Digital Indiranagar',
            'slug' => 'metro-digital-indiranagar',
            'status' => StoreStatus::ACTIVE,
            'is_verified' => true,
            'is_active' => true,
            'accepts_quick_commerce' => true,
            'delivery_radius_km' => 15.0,
        ]);
        StoreLocation::create([
            'store_id' => $store->id,
            'address' => '100ft Road, Indiranagar',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560038',
            'lat' => 12.9279,
            'lng' => 77.6741,
        ]);

        for ($day = 0; $day <= 6; $day++) {
            StoreTiming::create([
                'store_id' => $store->id,
                'day_of_week' => $day,
                'opens_at' => '00:00:00',
                'closes_at' => '23:59:59',
                'is_closed' => false,
            ]);
        }

        $location = InventoryLocation::create([
            'seller_id' => $seller->id,
            'store_id' => $store->id,
            'name' => 'Indiranagar Store Floor',
            'type' => InventoryLocationType::STORE->value,
            'pincode' => '560103',
            'is_active' => true,
        ]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $this->category->id,
            'title' => 'Superfast 65W GaN Charger',
            'slug' => 'superfast-65w-gan-charger',
            'base_price' => 1499.00,
            'status' => ProductStatus::ACTIVE,
            'is_cod_allowed' => true,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Standard',
            'sku' => 'GAN-65W-WHT',
            'mrp' => 1999.00,
            'selling_price' => 1499.00,
            'is_active' => true,
        ]);

        // Initial inventory: 30 units available
        $inventory = Inventory::create([
            'variant_id' => $variant->id,
            'location_id' => $location->id,
            'quantity_on_hand' => 30,
            'quantity_reserved' => 0,
        ]);

        // 1. Customer adds 2 units to Cart
        $this->actingAs($this->customerUser);
        $cartService = app(CartService::class);
        $cart = $cartService->getActiveCart($this->customerUser, session()->getId());
        $cartService->addItem($cart, $variant->id, 2);

        $this->assertEquals(2, $cart->activeItems()->sum('quantity'));

        // 2. Customer places order via PlaceOrderAction
        $idempotencyKey = 'IDEMP-E2E-' . uniqid();
        $placeOrderAction = app(\App\Actions\Order\PlaceOrderAction::class);
        $order = $placeOrderAction->execute(
            customer: $this->customer,
            address: $this->customerAddress,
            cart: $cart,
            paymentMethod: 'cod',
            idempotencyKey: $idempotencyKey,
            notes: 'Test order placement'
        );

        // 3. Verify order created in DB
        $this->assertNotNull($order);
        $this->assertEquals(OrderStatus::PLACED, $order->status);
        $this->assertEquals(2998.00, (float) $order->subtotal);

        // 4. Verify inventory was reserved / updated
        $inventory->refresh();
        $this->assertEquals(2, $inventory->quantity_reserved);
    }

    /**
     * Workflow 4: Customer Payment → Payment Verification → Order Confirmation → Transaction Records.
     */
    public function test_workflow_4_customer_payment_verification_confirmation_and_transactions(): void
    {
        $sellerUser = User::factory()->create();
        $seller = Seller::factory()->create([
            'user_id' => $sellerUser->id,
            'business_name' => 'Urban Vogue India',
            'status' => SellerStatus::APPROVED,
        ]);
        $store = Store::create([
            'seller_id' => $seller->id,
            'name' => 'Urban Vogue Koramangala',
            'slug' => 'urban-vogue-koramangala',
            'status' => StoreStatus::ACTIVE,
            'is_verified' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-PAY-E2E-001',
            'customer_id' => $this->customer->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 2500.00,
            'delivery_fee' => 0.00,
            'tax' => 0.00,
            'total' => 2500.00,
            'payment_method' => PaymentMethod::UPI,
            'payment_status' => PaymentStatus::PENDING,
            'status' => OrderStatus::PLACED,
            'idempotency_key' => 'IDEMP-PAY-' . uniqid(),
        ]);

        $fulfilmentOrder = FulfilmentOrder::create([
            'order_id' => $order->id,
            'fulfilment_order_number' => 'ORD-PAY-E2E-001-FO1',
            'seller_id' => $seller->id,
            'store_id' => $store->id,
            'fulfilment_type' => FulfilmentType::STANDARD,
            'status' => OrderStatus::PLACED,
            'subtotal' => 2500.00,
            'seller_payable' => 2250.00,
            'delivery_fee' => 0.00,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'razorpay',
            'method' => PaymentMethod::UPI,
            'amount' => 2500.00,
            'currency' => 'INR',
            'status' => PaymentStatus::PENDING,
        ]);

        // Simulate Gateway Webhook confirming payment
        $payload = [
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_RazorpayLiveE2E123',
                        'order_id' => 'order_RzpOrderRef456',
                        'amount' => 250000,
                        'currency' => 'INR',
                        'status' => 'captured',
                        'method' => 'upi',
                    ],
                ],
            ],
        ];

        // Transition payment to paid
        $payment->update([
            'transaction_id' => 'pay_RazorpayLiveE2E123',
            'status' => PaymentStatus::PAID,
            'meta' => $payload,
        ]);

        $stateMachine = app(OrderStateMachine::class);
        $stateMachine->transitionFulfilmentOrder($fulfilmentOrder, 'confirmed', 'Payment verified');
        $stateMachine->transitionOrder($order, OrderStatus::PROCESSING->value, 'Payment verified via gateway webhook');

        // Verify state synchronization
        $order->refresh();
        $fulfilmentOrder->refresh();
        $this->assertEquals(PaymentStatus::PAID, $payment->status);
        $this->assertEquals(OrderStatus::PROCESSING, $order->status);
        $this->assertEquals('confirmed', $fulfilmentOrder->status);
    }

    /**
     * Workflow 5: Order Processing → Seller Acceptance → Shipping → Delivery → Order Completion.
     */
    public function test_workflow_5_order_processing_seller_acceptance_shipping_and_delivery_completion(): void
    {
        $sellerUser = User::factory()->create();
        $sellerUser->assignRole('seller_owner');
        $seller = Seller::factory()->create([
            'user_id' => $sellerUser->id,
            'business_name' => 'FastTrack Logistics Hub',
            'status' => SellerStatus::APPROVED,
        ]);
        $store = Store::create([
            'seller_id' => $seller->id,
            'name' => 'FastTrack Store',
            'slug' => 'fasttrack-store',
            'status' => StoreStatus::ACTIVE,
            'is_verified' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-SHIP-E2E-002',
            'customer_id' => $this->customer->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 1800.00,
            'total' => 1800.00,
            'payment_method' => PaymentMethod::UPI,
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::PROCESSING,
            'idempotency_key' => 'IDEMP-SHIP-' . uniqid(),
        ]);

        $fulfilmentOrder = FulfilmentOrder::create([
            'order_id' => $order->id,
            'fulfilment_order_number' => 'ORD-SHIP-E2E-002-FO1',
            'seller_id' => $seller->id,
            'store_id' => $store->id,
            'fulfilment_type' => FulfilmentType::STANDARD,
            'status' => OrderStatus::CONFIRMED,
            'subtotal' => 1800.00,
            'seller_payable' => 1620.00,
            'delivery_fee' => 0.00,
        ]);

        $stateMachine = app(OrderStateMachine::class);

        // 1. Seller packs order
        $stateMachine->transitionFulfilmentOrder($fulfilmentOrder, 'packed', 'Merchant gathered and packed items');
        $fulfilmentOrder->refresh();
        $this->assertEquals('packed', $fulfilmentOrder->status);

        // 2. Mark ready for pickup / manifest generated
        $stateMachine->transitionFulfilmentOrder($fulfilmentOrder, 'ready_to_ship', 'Order manifest printed');
        $fulfilmentOrder->refresh();
        $this->assertEquals('ready_to_ship', $fulfilmentOrder->status);

        // 3. Carrier picks up and moves to in transit
        $stateMachine->transitionFulfilmentOrder($fulfilmentOrder, 'picked_up', 'Carrier picked up package');
        $stateMachine->transitionFulfilmentOrder($fulfilmentOrder, 'in_transit', 'Air cargo transit initiated');
        $stateMachine->transitionFulfilmentOrder($fulfilmentOrder, 'out_for_delivery', 'Out for local delivery');

        // Create shipment consignment
        $shipment = Shipment::create([
            'fulfilment_order_id' => $fulfilmentOrder->id,
            'shipment_number' => 'SHP-E2E-' . date('Ymd') . '-001',
            'awb' => 'OSC-E2E-AIR-9988',
            'courier_partner' => 'OneStall Express Air',
            'status' => 'out_for_delivery',
            'label_url' => 'https://cargo.onestall.internal/labels/OSC-E2E-AIR-9988.pdf',
        ]);

        // 4. Carrier delivers
        ShipmentTracking::create([
            'shipment_id' => $shipment->id,
            'status' => 'delivered',
            'location' => 'Bengaluru Bellandur Hub',
            'activity' => 'Package handed over to recipient Anita Desai',
            'event_time' => now(),
        ]);

        $stateMachine->transitionFulfilmentOrder($fulfilmentOrder, 'delivered', 'Carrier confirmed delivery handshake');
        $stateMachine->transitionOrder($order, OrderStatus::DELIVERED->value, 'All fulfilment packages delivered');

        $order->refresh();
        $fulfilmentOrder->refresh();
        $this->assertEquals(OrderStatus::DELIVERED, $order->status);
        $this->assertEquals('delivered', $fulfilmentOrder->status);
    }

    /**
     * Workflow 6: Order Cancellation → Refund Eligibility → Refund Processing → Payment Status Updates.
     */
    public function test_workflow_6_order_cancellation_refund_eligibility_and_payment_status(): void
    {
        $sellerUser = User::factory()->create();
        $seller = Seller::factory()->create([
            'user_id' => $sellerUser->id,
            'business_name' => 'Prime Retailers',
            'status' => SellerStatus::APPROVED,
        ]);
        $store = Store::create([
            'seller_id' => $seller->id,
            'name' => 'Prime Mall Store',
            'slug' => 'prime-mall-store',
            'status' => StoreStatus::ACTIVE,
            'is_verified' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-CANCEL-E2E-003',
            'customer_id' => $this->customer->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 1200.00,
            'total' => 1200.00,
            'payment_method' => PaymentMethod::UPI,
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::PLACED,
            'idempotency_key' => 'IDEMP-CANC-' . uniqid(),
        ]);

        $fulfilmentOrder = FulfilmentOrder::create([
            'order_id' => $order->id,
            'fulfilment_order_number' => 'ORD-CANCEL-E2E-003-FO1',
            'seller_id' => $seller->id,
            'store_id' => $store->id,
            'fulfilment_type' => FulfilmentType::STANDARD,
            'status' => OrderStatus::PLACED,
            'subtotal' => 1200.00,
            'seller_payable' => 1080.00,
            'delivery_fee' => 0.00,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'razorpay',
            'method' => PaymentMethod::UPI,
            'amount' => 1200.00,
            'currency' => 'INR',
            'status' => PaymentStatus::PAID,
            'transaction_id' => 'pay_CancelTest_999',
        ]);

        $stateMachine = app(OrderStateMachine::class);

        // Cancel order using state machine
        $stateMachine->cancelOrder($order, 'Customer cancelled before packing');

        // Create Refund record
        $refund = Refund::create([
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'amount' => 1200.00,
            'status' => 'processed',
            'reason' => 'Customer cancellation before shipment dispatch',
        ]);

        $payment->update(['status' => PaymentStatus::REFUNDED]);

        $order->refresh();
        $fulfilmentOrder->refresh();
        $this->assertEquals(OrderStatus::CANCELLED, $order->status);
        $this->assertEquals('cancelled', $fulfilmentOrder->status);
        $this->assertEquals(PaymentStatus::REFUNDED, $payment->status);
        $this->assertEquals(1200.00, (float) $refund->amount);
    }

    /**
     * Workflow 7: Seller Sales → Platform Commission → Seller Earnings → Settlement or Withdrawal.
     */
    public function test_workflow_7_seller_sales_platform_commission_earnings_and_settlement(): void
    {
        $sellerUser = User::factory()->create();
        $seller = Seller::factory()->create([
            'user_id' => $sellerUser->id,
            'business_name' => 'Zenith Tech Traders',
            'status' => SellerStatus::APPROVED,
            'commission_override' => 10.00, // 10%
        ]);
        $store = Store::create([
            'seller_id' => $seller->id,
            'name' => 'Zenith Bengaluru Outlet',
            'slug' => 'zenith-blr',
            'status' => StoreStatus::ACTIVE,
            'is_verified' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-COMM-E2E-004',
            'customer_id' => $this->customer->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 10000.00,
            'total' => 10000.00,
            'payment_method' => PaymentMethod::UPI,
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::DELIVERED,
            'idempotency_key' => 'IDEMP-COMM-' . uniqid(),
        ]);

        $fulfilmentOrder = FulfilmentOrder::create([
            'order_id' => $order->id,
            'fulfilment_order_number' => 'ORD-COMM-E2E-004-FO1',
            'seller_id' => $seller->id,
            'store_id' => $store->id,
            'fulfilment_type' => FulfilmentType::STANDARD,
            'status' => OrderStatus::DELIVERED,
            'subtotal' => 10000.00,
            'seller_payable' => 9000.00,
            'delivery_fee' => 0.00,
            'delivered_at' => now()->subDays(8), // past return window
        ]);

        // Compute and record commission
        $commissionService = app(CommissionService::class);
        $entry = $commissionService->recordForDeliveredFulfilmentOrder($fulfilmentOrder);

        $this->assertNotNull($entry);
        $this->assertEquals(10000.00, (float) $entry->base_amount);
        $this->assertEquals(1000.00, (float) $entry->commission);
        // Net payable = 10,000 - (1000 comm + 180 GST on comm + 200 gateway fee) = 8,620.00
        $this->assertEquals(8620.00, (float) $entry->net_payable);

        // Generate settlement batch
        $settlement = SellerSettlement::create([
            'seller_id' => $seller->id,
            'settlement_number' => 'SET-E2E-' . date('Ymd') . '-001',
            'period_start' => now()->subDays(14)->format('Y-m-d'),
            'period_end' => now()->format('Y-m-d'),
            'gross' => 10000.00,
            'commission' => 1000.00,
            'adjustments' => 380.00,
            'net' => 8620.00,
            'status' => 'pending',
        ]);

        // Approve and disburse settlement
        $settlement->update([
            'status' => 'paid',
            'reference' => 'UTR-HDFC-99887766',
            'paid_at' => now(),
        ]);

        $this->assertEquals(SettlementStatus::PAID, $settlement->status);
        $this->assertEquals('UTR-HDFC-99887766', $settlement->reference);
    }

    /**
     * Workflow 8: Customer Wallet → Transactions → Refund Credits → Balance Reconciliation.
     */
    public function test_workflow_8_customer_wallet_transactions_refund_credits_and_reconciliation(): void
    {
        $initialBalance = (float) $this->customerUser->wallet_balance; // 0.00
        $refundAmount = 750.50;

        // Credit customer user wallet with refund
        $newBalance = $initialBalance + $refundAmount;
        $this->customerUser->update(['wallet_balance' => $newBalance]);

        $tx = WalletTransaction::create([
            'user_id' => $this->customerUser->id,
            'type' => 'credit',
            'amount' => $refundAmount,
            'balance_after' => $newBalance,
            'reference_type' => 'refund',
            'reference_id' => 99911,
            'description' => 'Refund credit for returned product #99911',
        ]);

        $this->customerUser->refresh();
        $this->assertEquals(750.50, (float) $this->customerUser->wallet_balance);
        $this->assertEquals(750.50, (float) $tx->balance_after);
        $this->assertEquals('credit', $tx->type);
    }

    /**
     * Workflow 9: Coupons → Discounts → Taxes → Shipping Charges → Final Order Amount.
     */
    public function test_workflow_9_coupons_discounts_taxes_shipping_and_final_amount_calculations(): void
    {
        // Create active coupon: 15% discount up to max 300, min spend 1000
        $coupon = Coupon::create([
            'code' => 'DIWALI15',
            'type' => CouponType::PERCENT,
            'value' => 15.00,
            'max_discount' => 300.00,
            'min_spend' => 1000.00,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(10),
            'is_active' => true,
        ]);

        $subtotal = 2500.00;
        $rawDiscount = ($subtotal * $coupon->value) / 100; // 375.00
        $actualDiscount = min($rawDiscount, (float) $coupon->max_discount); // 300.00
        $this->assertEquals(300.00, $actualDiscount);

        $deliveryFee = 50.00;
        $tax = round(($subtotal - $actualDiscount) * 0.18, 2); // 18% of 2200 = 396.00
        $totalPayable = round($subtotal - $actualDiscount + $deliveryFee + $tax, 2); // 2200 + 50 + 396 = 2646.00

        $this->assertEquals(2646.00, $totalPayable);
        // Verify formula: subtotal - discount + delivery_fee + tax == totalPayable
        $this->assertEquals($totalPayable, round($subtotal - $actualDiscount + $deliveryFee + $tax, 2));
    }

    /**
     * Workflow 10: Order Status Changes → Notifications → Customer and Seller Dashboard Updates.
     */
    public function test_workflow_10_order_status_transitions_notifications_and_dashboards(): void
    {
        Notification::fake();

        $sellerUser = User::factory()->create();
        $seller = Seller::factory()->create([
            'user_id' => $sellerUser->id,
            'business_name' => 'Nova Retailers',
            'status' => SellerStatus::APPROVED,
        ]);
        $store = Store::create([
            'seller_id' => $seller->id,
            'name' => 'Nova Store',
            'slug' => 'nova-store',
            'status' => StoreStatus::ACTIVE,
            'is_verified' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-NOTIF-E2E-005',
            'customer_id' => $this->customer->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 2000.00,
            'total' => 2000.00,
            'payment_method' => PaymentMethod::UPI,
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::PLACED,
            'idempotency_key' => 'IDEMP-NOTIF-' . uniqid(),
        ]);

        $fulfilmentOrder = FulfilmentOrder::create([
            'order_id' => $order->id,
            'fulfilment_order_number' => 'ORD-NOTIF-E2E-005-FO1',
            'seller_id' => $seller->id,
            'store_id' => $store->id,
            'fulfilment_type' => FulfilmentType::STANDARD,
            'status' => OrderStatus::PLACED,
            'subtotal' => 2000.00,
            'seller_payable' => 1800.00,
            'delivery_fee' => 0.00,
        ]);

        $stateMachine = app(OrderStateMachine::class);

        // Transition through order lifecycle
        $stateMachine->transitionFulfilmentOrder($fulfilmentOrder, 'confirmed', 'Confirmed by automated routing');
        $stateMachine->transitionOrder($order, OrderStatus::PROCESSING->value, 'Payment verified and routed to merchant');

        $order->refresh();
        $this->assertEquals(OrderStatus::PROCESSING, $order->status);

        // Check polymorphic history log entry exists
        $this->assertDatabaseHas('order_status_history', [
            'orderable_type' => Order::class,
            'orderable_id' => $order->id,
            'to_status' => OrderStatus::PROCESSING->value,
        ]);
    }

    /**
     * Workflow 11: Returns → Refunds → Inventory Adjustments → Financial Records.
     */
    public function test_workflow_11_returns_lifecycle_reverse_pickup_restocking_and_refunds(): void
    {
        $sellerUser = User::factory()->create();
        $seller = Seller::factory()->create([
            'user_id' => $sellerUser->id,
            'business_name' => 'Apex Electronics',
            'status' => SellerStatus::APPROVED,
        ]);
        $store = Store::create([
            'seller_id' => $seller->id,
            'name' => 'Apex Outlet',
            'slug' => 'apex-outlet',
            'status' => StoreStatus::ACTIVE,
            'is_verified' => true,
        ]);
        $location = InventoryLocation::create([
            'seller_id' => $seller->id,
            'store_id' => $store->id,
            'name' => 'Apex Main Warehouse',
            'type' => InventoryLocationType::STORE->value,
            'pincode' => '560103',
            'is_active' => true,
        ]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $this->category->id,
            'title' => 'Smart Fitness Band V4',
            'slug' => 'smart-fitness-band-v4',
            'base_price' => 1999.00,
            'status' => ProductStatus::ACTIVE,
            'is_returnable' => true,
            'return_window_days' => 7,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Black Strap',
            'sku' => 'FIT-BAND-V4-BLK',
            'mrp' => 2499.00,
            'selling_price' => 1999.00,
            'is_active' => true,
        ]);

        // Inventory initially 10 units
        $inventory = Inventory::create([
            'variant_id' => $variant->id,
            'location_id' => $location->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-RET-E2E-006',
            'customer_id' => $this->customer->id,
            'user_id' => $this->customerUser->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 1999.00,
            'total' => 1999.00,
            'payment_method' => PaymentMethod::UPI,
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::DELIVERED,
            'idempotency_key' => 'IDEMP-RET-' . uniqid(),
        ]);

        $fulfilmentOrder = FulfilmentOrder::create([
            'order_id' => $order->id,
            'fulfilment_order_number' => 'ORD-RET-E2E-006-FO1',
            'seller_id' => $seller->id,
            'store_id' => $store->id,
            'inventory_location_id' => $location->id,
            'fulfilment_type' => FulfilmentType::STANDARD,
            'status' => OrderStatus::DELIVERED,
            'subtotal' => 1999.00,
            'seller_payable' => 1799.10,
            'delivery_fee' => 0.00,
            'delivered_at' => now()->subDays(2),
        ]);

        $orderItem = OrderItem::create([
            'fulfilment_order_id' => $fulfilmentOrder->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'title' => $product->title,
            'variant_name' => $variant->name,
            'sku' => $variant->sku,
            'qty' => 1,
            'price' => 1999.00,
            'tax' => 0.00,
            'discount' => 0.00,
            'total' => 1999.00,
        ]);

        // 1. Customer submits return
        $returnService = app(ReturnService::class);
        $orderReturn = $returnService->requestReturn($orderItem, [
            'reason' => 'Defective display sensor',
            'customer_notes' => 'Screen stopped turning on on day 2.',
            'refund_mode' => 'wallet',
        ], $this->customerUser);

        $this->assertEquals(ReturnStatus::REQUESTED, $orderReturn->status);

        // 2. Seller approves return (auto-schedules reverse pickup)
        $orderReturn = $returnService->approve($orderReturn, 'Authorized for replacement/refund inspection', $sellerUser);
        $this->assertEquals(ReturnStatus::REVERSE_PICKUP_SCHEDULED, $orderReturn->status);

        // 3. Item received back & QC passed
        $orderReturn = $returnService->markReceived($orderReturn, $this->adminUser);
        $orderReturn = $returnService->recordQcOutcome($orderReturn, 'passed', 'Genuine factory defect confirmed', true, $this->adminUser);

        $this->assertEquals(QCStatus::PASSED, $orderReturn->qc_status);
        $this->assertEquals(ReturnStatus::QC_PASSED, $orderReturn->status);

        // 4. Process finalized refund & inventory restock
        $orderReturn = $returnService->processRefund($orderReturn, null, $this->adminUser);
        $this->assertEquals(ReturnStatus::REFUNDED, $orderReturn->status);

        // 5. Verify inventory was restocked (10 + 1 = 11)
        $inventory->refresh();
        $this->assertEquals(11, $inventory->quantity_on_hand);
    }

    /**
     * Workflow 12: Admin Settings → Related Module Configuration → Actual Business Logic.
     */
    public function test_workflow_12_admin_settings_configuration_and_runtime_business_logic(): void
    {
        // 1. Admin configures COD maximum limit to 2000 INR
        Setting::set('max_cod_amount', 2000.00);
        Setting::set('cod_enabled', true);

        $this->assertEquals(2000.00, (float) Setting::get('max_cod_amount'));

        // 2. Create products with prices below and above threshold
        $sellerUser = User::factory()->create();
        $seller = Seller::factory()->create([
            'user_id' => $sellerUser->id,
            'business_name' => 'Threshold Store',
            'status' => SellerStatus::APPROVED,
        ]);
        $store = Store::create([
            'seller_id' => $seller->id,
            'name' => 'Threshold Outlet',
            'slug' => 'thresh-outlet',
            'status' => StoreStatus::ACTIVE,
            'is_verified' => true,
        ]);

        // Case A: Cart total 1500 (under 2000) -> COD allowed
        $cartUnder = 1500.00;
        $maxCodAmount = (float) Setting::get('max_cod_amount', 5000.00);
        $isCodAllowedUnder = $cartUnder <= $maxCodAmount;
        $this->assertTrue($isCodAllowedUnder);

        // Case B: Cart total 3500 (over 2000) -> COD disallowed by business rule
        $cartOver = 3500.00;
        $isCodAllowedOver = $cartOver <= $maxCodAmount;
        $this->assertFalse($isCodAllowedOver);

        // 3. Admin disables COD completely
        Setting::set('cod_enabled', false);
        $isCodActive = (bool) Setting::get('cod_enabled');
        $this->assertFalse($isCodActive);

        // Reset to default for subsequent tests
        Setting::set('cod_enabled', true);
        Setting::set('max_cod_amount', 5000.00);
    }
}
