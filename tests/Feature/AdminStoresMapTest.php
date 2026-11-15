<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SellerStatus;
use App\Enums\StoreStatus;
use App\Models\Seller;
use App\Models\Store;
use App\Models\StoreLocation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStoresMapTest extends TestCase
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

        // Normal Customer without admin privileges
        $this->customerUser = User::factory()->create([
            'email' => 'customer@onestall.in',
            'name' => 'John Customer',
        ]);
        $this->customerUser->assignRole('customer');
    }

    public function test_admin_can_view_interactive_store_map_with_geodata_and_metrics(): void
    {
        // 1. Create Seller & Geocoded Store 1 (Quick commerce store in Bengaluru)
        $seller1 = Seller::factory()->create([
            'business_name' => 'Bengaluru Express Retail',
            'status' => SellerStatus::APPROVED,
        ]);
        $store1 = Store::factory()->create([
            'seller_id' => $seller1->id,
            'name' => 'Bengaluru SuperMart Koramangala',
            'status' => StoreStatus::ACTIVE,
            'accepts_quick_commerce' => true,
            'delivery_radius_km' => 6.0,
        ]);
        StoreLocation::create([
            'store_id' => $store1->id,
            'address' => '100 Feet Road, 4th Block, Koramangala',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560034',
            'lat' => 12.9345,
            'lng' => 77.6200,
        ]);

        // 2. Create Seller & Geocoded Store 2 (Standard store in Delhi)
        $seller2 = Seller::factory()->create([
            'business_name' => 'Delhi Electronics Hub',
            'status' => SellerStatus::APPROVED,
        ]);
        $store2 = Store::factory()->create([
            'seller_id' => $seller2->id,
            'name' => 'Delhi Gadgets Connaught Place',
            'status' => StoreStatus::ACTIVE,
            'accepts_quick_commerce' => false,
            'delivery_radius_km' => 10.0,
        ]);
        StoreLocation::create([
            'store_id' => $store2->id,
            'address' => 'Block B, Inner Circle, CP',
            'city' => 'Delhi',
            'state' => 'Delhi',
            'pincode' => '110001',
            'lat' => 28.6315,
            'lng' => 77.2167,
        ]);

        // 3. Create Store 3 with MISSING lat/lng to test unmapped detection
        $seller3 = Seller::factory()->create([
            'business_name' => 'Unmapped Store Retail',
            'status' => SellerStatus::APPROVED,
        ]);
        $store3 = Store::factory()->create([
            'seller_id' => $seller3->id,
            'name' => 'Unmapped Store Outlet',
            'status' => StoreStatus::ACTIVE,
        ]);

        $response = $this->actingAs($this->adminUser)->get('/admin/stores/map');

        $response->assertStatus(200);
        $response->assertSee('Interactive Store Map &amp; Geo-Coverage', false);
        $response->assertSee('Bengaluru SuperMart Koramangala');
        $response->assertSee('Delhi Gadgets Connaught Place');
        $response->assertSee('2 Geocoded Store Outlets');
        $response->assertSee('Quick Commerce (10-30m)');
        $response->assertSee('City Coverage Reach');

        // Check metrics passed to the view
        $response->assertViewHas('metrics', function ($metrics) {
            return $metrics['total_geocoded'] === 2 &&
                   $metrics['quick_commerce'] === 1 &&
                   $metrics['standard'] === 1 &&
                   $metrics['unmapped'] === 1 &&
                   $metrics['cities_count'] === 2;
        });

        // Check mapData payload structure
        $response->assertViewHas('mapData', function ($mapData) use ($store1, $store2) {
            $ids = $mapData->pluck('id')->toArray();
            return in_array($store1->id, $ids, true) &&
                   in_array($store2->id, $ids, true) &&
                   count($ids) === 2;
        });

        // Check CSP header allows unpkg.com for Leaflet map
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString('https://unpkg.com', $csp);
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $response = $this->get('/admin/stores/map');
        $response->assertRedirect('/admin/login');
    }

    public function test_unauthorized_user_cannot_access_store_map(): void
    {
        $response = $this->actingAs($this->customerUser)->get('/admin/stores/map');
        $response->assertStatus(403);
    }
}
