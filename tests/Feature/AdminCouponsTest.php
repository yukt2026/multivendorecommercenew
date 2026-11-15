<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CouponType;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Seller;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCouponsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->adminUser = User::factory()->create([
            'email' => 'admin.coupons@onestall.in',
            'name' => 'Marketing Promotions Director',
        ]);
        $this->adminUser->assignRole('super_admin');
    }

    public function test_coupons_index_page_renders_with_metrics_and_formatting(): void
    {
        $seller = Seller::factory()->create(['business_name' => 'Royal Fresh Mart']);

        Coupon::create([
            'code' => 'FLAT100OFF',
            'type' => CouponType::FLAT,
            'value' => 100.00,
            'min_order' => 499.00,
            'is_active' => true,
        ]);

        Coupon::create([
            'code' => 'DIWALI50',
            'type' => CouponType::PERCENT,
            'value' => 50.00,
            'min_order' => 299.00,
            'max_discount' => 150.00,
            'seller_id' => $seller->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.coupons.index'));

        $response->assertStatus(200);
        $response->assertSee('Platform &amp; Seller Coupons', false);
        $response->assertSee('FLAT100OFF');
        $response->assertSee('DIWALI50');
        $response->assertSee('50%');
        $response->assertSee('Royal Fresh Mart');
        $response->assertSee('Platform Wide');
    }

    public function test_coupons_can_be_filtered_and_searched(): void
    {
        $seller = Seller::factory()->create(['business_name' => 'TechZone Retail']);

        Coupon::create([
            'code' => 'SEARCHME99',
            'type' => CouponType::PERCENT,
            'value' => 15.00,
            'min_order' => 200.00,
            'is_active' => true,
        ]);

        Coupon::create([
            'code' => 'SELLERONLY20',
            'type' => CouponType::FLAT,
            'value' => 50.00,
            'seller_id' => $seller->id,
            'is_active' => false,
        ]);

        // Search by code
        $searchRes = $this->actingAs($this->adminUser)->get(route('admin.coupons.index', ['search' => 'SEARCHME']));
        $searchRes->assertStatus(200);
        $searchRes->assertSee('SEARCHME99');
        $searchRes->assertDontSee('SELLERONLY20');

        // Filter by scope=seller
        $scopeRes = $this->actingAs($this->adminUser)->get(route('admin.coupons.index', ['scope' => 'seller']));
        $scopeRes->assertStatus(200);
        $scopeRes->assertSee('SELLERONLY20');
        $scopeRes->assertDontSee('SEARCHME99');

        // Filter by type=percent
        $typeRes = $this->actingAs($this->adminUser)->get(route('admin.coupons.index', ['type' => 'percent']));
        $typeRes->assertStatus(200);
        $typeRes->assertSee('SEARCHME99');
        $typeRes->assertDontSee('SELLERONLY20');
    }

    public function test_admin_can_create_coupon_with_category_restrictions(): void
    {
        $cat = Category::factory()->create(['name' => 'Organic Produce']);

        $response = $this->actingAs($this->adminUser)->post(route('admin.coupons.store'), [
            'code' => 'ORGANIC25',
            'type' => 'percent',
            'value' => 25.00,
            'min_order' => 300.00,
            'max_discount' => 100.00,
            'usage_limit' => 500,
            'per_user_limit' => 2,
            'category_ids' => [$cat->id],
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.coupons.index'));
        $this->assertDatabaseHas('coupons', [
            'code' => 'ORGANIC25',
            'type' => 'percent',
            'min_order' => 300.00,
        ]);

        $coupon = Coupon::where('code', 'ORGANIC25')->first();
        $this->assertNotNull($coupon->applies_to);
        $this->assertSame([$cat->id], $coupon->applies_to['categories']);
    }

    public function test_admin_can_update_existing_coupon(): void
    {
        $coupon = Coupon::create([
            'code' => 'OLDRATE10',
            'type' => CouponType::PERCENT,
            'value' => 10.00,
            'min_order' => 100.00,
            'per_user_limit' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->put(route('admin.coupons.update', $coupon), [
            'code' => 'NEWRATE20',
            'type' => 'flat',
            'value' => 75.00,
            'min_order' => 350.00,
            'per_user_limit' => 3,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.coupons.index'));
        $coupon->refresh();

        $this->assertSame('NEWRATE20', $coupon->code);
        $this->assertSame(CouponType::FLAT, $coupon->type);
        $this->assertEquals(75.00, $coupon->value);
        $this->assertEquals(350.00, $coupon->min_order);
        $this->assertSame(3, $coupon->per_user_limit);
    }

    public function test_admin_can_toggle_coupon_status_via_web_and_json(): void
    {
        $coupon = Coupon::create([
            'code' => 'TOGGLECODE',
            'type' => CouponType::FLAT,
            'value' => 20.00,
            'is_active' => true,
        ]);

        // Web form post -> redirects
        $webRes = $this->actingAs($this->adminUser)->post(route('admin.coupons.toggle', $coupon));
        $webRes->assertRedirect();
        $this->assertFalse($coupon->fresh()->is_active);

        // JSON post -> returns JSON
        $jsonRes = $this->actingAs($this->adminUser)->postJson(route('admin.coupons.toggle', $coupon));
        $jsonRes->assertOk();
        $jsonRes->assertJson([
            'success' => true,
            'is_active' => true,
        ]);
        $this->assertTrue($coupon->fresh()->is_active);
    }

    public function test_admin_can_delete_coupon(): void
    {
        $coupon = Coupon::create([
            'code' => 'DELETECODE',
            'type' => CouponType::FLAT,
            'value' => 10.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->delete(route('admin.coupons.destroy', $coupon));
        $response->assertRedirect(route('admin.coupons.index'));

        $this->assertDatabaseMissing('coupons', ['code' => 'DELETECODE']);
    }

    public function test_admin_can_export_coupons_csv(): void
    {
        Coupon::create([
            'code' => 'EXPORTCSV1',
            'type' => CouponType::PERCENT,
            'value' => 10.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.coupons.export'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('EXPORTCSV1', $response->streamedContent());
    }
}
