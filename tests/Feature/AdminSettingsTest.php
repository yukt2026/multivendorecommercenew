<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $customerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $this->adminUser = User::factory()->create(['name' => 'Admin User', 'email' => 'admin@onestall.test']);
        $this->adminUser->assignRole($adminRole);

        $this->customerUser = User::factory()->create(['name' => 'Customer Joe', 'email' => 'joe@customer.test']);
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $response = $this->get(route('admin.settings.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_non_admin_customer_is_forbidden(): void
    {
        $response = $this->actingAs($this->customerUser)->get(route('admin.settings.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_view_settings_index_and_ensures_essential_defaults(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.settings.index'));
        $response->assertStatus(200);
        $response->assertSee('Global Platform Settings');
        $response->assertSee('General &amp; Platform Info', false);
        $response->assertSee('Payment Gateways &amp; COD', false);
        $response->assertSee('SEO &amp; Meta Tags', false);
        $response->assertSee('Delivery Radius &amp; Thresholds', false);

        // Core defaults should now exist in the database
        $this->assertDatabaseHas('settings', ['key' => 'name', 'group' => 'general']);
        $this->assertDatabaseHas('settings', ['key' => 'cod_allowed', 'group' => 'payment']);
        $this->assertDatabaseHas('settings', ['key' => 'meta_title', 'group' => 'seo']);
        $this->assertDatabaseHas('settings', ['key' => 'standard_delivery_fee', 'group' => 'delivery']);
    }

    public function test_admin_can_switch_tabs_and_view_group_settings(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.settings.index', ['tab' => 'payment']));
        $response->assertStatus(200);
        $response->assertSee('Enable Cash On Delivery (COD)');
        $response->assertSee('Razorpay Key ID');

        $seoResponse = $this->actingAs($this->adminUser)->get(route('admin.settings.index', ['tab' => 'seo']));
        $seoResponse->assertStatus(200);
        $seoResponse->assertSee('Default SEO Meta Title');
        $seoResponse->assertSee('Default Meta Description');
    }

    public function test_admin_can_update_settings_and_preserves_tab(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.settings.update'), [
            'tab' => 'payment',
            'settings' => [
                'cod_allowed' => '1',
                'cod_max_limit_amount' => '7500',
            ],
        ]);

        $response->assertRedirect(route('admin.settings.index', ['tab' => 'payment']));
        $response->assertSessionHas('success');

        $this->assertEquals(7500, (float) Setting::get('cod_max_limit_amount'));
    }

    public function test_updating_setting_preserves_its_original_group_and_label(): void
    {
        // First ensure payment setting exists
        $this->actingAs($this->adminUser)->get(route('admin.settings.index'));

        $codSetting = Setting::where('key', 'cod_allowed')->first();
        $this->assertNotNull($codSetting);
        $this->assertEquals('payment', $codSetting->group);
        $this->assertEquals('Enable Cash On Delivery (COD)', $codSetting->label);
        $this->assertEquals('boolean', $codSetting->type);

        // Update value via form POST
        $this->actingAs($this->adminUser)->post(route('admin.settings.update'), [
            'tab' => 'payment',
            'settings' => [
                'cod_allowed' => '0',
            ],
        ]);

        $fresh = Setting::where('key', 'cod_allowed')->first();
        $this->assertEquals('payment', $fresh->group); // Group must NOT revert to general!
        $this->assertEquals('Enable Cash On Delivery (COD)', $fresh->label); // Label must NOT be null!
        $this->assertEquals('boolean', $fresh->type); // Type must remain boolean!
        $this->assertFalse((bool) Setting::get('cod_allowed'));
    }

    public function test_setting_alias_resolution_works_across_app(): void
    {
        Setting::set('cod_allowed', '1', 'boolean', 'payment', 'COD Allowed');
        $this->assertTrue((bool) Setting::get('cod_enabled'));
        $this->assertTrue((bool) Setting::get('cod_allowed'));

        Setting::set('free_shipping_threshold', '699.00', 'number', 'delivery');
        $this->assertEquals(699.00, (float) Setting::get('free_delivery_threshold'));
        $this->assertEquals(699.00, (float) Setting::get('free_shipping_threshold'));

        Setting::set('standard_delivery_fee', '35.00', 'number', 'delivery');
        $this->assertEquals(35.00, (float) Setting::get('base_delivery_fee'));
        $this->assertEquals(35.00, (float) Setting::get('standard_delivery_fee'));
    }

    public function test_activity_log_is_created_on_settings_update(): void
    {
        $this->actingAs($this->adminUser)->post(route('admin.settings.update'), [
            'tab' => 'general',
            'settings' => [
                'name' => 'OneStall MegaStore',
            ],
        ]);

        $this->assertEquals('OneStall MegaStore', Setting::get('name'));

        if (class_exists(\Spatie\Activitylog\Models\Activity::class)) {
            $this->assertDatabaseHas('activity_log', [
                'log_name' => 'settings',
                'causer_id' => $this->adminUser->id,
            ]);
        }
    }
}
