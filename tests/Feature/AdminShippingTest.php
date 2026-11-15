<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Jobs\Cargo\TrackingSyncJob;
use App\Models\FulfilmentOrder;
use App\Models\Order;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Cargo\Contracts\LogisticsProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminShippingTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $customerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $this->adminUser = User::factory()->create(['name' => 'Admin Logistics', 'email' => 'logistics@onestall.test']);
        $this->adminUser->assignRole($adminRole);

        $this->customerUser = User::factory()->create(['name' => 'Regular Customer', 'email' => 'customer@onestall.test']);
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $response = $this->get(route('admin.shipping.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_non_admin_customer_is_forbidden(): void
    {
        $response = $this->actingAs($this->customerUser)->get(route('admin.shipping.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_view_shipping_overview_with_metrics_and_consignments(): void
    {
        $seller = Seller::factory()->create(['business_name' => 'Prime Electronics Ltd']);
        $order = Order::factory()->create(['order_number' => 'ORD-SHP-001']);
        $fo = FulfilmentOrder::factory()->create([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'fulfilment_order_number' => 'FO-SHP-001',
        ]);

        $shipment = Shipment::create([
            'fulfilment_order_id' => $fo->id,
            'shipment_number' => 'SHP-99101',
            'provider' => 'onestall_cargo',
            'awb' => 'AWB99101001',
            'courier' => 'Delhivery Express',
            'service' => 'Surface',
            'status' => ShipmentStatus::IN_TRANSIT,
            'weight' => 1.25,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.shipping.index'));
        $response->assertStatus(200);
        $response->assertSee('Logistics &amp; OneStall Cargo Oversight', false);
        $response->assertSee('Courier Fallback Priority Chain');
        $response->assertSee('Recent Cargo Consignments');
        $response->assertSee('SHP-99101');
        $response->assertSee('ORD-SHP-001');
        $response->assertSee('Prime Electronics Ltd');
        $response->assertSee('Delhivery Express');
    }

    public function test_admin_can_update_shipping_and_cargo_configurations(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.shipping.update'), [
            'default_weight_kg' => 0.85,
            'cod_max_limit' => 4000.00,
            'free_shipping_threshold' => 699.00,
            'quick_max_radius_km' => 7.5,
            'standard_base_fee' => 45.00,
            'rto_max_attempts' => 2,
            'courier_priorities' => ['delhivery', 'onestall_cargo', 'bluedart', 'shadowfax'],
            'cargo_base_url' => 'https://api.onestallcargo.internal/v2',
            'cargo_api_key' => 'live_api_key_123',
            'cargo_api_secret' => 'live_secret_456',
            'cargo_webhook_secret' => 'live_wh_secret_789',
            'cargo_fake' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals(0.85, (float) Setting::get('shipping_default_weight_kg'));
        $this->assertEquals(4000.00, (float) Setting::get('shipping_cod_max_limit'));
        $this->assertEquals(699.00, (float) Setting::get('shipping_free_threshold'));
        $this->assertEquals(7.5, (float) Setting::get('shipping_quick_max_radius_km'));
        $this->assertEquals(45.00, (float) Setting::get('shipping_standard_base_fee'));
        $this->assertEquals(2, (int) Setting::get('shipping_rto_max_attempts'));
        $this->assertEquals(['delhivery', 'onestall_cargo', 'bluedart', 'shadowfax'], Setting::get('shipping_courier_priorities'));

        // Cross-sync check
        $this->assertEquals(699.00, (float) Setting::get('free_shipping_threshold'));
        $this->assertEquals(699.00, (float) Setting::get('free_delivery_threshold'));
        $this->assertEquals(45.00, (float) Setting::get('standard_delivery_fee'));
        $this->assertEquals(45.00, (float) Setting::get('base_delivery_fee'));
        $this->assertEquals(4000.00, (float) Setting::get('cod_max_limit_amount'));
    }

    public function test_validation_fails_on_invalid_shipping_inputs(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.shipping.update'), [
            'default_weight_kg' => -5,
            'cod_max_limit' => -100,
            'free_shipping_threshold' => -50,
            'quick_max_radius_km' => 0,
            'standard_base_fee' => -10,
            'rto_max_attempts' => 9, // max is 5
            'courier_priorities' => 'not-an-array',
        ]);

        $response->assertSessionHasErrors([
            'default_weight_kg',
            'cod_max_limit',
            'free_shipping_threshold',
            'quick_max_radius_km',
            'standard_base_fee',
            'rto_max_attempts',
            'courier_priorities',
        ]);
    }

    public function test_admin_can_dispatch_bulk_tracking_sync_job(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->adminUser)->post(route('admin.shipping.sync_all'));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        Queue::assertPushed(TrackingSyncJob::class);
    }

    public function test_admin_can_view_failed_shipment_queue(): void
    {
        $seller = Seller::factory()->create(['business_name' => 'FastTrack Store']);
        $order = Order::factory()->create(['order_number' => 'ORD-FAIL-01']);
        $fo = FulfilmentOrder::factory()->create([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
        ]);

        $failedShipment = Shipment::create([
            'fulfilment_order_id' => $fo->id,
            'shipment_number' => 'SHP-FAIL-001',
            'provider' => 'onestall_cargo',
            'awb' => null,
            'status' => ShipmentStatus::FAILED,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.shipping.failed_queue'));
        $response->assertStatus(200);
        $response->assertSee('Failed Shipments Queue');
        $response->assertSee('SHP-FAIL-001');
        $response->assertSee('ORD-FAIL-01');
        $response->assertSee('FastTrack Store');
        $response->assertSee('Retry Booking');
    }

    public function test_admin_can_retry_failed_shipment(): void
    {
        $seller = Seller::factory()->create();
        $order = Order::factory()->create();
        $fo = FulfilmentOrder::factory()->create([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
        ]);

        $shipment = Shipment::create([
            'fulfilment_order_id' => $fo->id,
            'shipment_number' => 'SHP-RETRY-01',
            'provider' => 'onestall_cargo',
            'courier' => 'Delhivery Express',
            'awb' => null,
            'status' => ShipmentStatus::FAILED,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('admin.shipping.retry_shipment', $shipment));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $fresh = $shipment->fresh();
        $this->assertEquals(ShipmentStatus::MANIFEST_CREATED, $fresh->status);
        $this->assertNotNull($fresh->awb);
    }
}
