<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CODRemittanceStatus;
use App\Enums\FulfilmentType;
use App\Enums\InventoryLocationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SellerStatus;
use App\Enums\ShipmentStatus;
use App\Enums\StoreStatus;
use App\Jobs\Cargo\CreateShipmentJob;
use App\Jobs\Cargo\TrackingSyncJob;
use App\Models\Category;
use App\Models\CodRemittance;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\FulfilmentOrder;
use App\Models\Inventory;
use App\Models\InventoryLocation;
use App\Models\NdrCase;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RtoCase;
use App\Models\Seller;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use App\Models\Store;
use App\Models\User;
use App\Services\Cargo\CargoClient;
use App\Services\Cargo\Contracts\LogisticsProvider;
use App\Services\Order\OrderStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CargoLogisticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $customerUser;
    protected Customer $customer;
    protected CustomerAddress $address;
    protected User $sellerUser;
    protected Seller $seller;
    protected Store $store;
    protected InventoryLocation $location;
    protected User $adminUser;
    protected Product $product;
    protected ProductVariant $variant;
    protected Inventory $inventory;
    protected LogisticsProvider $provider;
    protected CargoClient $cargoClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cargoClient = app(CargoClient::class);
        $this->provider = app(LogisticsProvider::class);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        // Customer
        $this->customerUser = User::factory()->create([
            'name' => 'Kavita Iyer',
            'email' => 'kavita@example.com',
        ]);
        $this->customer = Customer::create([
            'user_id' => $this->customerUser->id,
            'loyalty_points' => 0,
        ]);
        $this->address = CustomerAddress::create([
            'user_id' => $this->customerUser->id,
            'customer_id' => $this->customer->id,
            'name' => 'Kavita Iyer',
            'phone' => '9876543210',
            'line1' => '24 Park View, MG Road',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560001',
            'is_default' => true,
        ]);

        // Seller
        $this->sellerUser = User::factory()->create([
            'name' => 'Vikram Merchant',
            'email' => 'vikram@seller.com',
        ]);
        $this->seller = Seller::create([
            'user_id' => $this->sellerUser->id,
            'company_name' => 'Zenith Lifestyle Logistics',
            'business_name' => 'Zenith Lifestyle',
            'legal_name' => 'Zenith Retailers LLP',
            'slug' => 'zenith-lifestyle',
            'status' => SellerStatus::APPROVED,
        ]);
        $this->store = Store::create([
            'seller_id' => $this->seller->id,
            'name' => 'Zenith Hub Bengaluru',
            'slug' => 'zenith-hub-bengaluru',
            'status' => StoreStatus::ACTIVE,
            'pincode' => '560038',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'address' => 'Plot 45, Indiranagar',
            'phone' => '9876543210',
        ]);
        $this->location = InventoryLocation::create([
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'name' => 'Central Hub Shelf 1',
            'code' => 'ZEN-HUB-01',
            'type' => InventoryLocationType::WAREHOUSE,
            'is_active' => true,
        ]);

        // Admin
        $this->adminUser = User::factory()->create([
            'name' => 'Logistics Chief',
            'email' => 'logistics@onestall.local',
        ]);
        $this->adminUser->assignRole('super_admin');

        // Product & Variant
        $cat = Category::create(['name' => 'Apparel', 'slug' => 'apparel', 'is_active' => true]);
        $this->product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $cat->id,
            'title' => 'Men Regular Fit Linen Shirt',
            'slug' => 'men-regular-fit-linen-shirt',
            'is_active' => true,
        ]);
        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'ZEN-SHIRT-M',
            'name' => 'Medium / Navy Blue',
            'selling_price' => 1499.00,
            'mrp' => 2499.00,
            'weight_grams' => 450,
            'is_active' => true,
        ]);

        $this->inventory = Inventory::create([
            'variant_id' => $this->variant->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
            'reorder_threshold' => 5,
        ]);
    }

    /**
     * Create test standard fulfilment order.
     */
    protected function createStandardOrder(bool $isCod = false, string $foStatus = 'packed'): array
    {
        $order = Order::create([
            'order_number' => 'ORD-CARGO-' . strtoupper(uniqid()),
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
            'subtotal' => 1499.00,
            'tax' => 0.00,
            'delivery_fee' => 40.00,
            'discount' => 0.00,
            'total' => 1539.00,
            'status' => OrderStatus::PROCESSING,
            'payment_status' => $isCod ? PaymentStatus::PENDING : PaymentStatus::PAID,
            'payment_method' => $isCod ? PaymentMethod::COD : PaymentMethod::UPI,
        ]);

        $fo = FulfilmentOrder::create([
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'inventory_location_id' => $this->location->id,
            'fulfilment_order_number' => 'FO-CARGO-' . strtoupper(uniqid()),
            'fulfilment_type' => FulfilmentType::STANDARD,
            'status' => $foStatus,
            'subtotal' => 1499.00,
            'delivery_fee' => 40.00,
            'commission_amount' => 149.90,
            'total' => 1539.00,
        ]);

        $item = OrderItem::create([
            'fulfilment_order_id' => $fo->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'title' => $this->product->title,
            'variant_name' => $this->variant->name,
            'sku' => $this->variant->sku,
            'price' => 1499.00,
            'qty' => 1,
            'tax' => 0.00,
            'discount' => 0.00,
            'total' => 1499.00,
        ]);

        return [$order, $fo, $item];
    }

    public function test_cargo_client_fake_mode_operations(): void
    {
        $this->cargoClient->setFakeMode(true);
        $this->assertTrue($this->cargoClient->isFakeMode());

        // 1. Create Shipment
        $shipmentRes = $this->cargoClient->createShipment([
            'shipment_number' => 'SHP-TEST-001',
            'courier' => 'OneStall Express Surface',
        ]);
        $this->assertTrue($shipmentRes['success']);
        $this->assertStringStartsWith('OSC-', $shipmentRes['awb']);

        // 2. Generate AWB
        $awbRes = $this->cargoClient->generateAwb('SHP-TEST-001');
        $this->assertTrue($awbRes['success']);
        $this->assertNotEmpty($awbRes['label_url']);

        // 3. Get Rates
        $rates = $this->cargoClient->getRates(['origin_pincode' => '560038', 'dest_pincode' => '560001', 'weight_kg' => 1.5]);
        $this->assertIsArray($rates);
        $this->assertCount(3, $rates);

        // 4. Request Pickup
        $pickup = $this->cargoClient->requestPickup(['pickup_pincode' => '560038']);
        $this->assertTrue($pickup['success']);
        $this->assertEquals('pickup_scheduled', $pickup['status']);

        // 5. Track
        $track = $this->cargoClient->trackShipment('OSC-SAMPLE');
        $this->assertTrue($track['success']);
        $this->assertNotEmpty($track['events']);

        // 6. NDR Actions
        $ndr = $this->cargoClient->getNdrActions('OSC-SAMPLE');
        $this->assertTrue($ndr['success']);
        $this->assertContains('reattempt', $ndr['allowed_actions']);

        // 7. COD Remittances
        $cod = $this->cargoClient->getCodRemittanceData();
        $this->assertIsArray($cod);
        $this->assertNotEmpty($cod);
    }

    public function test_cargo_client_live_http_request_with_signatures_and_retries(): void
    {
        $this->cargoClient->setFakeMode(false);

        Http::fake([
            'https://api.onestallcargo.internal/v1/shipments' => Http::response([
                'success' => true,
                'awb' => 'OSC-LIVE-123',
                'courier' => 'OneStall Express Air',
                'charges' => 195.00,
            ], 201),
        ]);

        $res = $this->cargoClient->createShipment(['shipment_number' => 'SHP-LIVE-01']);

        $this->assertTrue($res['success']);
        $this->assertEquals('OSC-LIVE-123', $res['awb']);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization')
                && $request->hasHeader('X-Cargo-Signature')
                && $request->hasHeader('X-Cargo-Timestamp');
        });
    }

    public function test_logistics_provider_creates_shipment_and_tracking_milestone(): void
    {
        [$order, $fo, $item] = $this->createStandardOrder(isCod: true);

        $result = $this->provider->createShipment($fo, [
            'courier' => 'OneStall Cargo Express',
        ]);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['awb']);

        $this->assertDatabaseHas('shipments', [
            'fulfilment_order_id' => $fo->id,
            'courier' => 'OneStall Cargo Express',
            'status' => ShipmentStatus::MANIFEST_CREATED->value,
            'cod_amount' => 1539.00,
        ]);

        $shipment = Shipment::where('fulfilment_order_id', $fo->id)->first();
        $this->assertNotNull($shipment);

        $this->assertDatabaseHas('shipment_tracking', [
            'shipment_id' => $shipment->id,
            'status' => 'manifest_created',
        ]);
    }

    public function test_order_transition_to_ready_to_ship_auto_dispatches_create_shipment_job(): void
    {
        Queue::fake();

        [$order, $fo, $item] = $this->createStandardOrder(foStatus: 'packed');

        $stateMachine = app(OrderStateMachine::class);
        $stateMachine->transitionFulfilmentOrder($fo, 'ready_to_ship');

        Queue::assertPushed(CreateShipmentJob::class, function ($job) use ($fo) {
            return $job->fulfilmentOrder->id === $fo->id;
        });
    }

    public function test_create_shipment_job_execution_creates_awb_and_label(): void
    {
        [$order, $fo, $item] = $this->createStandardOrder();

        $job = new CreateShipmentJob($fo);
        $job->handle($this->provider);

        $shipment = Shipment::where('fulfilment_order_id', $fo->id)->first();
        $this->assertNotNull($shipment);
        $this->assertNotNull($shipment->awb);
        $this->assertNotNull($shipment->label_url);
    }

    public function test_tracking_sync_job_iterates_in_transit_shipments(): void
    {
        [$order, $fo, $item] = $this->createStandardOrder();
        $shipment = Shipment::create([
            'fulfilment_order_id' => $fo->id,
            'shipment_number' => 'SHP-SYNC-01',
            'provider' => 'onestall_cargo',
            'awb' => 'OSC-SYNC-12345',
            'courier' => 'OneStall Express',
            'status' => ShipmentStatus::IN_TRANSIT,
        ]);

        $job = new TrackingSyncJob($shipment->id);
        $job->handle($this->provider);

        $this->assertDatabaseHas('shipment_tracking', [
            'shipment_id' => $shipment->id,
            'status' => 'in_transit',
        ]);
    }

    public function test_webhook_verifies_signature_and_updates_milestones(): void
    {
        [$order, $fo, $item] = $this->createStandardOrder();
        $shipment = Shipment::create([
            'fulfilment_order_id' => $fo->id,
            'shipment_number' => 'SHP-HOOK-01',
            'provider' => 'onestall_cargo',
            'awb' => 'OSC-HOOK-001',
            'courier' => 'OneStall Express',
            'status' => ShipmentStatus::MANIFEST_CREATED,
        ]);

        $payload = [
            'event' => 'shipment.in_transit',
            'data' => [
                'awb' => 'OSC-HOOK-001',
                'shipment_number' => 'SHP-HOOK-01',
                'location' => 'Yeshwanthpur Sorting Hub',
                'remarks' => 'Scanned in linehaul transit',
                'timestamp' => now()->toIso8601String(),
            ],
        ];

        $rawJson = json_encode($payload);
        $secret = $this->cargoClient->getWebhookSecret();
        $validSignature = hash_hmac('sha256', $rawJson, $secret);

        // 1. Invalid signature rejected
        $this->call('POST', '/api/v1/cargo/webhook', [], [], [], [
            'HTTP_X-Cargo-Signature' => 'invalid-hash',
            'CONTENT_TYPE' => 'application/json',
        ], $rawJson)->assertStatus(401);

        // 2. Valid signature accepted
        $response = $this->call('POST', '/api/v1/cargo/webhook', [], [], [], [
            'HTTP_X-Cargo-Signature' => $validSignature,
            'CONTENT_TYPE' => 'application/json',
        ], $rawJson);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $shipment->refresh();
        $this->assertEquals(ShipmentStatus::IN_TRANSIT, $shipment->status);

        $this->assertDatabaseHas('shipment_tracking', [
            'shipment_id' => $shipment->id,
            'status' => 'shipment.in_transit',
            'location' => 'Yeshwanthpur Sorting Hub',
        ]);
    }

    public function test_webhook_delivered_event_marks_order_delivered_and_logs_cod(): void
    {
        [$order, $fo, $item] = $this->createStandardOrder(isCod: true, foStatus: 'ready_to_ship');
        $shipment = Shipment::create([
            'fulfilment_order_id' => $fo->id,
            'shipment_number' => 'SHP-DELIV-01',
            'provider' => 'onestall_cargo',
            'awb' => 'OSC-DELIV-999',
            'courier' => 'OneStall Express',
            'cod_amount' => 1539.00,
            'status' => ShipmentStatus::OUT_FOR_DELIVERY,
        ]);

        $payload = [
            'event' => 'shipment.delivered',
            'data' => [
                'awb' => 'OSC-DELIV-999',
                'location' => 'Koramangala, Bengaluru',
                'timestamp' => now()->toIso8601String(),
            ],
        ];

        $rawJson = json_encode($payload);
        $signature = hash_hmac('sha256', $rawJson, $this->cargoClient->getWebhookSecret());

        $this->call('POST', '/api/v1/cargo/webhook', [], [], [], [
            'HTTP_X-Cargo-Signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $rawJson)->assertOk();

        $shipment->refresh();
        $this->assertEquals(ShipmentStatus::DELIVERED, $shipment->status);

        $fo->refresh();
        $this->assertEquals('delivered', $fo->status);

        // COD Remittance created
        $this->assertDatabaseHas('cod_remittances', [
            'shipment_id' => $shipment->id,
            'seller_id' => $this->seller->id,
            'amount' => 1539.00,
            'status' => CODRemittanceStatus::COLLECTED->value,
        ]);
    }

    public function test_webhook_ndr_event_and_seller_resolution_action(): void
    {
        [$order, $fo, $item] = $this->createStandardOrder();
        $shipment = Shipment::create([
            'fulfilment_order_id' => $fo->id,
            'shipment_number' => 'SHP-NDR-01',
            'provider' => 'onestall_cargo',
            'awb' => 'OSC-NDR-555',
            'courier' => 'OneStall Express',
            'status' => ShipmentStatus::OUT_FOR_DELIVERY,
        ]);

        $payload = [
            'event' => 'shipment.ndr',
            'data' => [
                'awb' => 'OSC-NDR-555',
                'ndr_reason' => 'Customer phone switched off',
                'attempt_number' => 1,
            ],
        ];

        $raw = json_encode($payload);
        $sig = hash_hmac('sha256', $raw, $this->cargoClient->getWebhookSecret());

        $this->call('POST', '/api/v1/cargo/webhook', [], [], [], [
            'HTTP_X-Cargo-Signature' => $sig,
            'CONTENT_TYPE' => 'application/json',
        ], $raw)->assertOk();

        $shipment->refresh();
        $this->assertEquals(ShipmentStatus::NDR_RAISED, $shipment->status);

        $this->assertDatabaseHas('ndr_cases', [
            'shipment_id' => $shipment->id,
            'reason' => 'Customer phone switched off',
            'attempts' => 1,
        ]);

        // Seller resolves NDR with reattempt
        $response = $this->actingAs($this->sellerUser)
            ->post(route('seller.shipments.ndr_action', $shipment), [
                'action' => 'reattempt',
                'remarks' => 'Customer was traveling, confirmed available tomorrow morning.',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('ndr_cases', [
            'shipment_id' => $shipment->id,
            'status' => 'reattempt_scheduled',
            'customer_action' => 'reattempt',
        ]);
    }

    public function test_customer_can_resolve_ndr_from_tracking_page(): void
    {
        [$order, $fo, $item] = $this->createStandardOrder();
        $shipment = Shipment::create([
            'fulfilment_order_id' => $fo->id,
            'shipment_number' => 'SHP-CUST-NDR',
            'provider' => 'onestall_cargo',
            'awb' => 'OSC-CUST-NDR',
            'courier' => 'OneStall Express',
            'status' => ShipmentStatus::NDR_RAISED,
        ]);

        NdrCase::create([
            'shipment_id' => $shipment->id,
            'reason' => 'Gate locked / No response',
            'attempts' => 1,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->customerUser)
            ->post(route('orders.ndr.resolve', [$order->order_number, $fo->fulfilment_order_number]), [
                'action' => 'change_address',
                'new_phone' => '9988776655',
                'remarks' => 'Please leave with security if doorbell not answered.',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('ndr_cases', [
            'shipment_id' => $shipment->id,
            'customer_action' => 'change_address',
            'status' => 'reattempt_scheduled',
        ]);
    }

    public function test_webhook_rto_delivered_restocks_inventory(): void
    {
        [$order, $fo, $item] = $this->createStandardOrder();
        $initialStock = $this->inventory->quantity_on_hand;

        $shipment = Shipment::create([
            'fulfilment_order_id' => $fo->id,
            'shipment_number' => 'SHP-RTO-01',
            'provider' => 'onestall_cargo',
            'awb' => 'OSC-RTO-999',
            'courier' => 'OneStall Express',
            'status' => ShipmentStatus::RTO_INITIATED,
        ]);

        RtoCase::create([
            'shipment_id' => $shipment->id,
            'reason' => 'Repeated delivery failures',
            'status' => 'initiated',
            'initiated_at' => now(),
        ]);

        $payload = [
            'event' => 'shipment.rto_delivered',
            'data' => [
                'awb' => 'OSC-RTO-999',
                'location' => 'Origin Hub, Indiranagar',
                'timestamp' => now()->toIso8601String(),
            ],
        ];

        $raw = json_encode($payload);
        $sig = hash_hmac('sha256', $raw, $this->cargoClient->getWebhookSecret());

        $this->call('POST', '/api/v1/cargo/webhook', [], [], [], [
            'HTTP_X-Cargo-Signature' => $sig,
            'CONTENT_TYPE' => 'application/json',
        ], $raw)->assertOk();

        $shipment->refresh();
        $this->assertEquals(ShipmentStatus::RTO_DELIVERED, $shipment->status);

        $this->inventory->refresh();
        $this->assertEquals($initialStock + 1, $this->inventory->quantity_on_hand);
    }

    public function test_seller_shipments_console_views_and_label_downloads(): void
    {
        [$order, $fo, $item] = $this->createStandardOrder();
        $shipment = Shipment::create([
            'fulfilment_order_id' => $fo->id,
            'shipment_number' => 'SHP-UI-01',
            'provider' => 'onestall_cargo',
            'awb' => 'OSC-UI-001',
            'courier' => 'OneStall Express',
            'status' => ShipmentStatus::IN_TRANSIT,
            'weight' => 1.2,
        ]);

        // 1. Index
        $indexRes = $this->actingAs($this->sellerUser)
            ->get(route('seller.shipments.index'));
        $indexRes->assertOk()
            ->assertViewIs('seller.shipments.index')
            ->assertSee('OSC-UI-001');

        // 2. Show
        $showRes = $this->actingAs($this->sellerUser)
            ->get(route('seller.shipments.show', $shipment));
        $showRes->assertOk()
            ->assertViewIs('seller.shipments.show')
            ->assertSee('OSC-UI-001');

        // 3. Single Label Print
        $labelRes = $this->actingAs($this->sellerUser)
            ->get(route('seller.shipments.label', $shipment));
        $labelRes->assertOk()
            ->assertSee('ONESTALL CARGO')
            ->assertSee('OSC-UI-001');

        // 4. Bulk Labels
        $bulkRes = $this->actingAs($this->sellerUser)
            ->post(route('seller.shipments.bulk_labels'), [
                'shipment_ids' => [$shipment->id],
            ]);
        $bulkRes->assertOk()
            ->assertSee('Bulk Shipping Labels');

        // 5. CSV Export
        $csvRes = $this->actingAs($this->sellerUser)
            ->get(route('seller.shipments.export'));
        $csvRes->assertOk();
        $this->assertEquals('text/csv; charset=utf-8', $csvRes->headers->get('content-type'));
    }

    public function test_admin_shipping_overview_and_failed_queue_retry(): void
    {
        [$order, $fo, $item] = $this->createStandardOrder();
        $failedShp = Shipment::create([
            'fulfilment_order_id' => $fo->id,
            'shipment_number' => 'SHP-FAIL-01',
            'provider' => 'onestall_cargo',
            'awb' => null, // missing AWB
            'courier' => 'OneStall Express',
            'status' => ShipmentStatus::FAILED,
        ]);

        // 1. Admin Shipping Overview
        $adminIndex = $this->actingAs($this->adminUser)
            ->get(route('admin.shipping.index'));
        $adminIndex->assertOk()
            ->assertViewIs('admin.shipping.index')
            ->assertSee('OneStall Cargo Platform Credentials');

        // 2. Failed Queue View
        $queueRes = $this->actingAs($this->adminUser)
            ->get(route('admin.shipping.failed_queue'));
        $queueRes->assertOk()
            ->assertViewIs('admin.shipping.failed-queue')
            ->assertSee('SHP-FAIL-01');

        // 3. Retry Consignment
        $retryRes = $this->actingAs($this->adminUser)
            ->post(route('admin.shipping.retry_shipment', $failedShp));
        $retryRes->assertRedirect();

        $failedShp->refresh();
        $this->assertNotNull($failedShp->awb);
        $this->assertEquals(ShipmentStatus::MANIFEST_CREATED, $failedShp->status);

        // 4. Update Admin Shipping & Cargo Settings
        $updateRes = $this->actingAs($this->adminUser)
            ->post(route('admin.shipping.update'), [
                'default_weight_kg' => 0.75,
                'cod_max_limit' => 4500.00,
                'free_shipping_threshold' => 799.00,
                'quick_max_radius_km' => 10.0,
                'standard_base_fee' => 35.00,
                'rto_max_attempts' => 2,
                'courier_priorities' => ['onestall_cargo', 'delhivery', 'bluedart', 'shadowfax'],
                'cargo_fake' => 1,
            ]);
        $updateRes->assertRedirect()
            ->assertSessionHas('success');
    }
}
