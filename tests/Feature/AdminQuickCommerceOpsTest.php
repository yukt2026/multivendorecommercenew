<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StoreStatus;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\FulfilmentOrder;
use App\Models\InventoryLocation;
use App\Models\Order;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminQuickCommerceOpsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $customerUser;
    protected Customer $customer;
    protected Seller $seller;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->adminUser = User::factory()->create([
            'email' => 'admin.ops@onestall.in',
            'name' => 'Quick Commerce Mission Director',
        ]);
        $this->adminUser->assignRole('super_admin');

        $this->customerUser = User::factory()->create();
        $this->customer = Customer::create(['user_id' => $this->customerUser->id]);
        $this->seller = Seller::factory()->create();

        $this->store = Store::create([
            'seller_id' => $this->seller->id,
            'name' => 'Koramangala DarkStore Hub',
            'slug' => 'koramangala-darkstore',
            'status' => StoreStatus::ACTIVE,
            'accepts_quick_commerce' => true,
            'quick_radius_km' => 8.0,
            'quick_max_orders' => 20,
            'quick_prep_time_minutes' => 7,
            'quick_order_timeout_seconds' => 120,
        ]);

        InventoryLocation::create([
            'store_id' => $this->store->id,
            'seller_id' => $this->seller->id,
            'name' => 'Koramangala Hub Shelf 1',
            'code' => 'KOR-01',
            'type' => 'store',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_view_quick_commerce_mission_control(): void
    {
        $order = Order::create([
            'order_number' => 'OS-QC-LIVE-1',
            'customer_id' => $this->customer->id,
            'address_snapshot' => ['city' => 'Bengaluru', 'line1' => '12th Main', 'lat' => 12.9352, 'lng' => 77.6245],
            'subtotal' => 200,
            'delivery_fee' => 35,
            'total' => 235,
            'payment_method' => PaymentMethod::UPI,
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::PROCESSING,
        ]);

        $fo = FulfilmentOrder::create([
            'order_id' => $order->id,
            'fulfilment_order_number' => 'OS-QC-LIVE-1-FO1',
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'placed',
            'subtotal' => 200,
            'delivery_fee' => 35,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.quick.index'));

        $response->assertStatus(200);
        $response->assertSee('Quick Commerce Mission Control');
        $response->assertSee('OS-QC-LIVE-1-FO1');
        $response->assertSee('Koramangala DarkStore Hub');
        $response->assertSee('Hyperlocal Telemetry Live');
    }

    public function test_api_feed_returns_json_telemetry(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson(route('admin.quick.api_feed'));

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');
        $response->assertJsonStructure([
            'status',
            'data' => [
                'active_orders',
                'active_orders_count',
                'breached_orders',
                'breached_count',
                'stores_load',
                'riders_summary',
                'today_total',
                'today_delivered',
            ],
        ]);
    }

    public function test_admin_can_trigger_timeout_scan(): void
    {
        $order = Order::create([
            'order_number' => 'OS-TIMEOUT-TEST',
            'customer_id' => $this->customer->id,
            'address_snapshot' => ['city' => 'Bengaluru', 'line1' => '12th Main', 'lat' => 12.9352, 'lng' => 77.6245],
            'subtotal' => 150,
            'total' => 180,
            'payment_method' => PaymentMethod::UPI,
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::PROCESSING,
        ]);

        $fo = FulfilmentOrder::create([
            'order_id' => $order->id,
            'fulfilment_order_number' => 'OS-TIMEOUT-TEST-FO1',
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'placed',
            'subtotal' => 150,
        ]);

        // Fast-forward created_at by 300 seconds (breached timeout)
        DB::table('fulfilment_orders')->where('id', $fo->id)->update(['created_at' => now()->subSeconds(300)]);

        $response = $this->actingAs($this->adminUser)->post(route('admin.quick.scan_timeouts'));
        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_admin_can_trigger_reroute_on_fulfilment_order(): void
    {
        $order = Order::create([
            'order_number' => 'OS-REROUTE-TEST',
            'customer_id' => $this->customer->id,
            'address_snapshot' => ['city' => 'Bengaluru', 'line1' => '12th Main', 'lat' => 12.9352, 'lng' => 77.6245],
            'subtotal' => 120,
            'total' => 150,
            'payment_method' => PaymentMethod::UPI,
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::PROCESSING,
        ]);

        $fo = FulfilmentOrder::create([
            'order_id' => $order->id,
            'fulfilment_order_number' => 'OS-REROUTE-TEST-FO1',
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'placed',
            'subtotal' => 120,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('admin.quick.reroute', $fo));
        $response->assertRedirect();
    }
}
