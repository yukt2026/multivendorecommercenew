<?php

namespace Tests\Feature;

use App\Models\Setting;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    /**
     * Test storefront home page.
     */
    public function test_storefront_home_page_is_accessible(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('OneStall');
    }

    /**
     * Test admin dashboard page.
     */
    public function test_admin_dashboard_is_accessible(): void
    {
        $admin = \App\Models\User::factory()->create();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get('/admin');
        $response->assertStatus(200);
        $response->assertSee('Central Operations', false);
    }

    /**
     * Test admin settings page.
     */
    public function test_admin_settings_is_accessible(): void
    {
        $admin = \App\Models\User::factory()->create();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get('/admin/settings');
        $response->assertStatus(200);
        $response->assertSee('Platform Settings', false);
    }

    /**
     * Test admin UI kit showcase page.
     */
    public function test_admin_ui_kit_is_accessible(): void
    {
        $admin = \App\Models\User::factory()->create();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get('/admin/ui-kit');
        $response->assertStatus(200);
        $response->assertSee('OneStall Design System', false);
    }

    /**
     * Test seller dashboard page.
     */
    public function test_seller_dashboard_is_accessible(): void
    {
        $sellerUser = \App\Models\User::factory()->create();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'seller_owner', 'guard_name' => 'web']);
        $sellerUser->assignRole('seller_owner');

        $seller = \App\Models\Seller::factory()->create([
            'user_id' => $sellerUser->id,
            'status' => \App\Enums\SellerStatus::APPROVED,
        ]);

        $response = $this->actingAs($sellerUser)->get('/seller');
        $response->assertStatus(200);
        $response->assertSee('Merchant Operations Hub');
    }

    /**
     * Test API v1 config endpoint.
     */
    public function test_api_v1_config_endpoint(): void
    {
        $response = $this->getJson('/api/v1/config');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'platform_name',
                'currency',
                'currency_symbol',
                'quick_commerce_radius_km',
                'free_delivery_threshold',
                'base_delivery_fee',
            ],
        ]);
    }

    /**
     * Test setting helper and caching mechanism.
     */
    public function test_setting_helper_and_cache(): void
    {
        Setting::set('test_dynamic_key', 'dynamic_value');
        $this->assertEquals('dynamic_value', setting('test_dynamic_key'));

        Setting::set('test_dynamic_key', 'updated_value');
        $this->assertEquals('updated_value', setting('test_dynamic_key'));
    }
}
