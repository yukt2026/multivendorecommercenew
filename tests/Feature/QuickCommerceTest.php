<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeliveryAssignmentStatus;
use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SellerStatus;
use App\Enums\SellerType;
use App\Enums\StoreStatus;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryPartner;
use App\Models\FulfilmentOrder;
use App\Models\Inventory;
use App\Models\InventoryLocation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Store;
use App\Models\StoreLocation;
use App\Models\StoreTiming;
use App\Models\User;
use App\Services\Fulfilment\Strategies\NearbyStoreStrategy;
use App\Services\QuickCommerce\QuickCommerceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickCommerceTest extends TestCase
{
    use RefreshDatabase;

    protected User $sellerUser;
    protected Seller $seller;
    protected Store $storeA;
    protected Store $storeB;
    protected User $customerUser;
    protected Customer $customer;
    protected CustomerAddress $customerAddress;
    protected Product $product;
    protected ProductVariant $variant;
    protected DeliveryPartner $rider;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Seller & User
        $this->sellerUser = User::factory()->create(['email' => 'quick_seller@example.com']);
        $this->seller = Seller::create([
            'user_id' => $this->sellerUser->id,
            'business_name' => 'Quick Hyperlocal Retail',
            'legal_name' => 'Quick Hyperlocal Retail Pvt Ltd',
            'seller_type' => SellerType::MULTI_STORE,
            'status' => SellerStatus::APPROVED,
        ]);

        // 2. Customer
        $this->customerUser = User::factory()->create(['email' => 'customer_quick@example.com']);
        $this->customer = Customer::create(['user_id' => $this->customerUser->id]);
        $this->customerAddress = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'user_id' => $this->customerUser->id,
            'name' => 'Arun Kumar',
            'phone' => '9876543210',
            'line1' => 'Flat 402, Sunshine Residency',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560034',
            'lat' => 12.9352,
            'lng' => 77.6245,
            'is_default' => true,
        ]);

        // 3. Store A (Primary store, 2 km away, Koramangala)
        $this->storeA = Store::create([
            'seller_id' => $this->seller->id,
            'name' => 'Koramangala Dark Store Hub',
            'slug' => 'koramangala-dark-store',
            'status' => StoreStatus::ACTIVE,
            'accepts_quick_commerce' => true,
            'delivery_radius_km' => 6.0,
            'quick_max_orders' => 5,
            'quick_prep_time_minutes' => 10,
            'quick_order_timeout_seconds' => 120,
            'quick_auto_assign_rider' => true,
        ]);
        StoreLocation::create([
            'store_id' => $this->storeA->id,
            'address' => '80 Feet Road, 4th Block Koramangala',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560034',
            'lat' => 12.9340,
            'lng' => 77.6250,
        ]);
        StoreTiming::create([
            'store_id' => $this->storeA->id,
            'day_of_week' => now()->dayOfWeek,
            'opens_at' => '00:00:00',
            'closes_at' => '23:59:59',
            'is_closed' => false,
        ]);

        // 4. Store B (Backup store, 4 km away, HSR Layout)
        $this->storeB = Store::create([
            'seller_id' => $this->seller->id,
            'name' => 'HSR Layout Dark Store Hub',
            'slug' => 'hsr-dark-store',
            'status' => StoreStatus::ACTIVE,
            'accepts_quick_commerce' => true,
            'delivery_radius_km' => 8.0,
            'quick_max_orders' => 15,
            'quick_prep_time_minutes' => 12,
            'quick_order_timeout_seconds' => 120,
            'quick_auto_assign_rider' => true,
        ]);
        StoreLocation::create([
            'store_id' => $this->storeB->id,
            'address' => '27th Main, Sector 1 HSR Layout',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560102',
            'lat' => 12.9110,
            'lng' => 77.6380,
        ]);
        StoreTiming::create([
            'store_id' => $this->storeB->id,
            'day_of_week' => now()->dayOfWeek,
            'opens_at' => '00:00:00',
            'closes_at' => '23:59:59',
            'is_closed' => false,
        ]);

        // 5. Category & Product & Variant
        $category = \App\Models\Category::create([
            'name' => 'Dairy & Eggs',
            'slug' => 'dairy-eggs',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $category->id,
            'title' => 'Fresh Farm Whole Milk 1L',
            'slug' => 'fresh-farm-whole-milk-1l',
            'status' => \App\Enums\ProductStatus::ACTIVE,
            'is_quick_capable' => true,
            'is_cod_allowed' => true,
        ]);
        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'name' => '1 Litre Pouch',
            'sku' => 'MILK-1L',
            'price' => 65.00,
            'selling_price' => 65.00,
            'mrp' => 70.00,
        ]);

        // Inventory Locations & Stock
        $locA = InventoryLocation::create([
            'seller_id' => $this->seller->id,
            'store_id' => $this->storeA->id,
            'name' => 'Koramangala Shelf',
            'code' => 'KOR-01',
            'type' => \App\Enums\InventoryLocationType::STORE,
            'is_active' => true,
        ]);
        Inventory::create([
            'variant_id' => $this->variant->id,
            'location_id' => $locA->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
        ]);

        $locB = InventoryLocation::create([
            'seller_id' => $this->seller->id,
            'store_id' => $this->storeB->id,
            'name' => 'HSR Shelf',
            'code' => 'HSR-01',
            'type' => \App\Enums\InventoryLocationType::STORE,
            'is_active' => true,
        ]);
        Inventory::create([
            'variant_id' => $this->variant->id,
            'location_id' => $locB->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
        ]);

        // 6. Delivery Partner (Rider)
        $this->rider = DeliveryPartner::create([
            'name' => 'Mahesh Rider',
            'phone' => '9123456789',
            'vehicle' => 'Ather 450X',
            'vehicle_number' => 'KA-01-EQ-9988',
            'is_active' => true,
            'status' => 'available',
            'active_orders_count' => 0,
            'current_lat' => 12.9345,
            'current_lng' => 77.6248,
        ]);
    }

    /**
     * Test 1: Dynamic realistic ETA calculation based on distance, prep time, and live load buffer.
     */
    public function test_dynamic_realistic_eta_calculation(): void
    {
        /** @var QuickCommerceService $service */
        $service = app(QuickCommerceService::class);

        // Distance 2 km, store prep time 10 min, 0 active orders
        $etaLowLoad = $service->computeEta($this->storeA, 2.0);

        $this->assertEquals(10, $etaLowLoad['prep_time_minutes']);
        $this->assertEquals(7, $etaLowLoad['travel_time_minutes']); // 2 km * 3.5 = 7 mins
        $this->assertEquals(0, $etaLowLoad['load_buffer_minutes']);
        $this->assertFalse($etaLowLoad['is_overloaded']);
        $this->assertEquals('14-22 mins', $etaLowLoad['formatted_eta']);

        // Now simulate heavy store load: create 4 active orders
        for ($i = 1; $i <= 4; $i++) {
            $parent = Order::create([
                'order_number' => "OS-TEST-ORD{$i}",
                'customer_id' => $this->customer->id,
                'address_snapshot' => $this->customerAddress->toArray(),
                'subtotal' => 100,
                'discount' => 0,
                'delivery_fee' => 30,
                'tax' => 0,
                'total' => 130,
                'payment_method' => PaymentMethod::UPI,
                'payment_status' => PaymentStatus::PAID,
                'status' => OrderStatus::PROCESSING,
            ]);

            FulfilmentOrder::create([
                'order_id' => $parent->id,
                'fulfilment_order_number' => "OS-TEST-ORD{$i}-FO1",
                'seller_id' => $this->seller->id,
                'store_id' => $this->storeA->id,
                'fulfilment_type' => FulfilmentType::QUICK,
                'status' => 'packing',
                'subtotal' => 100,
                'delivery_fee' => 30,
            ]);
        }

        $etaHighLoad = $service->computeEta($this->storeA, 2.0);

        // With 4 active orders (> 3), load buffer = (4 - 3) * 2 = 2 mins
        $this->assertEquals(4, $etaHighLoad['active_orders']);
        $this->assertEquals(2, $etaHighLoad['load_buffer_minutes']);
        $this->assertFalse($etaHighLoad['is_overloaded']); // capacity is 5
        $this->assertEquals('16-24 mins', $etaHighLoad['formatted_eta']);

        // Now add a 5th order to reach full capacity
        $p5 = Order::create([
            'order_number' => 'OS-TEST-ORD5',
            'customer_id' => $this->customer->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 100,
            'discount' => 0,
            'delivery_fee' => 30,
            'tax' => 0,
            'total' => 130,
            'payment_method' => PaymentMethod::UPI,
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::PROCESSING,
        ]);
        FulfilmentOrder::create([
            'order_id' => $p5->id,
            'fulfilment_order_number' => 'OS-TEST-ORD5-FO1',
            'seller_id' => $this->seller->id,
            'store_id' => $this->storeA->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'placed',
            'subtotal' => 100,
            'delivery_fee' => 30,
        ]);

        $this->assertFalse($this->storeA->isUnderQuickCapacity());
        $etaFull = $service->computeEta($this->storeA, 2.0);
        $this->assertTrue($etaFull['is_overloaded']);
    }

    /**
     * Test 2: Overloaded store is rejected by NearbyStoreStrategy fulfilment evaluation.
     */
    public function test_overloaded_store_is_skipped_by_fulfilment_strategy(): void
    {
        $strategy = new NearbyStoreStrategy();
        $invA = $this->storeA->inventoryLocations->first()->inventories->first();

        // 1. When under capacity -> CandidateMatch returned
        $match = $strategy->evaluate($this->variant, 2, [
            'lat' => $this->customerAddress->lat,
            'lng' => $this->customerAddress->lng,
        ], $invA);

        $this->assertNotNull($match);
        $this->assertEquals($this->storeA->id, $match->storeId);
        $this->assertEquals(FulfilmentType::QUICK, $match->fulfilmentType);

        // 2. Overload store A
        $this->storeA->quick_max_orders = 1;
        $this->storeA->save();
        $invA->load('location.store');

        $p = Order::create([
            'order_number' => 'OS-CAP-TEST',
            'customer_id' => $this->customer->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 100,
            'discount' => 0,
            'delivery_fee' => 30,
            'tax' => 0,
            'total' => 130,
            'payment_method' => PaymentMethod::UPI,
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::PROCESSING,
        ]);
        FulfilmentOrder::create([
            'order_id' => $p->id,
            'fulfilment_order_number' => 'OS-CAP-TEST-FO1',
            'seller_id' => $this->seller->id,
            'store_id' => $this->storeA->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'packing',
            'subtotal' => 100,
            'delivery_fee' => 30,
        ]);

        // Now evaluation should return null because store is overloaded
        $matchOverloaded = $strategy->evaluate($this->variant, 2, [
            'lat' => $this->customerAddress->lat,
            'lng' => $this->customerAddress->lng,
        ], $invA);

        $this->assertNull($matchOverloaded);
    }

    /**
     * Test 3: Store rejection automatically reroutes order to next nearest eligible store.
     */
    public function test_store_rejection_auto_reroutes_to_next_nearest_store(): void
    {
        $parentOrder = Order::create([
            'order_number' => 'OS-REROUTE-01',
            'customer_id' => $this->customer->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 130,
            'discount' => 0,
            'delivery_fee' => 30,
            'tax' => 0,
            'total' => 160,
            'payment_method' => PaymentMethod::UPI,
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::PLACED,
        ]);

        $fo = FulfilmentOrder::create([
            'order_id' => $parentOrder->id,
            'fulfilment_order_number' => 'OS-REROUTE-01-FO1',
            'seller_id' => $this->seller->id,
            'store_id' => $this->storeA->id,
            'inventory_location_id' => $this->storeA->inventoryLocations->first()->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'placed',
            'subtotal' => 130,
            'delivery_fee' => 30,
            'delivery_otp' => '654321',
        ]);

        OrderItem::create([
            'fulfilment_order_id' => $fo->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'title' => $this->product->title,
            'sku' => $this->variant->sku,
            'qty' => 2,
            'price' => 65.00,
            'tax' => 0,
            'discount' => 0,
            'total' => 130.00,
        ]);

        // Seller rejects order from Store A
        $response = $this->actingAs($this->sellerUser)
            ->post("/seller/orders/{$fo->id}/reject", [
                'reason' => 'Store experiencing heavy rush',
            ]);

        $response->assertRedirect();

        // Refresh and check that order was rerouted to Store B
        $fo->refresh();
        $this->assertEquals($this->storeB->id, $fo->store_id);
        $this->assertEquals($this->storeA->id, $fo->reassigned_from_store_id);
        $this->assertEquals('placed', $fo->status); // Re-queued as placed for Store B

        $this->assertDatabaseHas('order_status_history', [
            'orderable_id' => $fo->id,
            'from_status' => 'rejected',
            'to_status' => 'placed',
        ]);
    }

    /**
     * Test 4: Background timeout command automatically detects and reroutes timed-out orders.
     */
    public function test_quick_check_timeouts_command_reroutes_expired_orders(): void
    {
        $parentOrder = Order::create([
            'order_number' => 'OS-TIMEOUT-01',
            'customer_id' => $this->customer->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 65,
            'discount' => 0,
            'delivery_fee' => 30,
            'tax' => 0,
            'total' => 95,
            'payment_method' => PaymentMethod::UPI,
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::PLACED,
        ]);

        // Create an order placed 150 seconds ago (store timeout is 120s)
        $fo = FulfilmentOrder::create([
            'order_id' => $parentOrder->id,
            'fulfilment_order_number' => 'OS-TIMEOUT-01-FO1',
            'seller_id' => $this->seller->id,
            'store_id' => $this->storeA->id,
            'inventory_location_id' => $this->storeA->inventoryLocations->first()->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'placed',
            'subtotal' => 65,
            'delivery_fee' => 30,
        ]);
        \Illuminate\Support\Facades\DB::table('fulfilment_orders')->where('id', $fo->id)->update(['created_at' => now()->subSeconds(150)]);

        OrderItem::create([
            'fulfilment_order_id' => $fo->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'title' => $this->product->title,
            'sku' => $this->variant->sku,
            'qty' => 1,
            'price' => 65.00,
            'tax' => 0,
            'discount' => 0,
            'total' => 65.00,
        ]);

        // Run artisan command
        $this->artisan('quick:check-timeouts')
            ->expectsOutputToContain('Scanning pending Quick Commerce orders')
            ->assertExitCode(0);

        $fo->refresh();
        $this->assertEquals($this->storeB->id, $fo->store_id);
        $this->assertEquals($this->storeA->id, $fo->reassigned_from_store_id);
    }

    /**
     * Test 5: Rider Auto-Assignment upon Order Ready.
     */
    public function test_rider_auto_assigned_when_order_marked_ready(): void
    {
        $parentOrder = Order::create([
            'order_number' => 'OS-DISPATCH-01',
            'customer_id' => $this->customer->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 65,
            'discount' => 0,
            'delivery_fee' => 30,
            'tax' => 0,
            'total' => 95,
            'payment_method' => PaymentMethod::UPI,
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::PROCESSING,
        ]);

        $fo = FulfilmentOrder::create([
            'order_id' => $parentOrder->id,
            'fulfilment_order_number' => 'OS-DISPATCH-01-FO1',
            'seller_id' => $this->seller->id,
            'store_id' => $this->storeA->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'packing',
            'subtotal' => 65,
            'delivery_fee' => 30,
        ]);

        // Mark ready for pickup
        $this->actingAs($this->sellerUser)
            ->post("/seller/orders/{$fo->id}/ready")
            ->assertRedirect();

        $fo->refresh();
        $this->rider->refresh();

        $this->assertEquals('rider_assigned', $fo->status);
        $this->assertEquals('busy', $this->rider->status);
        $this->assertEquals(1, $this->rider->active_orders_count);

        $this->assertDatabaseHas('delivery_assignments', [
            'fulfilment_order_id' => $fo->id,
            'delivery_partner_id' => $this->rider->id,
            'status' => DeliveryAssignmentStatus::ASSIGNED->value,
        ]);
    }

    /**
     * Test 6: Rider mobile screen, OTP delivery handshake, and completion.
     */
    public function test_rider_mobile_otp_delivery_handshake(): void
    {
        $parentOrder = Order::create([
            'order_number' => 'OS-OTP-01',
            'customer_id' => $this->customer->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 65,
            'discount' => 0,
            'delivery_fee' => 30,
            'tax' => 0,
            'total' => 95,
            'payment_method' => PaymentMethod::UPI,
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::PROCESSING,
        ]);

        $fo = FulfilmentOrder::create([
            'order_id' => $parentOrder->id,
            'fulfilment_order_number' => 'OS-OTP-01-FO1',
            'seller_id' => $this->seller->id,
            'store_id' => $this->storeA->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'ready',
            'subtotal' => 65,
            'delivery_fee' => 30,
            'delivery_otp' => '984512',
        ]);

        $assignment = DeliveryAssignment::create([
            'fulfilment_order_id' => $fo->id,
            'delivery_partner_id' => $this->rider->id,
            'status' => DeliveryAssignmentStatus::ASSIGNED,
            'assigned_at' => now(),
        ]);
        $this->rider->status = 'busy';
        $this->rider->active_orders_count = 1;
        $this->rider->save();

        // 1. Rider accepts assignment
        $this->post("/rider/orders/{$assignment->id}/accept")
            ->assertRedirect();
        $assignment->refresh();
        $this->assertEquals(DeliveryAssignmentStatus::ACCEPTED, $assignment->status);

        // 2. Rider marks picked up from store
        $this->post("/rider/orders/{$assignment->id}/pickup")
            ->assertRedirect();
        $assignment->refresh();
        $fo->refresh();
        $this->assertEquals(DeliveryAssignmentStatus::PICKED_UP, $assignment->status);
        $this->assertEquals('out_for_delivery', $fo->status);

        // 3. Attempt delivery with WRONG OTP -> rejected
        $this->post("/rider/orders/{$assignment->id}/deliver", ['otp' => '000000'])
            ->assertSessionHas('error');
        $this->assertNotEquals('delivered', $fo->refresh()->status);

        // 4. Submit CORRECT OTP -> success
        $this->post("/rider/orders/{$assignment->id}/deliver", ['otp' => '984512'])
            ->assertRedirect('/rider')
            ->assertSessionHas('success');

        $assignment->refresh();
        $fo->refresh();
        $this->rider->refresh();

        $this->assertEquals(DeliveryAssignmentStatus::DELIVERED, $assignment->status);
        $this->assertTrue($assignment->otp_verified);
        $this->assertEquals('delivered', $fo->status);
        $this->assertNotNull($fo->delivered_at);

        // Rider should now be freed back to available
        $this->assertEquals('available', $this->rider->status);
        $this->assertEquals(0, $this->rider->active_orders_count);
    }

    /**
     * Test 7: Telemetry, live coordinates ping, and customer live location endpoint.
     */
    public function test_telemetry_and_customer_live_tracking_endpoints(): void
    {
        // 1. Telemetry location update API
        $response = $this->postJson('/api/v1/rider/location', [
            'rider_id' => $this->rider->id,
            'lat' => 12.9360,
            'lng' => 77.6255,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('lat', 12.936);

        $this->rider->refresh();
        $this->assertEquals(12.936, $this->rider->current_lat);

        // 2. Customer live tracking screen & location endpoint
        $order = Order::create([
            'order_number' => 'OS-LIVE-01',
            'customer_id' => $this->customer->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 65,
            'discount' => 0,
            'delivery_fee' => 30,
            'tax' => 0,
            'total' => 95,
            'payment_method' => PaymentMethod::UPI,
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::PROCESSING,
        ]);

        $fo = FulfilmentOrder::create([
            'order_id' => $order->id,
            'fulfilment_order_number' => 'OS-LIVE-01-FO1',
            'seller_id' => $this->seller->id,
            'store_id' => $this->storeA->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'out_for_delivery',
            'subtotal' => 65,
            'delivery_fee' => 30,
            'delivery_otp' => '123456',
        ]);

        DeliveryAssignment::create([
            'fulfilment_order_id' => $fo->id,
            'delivery_partner_id' => $this->rider->id,
            'status' => DeliveryAssignmentStatus::PICKED_UP,
            'assigned_at' => now(),
        ]);

        // Customer page render
        $this->actingAs($this->customerUser)
            ->get("/orders/{$order->id}/live-tracking")
            ->assertOk()
            ->assertSee('Quick Commerce Live Tracking')
            ->assertSee('123456');

        // Customer live location API polling
        $liveRes = $this->getJson("/orders/{$order->id}/live-location");
        $liveRes->assertOk()
            ->assertJsonPath('delivery_otp', '123456')
            ->assertJsonPath('package_status', 'out_for_delivery')
            ->assertJsonPath('rider.name', 'Mahesh Rider')
            ->assertJsonPath('rider.lat', 12.936);
    }

    /**
     * Test 8: Admin Operations Mission Control and SLA breach detection.
     */
    public function test_admin_quick_commerce_ops_dashboard(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Create an order placed 200s ago (breached SLA)
        $order = Order::create([
            'order_number' => 'OS-SLA-01',
            'customer_id' => $this->customer->id,
            'address_snapshot' => $this->customerAddress->toArray(),
            'subtotal' => 65,
            'discount' => 0,
            'delivery_fee' => 30,
            'tax' => 0,
            'total' => 95,
            'payment_method' => PaymentMethod::UPI,
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::PLACED,
        ]);

        $fo = FulfilmentOrder::create([
            'order_id' => $order->id,
            'fulfilment_order_number' => 'OS-SLA-01-FO1',
            'seller_id' => $this->seller->id,
            'store_id' => $this->storeA->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'placed',
            'subtotal' => 65,
            'delivery_fee' => 30,
        ]);
        \Illuminate\Support\Facades\DB::table('fulfilment_orders')->where('id', $fo->id)->update(['created_at' => now()->subSeconds(200)]);
        $fo->refresh();

        $this->assertTrue($fo->isSlaBreached());

        // Test Admin Dashboard Screen
        $this->actingAs($admin)
            ->get('/admin/quick-commerce')
            ->assertOk()
            ->assertSee('Quick Commerce Mission Control')
            ->assertSee('OS-SLA-01-FO1');

        // Test JSON Feed
        $this->actingAs($admin)
            ->getJson('/admin/quick-commerce/api-feed')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.breached_count', 1);
    }
}
