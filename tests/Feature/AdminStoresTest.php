<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SellerStatus;
use App\Enums\StoreStatus;
use App\Models\Seller;
use App\Models\Store;
use App\Models\StoreLocation;
use App\Models\StoreTiming;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStoresTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $customerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // Super Admin / Operations Director
        $this->adminUser = User::factory()->create([
            'email' => 'admin.ops@onestall.in',
            'name' => 'Operations Director',
        ]);
        $this->adminUser->assignRole('admin');

        // Customer User
        $this->customerUser = User::factory()->create([
            'email' => 'customer@onestall.in',
            'name' => 'Customer User',
        ]);
        $this->customerUser->assignRole('customer');
    }

    public function test_admin_can_view_stores_list_with_metrics_and_search(): void
    {
        $seller = Seller::factory()->create(['business_name' => 'Koramangala SuperStore Retail']);
        $store = Store::factory()->create([
            'seller_id' => $seller->id,
            'name' => 'Koramangala 4th Block Hub',
            'status' => StoreStatus::ACTIVE,
            'accepts_quick_commerce' => true,
        ]);
        StoreLocation::create([
            'store_id' => $store->id,
            'address' => '80ft Road, 4th Block',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560034',
            'lat' => 12.9345,
            'lng' => 77.6200,
        ]);

        $response = $this->actingAs($this->adminUser)->get('/admin/stores');

        $response->assertStatus(200);
        $response->assertSee('Physical &amp; Dark Stores Network', false);
        $response->assertSee('Koramangala 4th Block Hub');
        $response->assertSee('Koramangala SuperStore Retail');
        $response->assertSee('Active &amp; Live', false);
        $response->assertSee('Quick Commerce (10-30m)');

        // Test search filter
        $searchResponse = $this->actingAs($this->adminUser)->get('/admin/stores?search=Koramangala');
        $searchResponse->assertStatus(200);
        $searchResponse->assertSee('Koramangala 4th Block Hub');

        // Test non-matching search
        $emptyResponse = $this->actingAs($this->adminUser)->get('/admin/stores?search=NonExistentStoreXYZ');
        $emptyResponse->assertStatus(200);
        $emptyResponse->assertSee('No store outlets match criteria');
    }

    public function test_admin_can_filter_stores_by_status_and_fulfillment_mode(): void
    {
        $seller = Seller::factory()->create();
        $storeActiveQuick = Store::factory()->create([
            'seller_id' => $seller->id,
            'name' => 'Active Quick Store',
            'status' => StoreStatus::ACTIVE,
            'accepts_quick_commerce' => true,
        ]);
        $storeSuspendedStandard = Store::factory()->create([
            'seller_id' => $seller->id,
            'name' => 'Suspended Standard Store',
            'status' => StoreStatus::SUSPENDED,
            'accepts_quick_commerce' => false,
        ]);

        // Filter by active status
        $resActive = $this->actingAs($this->adminUser)->get('/admin/stores?status=active');
        $resActive->assertStatus(200);
        $resActive->assertSee('Active Quick Store');
        $resActive->assertDontSee('Suspended Standard Store');

        // Filter by suspended status
        $resSuspended = $this->actingAs($this->adminUser)->get('/admin/stores?status=suspended');
        $resSuspended->assertStatus(200);
        $resSuspended->assertSee('Suspended Standard Store');
        $resSuspended->assertDontSee('Active Quick Store');

        // Filter by quick commerce only
        $resQuick = $this->actingAs($this->adminUser)->get('/admin/stores?quick_only=1');
        $resQuick->assertStatus(200);
        $resQuick->assertSee('Active Quick Store');
        $resQuick->assertDontSee('Suspended Standard Store');
    }

    public function test_admin_can_view_store_console_with_details_and_timings(): void
    {
        $seller = Seller::factory()->create([
            'business_name' => 'Metro Retail Innovations',
            'gstin' => '29AAAAA0000A1Z5',
            'pan' => 'AAAAA0000A',
        ]);
        $store = Store::factory()->create([
            'seller_id' => $seller->id,
            'name' => 'Indiranagar Flagship Outlet',
            'slug' => 'indiranagar-flagship',
            'status' => StoreStatus::ACTIVE,
            'delivery_radius_km' => 7.5,
            'accepts_quick_commerce' => true,
        ]);
        StoreLocation::create([
            'store_id' => $store->id,
            'address' => '100ft road Indiranagar',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560038',
            'lat' => 12.9784,
            'lng' => 77.6408,
        ]);

        StoreTiming::create([
            'store_id' => $store->id,
            'day_of_week' => 1, // Monday
            'opens_at' => '08:00:00',
            'closes_at' => '22:00:00',
            'is_closed' => false,
        ]);

        $response = $this->actingAs($this->adminUser)->get("/admin/stores/{$store->id}");

        $response->assertStatus(200);
        $response->assertSee('Indiranagar Flagship Outlet');
        $response->assertSee('Metro Retail Innovations');
        $response->assertSee('7.5 km');
        $response->assertSee('100ft road Indiranagar');
        $response->assertSee('Bengaluru');
        $response->assertSee('560038');
        $response->assertSee('08:00 - 22:00');
    }

    public function test_admin_can_approve_and_suspend_store(): void
    {
        $seller = Seller::factory()->create();
        $store = Store::factory()->create([
            'seller_id' => $seller->id,
            'status' => StoreStatus::SUSPENDED,
        ]);

        // Activate store
        $approveResponse = $this->actingAs($this->adminUser)->post("/admin/stores/{$store->id}/approve");
        $approveResponse->assertRedirect();
        $this->assertEquals(StoreStatus::ACTIVE, $store->fresh()->status);

        // Suspend store with reason
        $suspendResponse = $this->actingAs($this->adminUser)->post("/admin/stores/{$store->id}/suspend", [
            'reason' => 'Store hygiene audit inspection required',
        ]);
        $suspendResponse->assertRedirect();
        $this->assertEquals(StoreStatus::SUSPENDED, $store->fresh()->status);
    }

    public function test_admin_can_toggle_quick_commerce_capability(): void
    {
        $seller = Seller::factory()->create();
        $store = Store::factory()->create([
            'seller_id' => $seller->id,
            'accepts_quick_commerce' => false,
        ]);

        // Enable Quick Commerce
        $toggleOn = $this->actingAs($this->adminUser)->post("/admin/stores/{$store->id}/toggle-quick");
        $toggleOn->assertRedirect();
        $this->assertTrue($store->fresh()->accepts_quick_commerce);

        // Disable Quick Commerce
        $toggleOff = $this->actingAs($this->adminUser)->post("/admin/stores/{$store->id}/toggle-quick");
        $toggleOff->assertRedirect();
        $this->assertFalse($store->fresh()->accepts_quick_commerce);
    }

    public function test_admin_can_update_delivery_radius(): void
    {
        $seller = Seller::factory()->create();
        $store = Store::factory()->create([
            'seller_id' => $seller->id,
            'delivery_radius_km' => 5.0,
        ]);

        $response = $this->actingAs($this->adminUser)->post("/admin/stores/{$store->id}/update-radius", [
            'delivery_radius_km' => 12.5,
        ]);

        $response->assertRedirect();
        $this->assertEquals(12.5, $store->fresh()->delivery_radius_km);

        // Validation: must be valid numeric
        $failResponse = $this->actingAs($this->adminUser)->post("/admin/stores/{$store->id}/update-radius", [
            'delivery_radius_km' => -5,
        ]);
        $failResponse->assertSessionHasErrors('delivery_radius_km');
    }

    public function test_admin_can_export_stores_to_csv(): void
    {
        $seller = Seller::factory()->create(['business_name' => 'CSV Exportable Mart']);
        $store = Store::factory()->create([
            'seller_id' => $seller->id,
            'name' => 'Exportable Store Outlet',
        ]);

        $response = $this->actingAs($this->adminUser)->get('/admin/stores/export');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="stores-', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $response = $this->get('/admin/stores');
        $response->assertRedirect('/admin/login');
    }

    public function test_unauthorized_user_cannot_access_stores_module(): void
    {
        $response = $this->actingAs($this->customerUser)->get('/admin/stores');
        $response->assertStatus(403);
    }
}
