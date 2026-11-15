<?php

namespace Tests\Feature;

use App\Models\DeliveryAssignment;
use App\Models\DeliveryPartner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDeliveryPartnersTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create([
            'email' => 'admin_fleet_' . uniqid() . '@example.com',
            'status' => 'active',
        ]);
        $this->admin->assignRole('super_admin');
    }

    public function test_admin_can_view_delivery_partners_list_with_metrics(): void
    {
        DeliveryPartner::create([
            'name' => 'Ravi Kumar',
            'phone' => '+919876500001',
            'vehicle' => 'Electric Scooter',
            'vehicle_number' => 'DL-01-EV-1001',
            'status' => 'available',
            'is_active' => true,
        ]);

        DeliveryPartner::create([
            'name' => 'Suresh Verma',
            'phone' => '+919876500002',
            'vehicle' => 'Motorcycle',
            'vehicle_number' => 'DL-01-MC-2002',
            'status' => 'busy',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.delivery_partners.index'));

        $response->assertOk();
        $response->assertSee('Delivery Partners Fleet Management');
        $response->assertSee('Ravi Kumar');
        $response->assertSee('Suresh Verma');
        $response->assertSee('DL-01-EV-1001');
        $response->assertSee('DL-01-MC-2002');
    }

    public function test_admin_can_filter_delivery_partners_by_search_and_status(): void
    {
        $rider1 = DeliveryPartner::create([
            'name' => 'Amit Sharma',
            'phone' => '+919876500011',
            'vehicle' => 'Electric Scooter',
            'vehicle_number' => 'DL-05-EV-9999',
            'status' => 'available',
            'is_active' => true,
        ]);

        $rider2 = DeliveryPartner::create([
            'name' => 'Deepak Patel',
            'phone' => '+919876500022',
            'vehicle' => 'Motorcycle',
            'vehicle_number' => 'MH-02-MC-1111',
            'status' => 'offline',
            'is_active' => true,
        ]);

        // Search by name
        $response = $this->actingAs($this->admin)->get(route('admin.delivery_partners.index', ['search' => 'Amit']));
        $response->assertOk();
        $response->assertSee('Amit Sharma');
        $response->assertDontSee('Deepak Patel');

        // Filter by status
        $response = $this->actingAs($this->admin)->get(route('admin.delivery_partners.index', ['status' => 'offline']));
        $response->assertOk();
        $response->assertSee('Deepak Patel');
        $response->assertDontSee('Amit Sharma');
    }

    public function test_admin_can_store_new_delivery_partner(): void
    {
        $data = [
            'name' => 'Vikram Singh',
            'phone' => '+919811223344',
            'vehicle' => 'Electric Scooter',
            'vehicle_number' => 'DL-03-EV-4545',
            'status' => 'available',
            'current_lat' => 28.6139,
            'current_lng' => 77.2090,
            'is_active' => 1,
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.delivery_partners.store'), $data);

        $response->assertRedirect(route('admin.delivery_partners.index'));
        $this->assertDatabaseHas('delivery_partners', [
            'name' => 'Vikram Singh',
            'phone' => '+919811223344',
            'vehicle_number' => 'DL-03-EV-4545',
        ]);
    }

    public function test_admin_can_update_delivery_partner(): void
    {
        $rider = DeliveryPartner::create([
            'name' => 'Original Name',
            'phone' => '+919000000001',
            'vehicle' => 'Motorcycle',
            'vehicle_number' => 'DL-01-AB-1234',
            'status' => 'offline',
            'is_active' => true,
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'phone' => '+919000000001', // same phone should pass unique check
            'vehicle' => 'Electric Scooter',
            'vehicle_number' => 'DL-01-AB-9999',
            'status' => 'available',
            'current_lat' => 28.5355,
            'current_lng' => 77.3910,
            'is_active' => 1,
        ];

        $response = $this->actingAs($this->admin)->put(route('admin.delivery_partners.update', $rider), $updateData);

        $response->assertRedirect(route('admin.delivery_partners.index'));
        $this->assertDatabaseHas('delivery_partners', [
            'id' => $rider->id,
            'name' => 'Updated Name',
            'vehicle' => 'Electric Scooter',
            'vehicle_number' => 'DL-01-AB-9999',
            'status' => 'available',
        ]);
    }

    public function test_admin_can_toggle_delivery_partner_duty_status(): void
    {
        $rider = DeliveryPartner::create([
            'name' => 'Toggle Rider',
            'phone' => '+919888877771',
            'vehicle' => 'Motorcycle',
            'vehicle_number' => 'DL-04-TG-1234',
            'status' => 'available',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.delivery_partners.toggle', $rider), [
            'status' => 'offline',
        ]);

        $response->assertRedirect();
        $this->assertEquals('offline', $rider->fresh()->status);
    }

    public function test_admin_cannot_delete_rider_with_active_assignments(): void
    {
        $rider = DeliveryPartner::create([
            'name' => 'Busy Rider',
            'phone' => '+919555544441',
            'vehicle' => 'Electric Scooter',
            'vehicle_number' => 'DL-09-EV-8888',
            'status' => 'busy',
            'active_orders_count' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.delivery_partners.destroy', $rider));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('delivery_partners', ['id' => $rider->id]);
    }

    public function test_admin_can_delete_idle_rider(): void
    {
        $rider = DeliveryPartner::create([
            'name' => 'Idle Rider',
            'phone' => '+919666655551',
            'vehicle' => 'Electric Scooter',
            'vehicle_number' => 'DL-09-EV-7777',
            'status' => 'offline',
            'active_orders_count' => 0,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.delivery_partners.destroy', $rider));

        $response->assertRedirect(route('admin.delivery_partners.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('delivery_partners', ['id' => $rider->id]);
    }

    public function test_admin_can_export_delivery_partners_csv(): void
    {
        DeliveryPartner::create([
            'name' => 'Rider CSV Export',
            'phone' => '+919777766661',
            'vehicle' => 'Electric Scooter',
            'vehicle_number' => 'DL-99-CSV-1234',
            'status' => 'available',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.delivery_partners.export'));

        $response->assertOk();
        $this->assertTrue(str_contains((string) $response->headers->get('content-type'), 'text/csv'));
        $this->assertStringContainsString('Rider CSV Export', $response->streamedContent());
        $this->assertStringContainsString('DL-99-CSV-1234', $response->streamedContent());
    }
}
