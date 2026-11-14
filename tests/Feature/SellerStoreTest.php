<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CouponType;
use App\Enums\ProductStatus;
use App\Enums\ReviewStatus;
use App\Enums\SellerStatus;
use App\Enums\SellerType;
use App\Enums\StoreStatus;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\Seller;
use App\Models\Store;
use App\Models\StoreDeliveryZone;
use App\Models\StoreFollower;
use App\Models\StoreLocation;
use App\Models\StoreTiming;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerStoreTest extends TestCase
{
    use RefreshDatabase;

    protected User $sellerUser;
    protected Seller $seller;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->sellerUser = User::factory()->create([
            'email' => 'store.owner@onestall.in',
            'phone' => '+919876543201',
            'phone_verified_at' => now(),
            'email_verified_at' => now(),
        ]);
        $this->sellerUser->assignRole('seller_owner');

        $this->seller = Seller::factory()->create([
            'user_id' => $this->sellerUser->id,
            'business_name' => 'Apex Retail Solutions',
            'legal_name' => 'Apex Retail Solutions Private Limited',
            'seller_type' => SellerType::LOCAL_STORE,
            'status' => SellerStatus::APPROVED,
            'onboarding_step' => 7,
            'pan' => 'ABCDE1234F',
            'gstin' => '29ABCDE1234F1Z5',
        ]);

        $this->store = Store::factory()->create([
            'seller_id' => $this->seller->id,
            'name' => 'Apex Mega Mart',
            'slug' => 'apex-mega-mart',
            'status' => StoreStatus::ACTIVE,
            'accepts_quick_commerce' => true,
            'accepts_pickup' => true,
            'delivery_radius_km' => 7.50,
            'rating_count' => 0,
        ]);

        StoreLocation::create([
            'store_id' => $this->store->id,
            'address' => '100 Commercial Street',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560001',
            'lat' => 12.9716,
            'lng' => 77.5946,
        ]);

        for ($day = 0; $day <= 6; $day++) {
            StoreTiming::create([
                'store_id' => $this->store->id,
                'day_of_week' => $day,
                'opens_at' => '08:00:00',
                'closes_at' => '22:00:00',
                'is_closed' => false,
            ]);
        }
    }

    public function test_seller_can_access_store_settings_page(): void
    {
        $response = $this->actingAs($this->sellerUser)
            ->get(route('seller.store.edit'));

        $response->assertStatus(200);
        $response->assertSee('Apex Mega Mart');
        $response->assertSee('apex-mega-mart');
        $response->assertSee('Storefront &amp; Omnichannel Profile', false);
        $response->assertSee('Brand &amp; Visual Identity', false);
        $response->assertSee('Operating Schedule &amp; Timings', false);
        $response->assertSee('Delivery Radius, Zones &amp; Fulfillment Toggles', false);
        $response->assertSee('Search Engine Optimization (SEO)', false);
    }

    public function test_unauthorized_user_cannot_access_seller_store_settings(): void
    {
        // Guest
        $this->get(route('seller.store.edit'))
            ->assertRedirect(route('seller.login'));

        // Normal Customer
        $customerUser = User::factory()->create();
        $customerUser->assignRole('customer');

        $this->actingAs($customerUser)
            ->get(route('seller.store.edit'))
            ->assertForbidden();
    }

    public function test_live_slug_availability_check(): void
    {
        // 1. Own existing slug is available
        $response = $this->actingAs($this->sellerUser)
            ->postJson(route('seller.store.check_slug'), ['slug' => 'apex-mega-mart']);

        $response->assertStatus(200)
            ->assertJson([
                'available' => true,
            ]);

        // 2. Fresh new slug is available
        $response = $this->actingAs($this->sellerUser)
            ->postJson(route('seller.store.check_slug'), ['slug' => 'brand-new-outlet']);

        $response->assertStatus(200)
            ->assertJson([
                'available' => true,
            ]);

        // 3. Reserved platform keyword is blocked
        $response = $this->actingAs($this->sellerUser)
            ->postJson(route('seller.store.check_slug'), ['slug' => 'admin']);

        $response->assertStatus(200)
            ->assertJson([
                'available' => false,
            ]);

        // 4. Slug already taken by another store
        Store::factory()->create([
            'seller_id' => $this->seller->id,
            'name' => 'Taken Store',
            'slug' => 'taken-store-slug',
        ]);

        $response = $this->actingAs($this->sellerUser)
            ->postJson(route('seller.store.check_slug'), ['slug' => 'taken-store-slug']);

        $response->assertStatus(200)
            ->assertJson([
                'available' => false,
            ]);
    }

    public function test_seller_can_update_storefront_details_and_timings(): void
    {
        Storage::fake('public');

        $logoFile = UploadedFile::fake()->image('store_logo.png', 400, 400);
        $bannerFile = UploadedFile::fake()->image('store_banner.jpg', 1200, 400);

        $timingsData = [];
        for ($i = 0; $i <= 6; $i++) {
            $timingsData[$i] = [
                'day_of_week' => (string) $i,
                'opens_at' => '09:00',
                'closes_at' => '21:00',
                'is_closed' => $i === 0 ? '1' : '0', // Closed on Sundays
            ];
        }

        $updatePayload = [
            'name' => 'Apex Supermart Prime',
            'slug' => 'apex-supermart-prime',
            'description' => 'The ultimate neighbourhood one-stop department store.',
            'phone' => '+919988776655',
            'email' => 'prime@apexretail.in',
            'address' => 'Plot 45, Indiranagar 100ft Road',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560038',
            'lat' => 12.9784,
            'lng' => 77.6408,
            'delivery_radius_km' => 10.5,
            'delivery_fee' => 40.0,
            'min_order_value' => 199.0,
            'accepts_pickup' => '1',
            'accepts_quick_commerce' => '1',
            'is_active' => '1',
            'meta_title' => 'Apex Supermart Prime | Quick Grocery & Daily Essentials',
            'meta_description' => 'Get fresh groceries and daily essentials delivered in 15-30 minutes.',
            'logo' => $logoFile,
            'banner' => $bannerFile,
            'timings' => $timingsData,
        ];

        $response = $this->actingAs($this->sellerUser)
            ->put(route('seller.store.update'), $updatePayload);

        $response->assertRedirect(route('seller.store.edit'));
        $response->assertSessionHas('success');

        // Check Store model in DB
        $this->store->refresh();
        $this->assertEquals('Apex Supermart Prime', $this->store->name);
        $this->assertEquals('apex-supermart-prime', $this->store->slug);
        $this->assertEquals('The ultimate neighbourhood one-stop department store.', $this->store->description);
        $this->assertEquals(10.5, (float) $this->store->delivery_radius_km);
        $this->assertNotNull($this->store->logo);
        $this->assertNotNull($this->store->banner);

        // Check StoreLocation in DB
        $location = $this->store->location()->first();
        $this->assertNotNull($location);
        $this->assertEquals('Plot 45, Indiranagar 100ft Road', $location->address);
        $this->assertEquals('Bengaluru', $location->city);
        $this->assertEquals('560038', $location->pincode);
        $this->assertEquals(12.9784, (float) $location->lat);
        $this->assertEquals(77.6408, (float) $location->lng);

        // Check StoreTiming in DB (Sunday closed, Monday open)
        $sundayTiming = $this->store->timings()->where('day_of_week', 0)->first();
        $this->assertNotNull($sundayTiming);
        $this->assertTrue((bool) $sundayTiming->is_closed);

        $mondayTiming = $this->store->timings()->where('day_of_week', 1)->first();
        $this->assertNotNull($mondayTiming);
        $this->assertFalse((bool) $mondayTiming->is_closed);
        $this->assertStringStartsWith('09:00', (string) $mondayTiming->opens_at);

        // Check StoreDeliveryZone in DB
        $zone = $this->store->deliveryZones()->where('is_quick', true)->first();
        $this->assertNotNull($zone);
        $this->assertEquals(10.5, (float) $zone->radius_km);
        $this->assertEquals(40.0, (float) $zone->delivery_fee);
        $this->assertEquals(199.0, (float) $zone->min_order_value);
    }

    public function test_public_storefront_accessible_for_active_store(): void
    {
        $category = Category::factory()->create([
            'name' => 'Fresh Groceries',
            'slug' => 'fresh-groceries',
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'seller_id' => $this->seller->id,
            'category_id' => $category->id,
            'title' => 'Organic Cow Milk 1L',
            'slug' => 'organic-cow-milk-1l',
            'status' => ProductStatus::ACTIVE,
            'is_quick_capable' => true,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'MILK-ORG-1L',
            'name' => '1 Litre Tetra Pack',
            'mrp' => 85.00,
            'selling_price' => 75.00,
            'is_default' => true,
            'is_active' => true,
        ]);

        Coupon::create([
            'seller_id' => $this->seller->id,
            'code' => 'APEXFLAT50',
            'type' => CouponType::FLAT,
            'value' => 50.00,
            'min_order' => 300.00,
            'is_active' => true,
            'ends_at' => now()->addDays(30),
        ]);

        $customer = Customer::factory()->create();
        Review::create([
            'product_id' => $product->id,
            'user_id' => $customer->user_id,
            'rating' => 5,
            'title' => 'Top Quality',
            'body' => 'Prompt delivery and fresh quality!',
            'status' => ReviewStatus::APPROVED,
        ]);

        $response = $this->get(route('store.show', ['slug' => 'apex-mega-mart']));

        $response->assertStatus(200);
        $response->assertSee('Apex Mega Mart');
        $response->assertSee('Organic Cow Milk 1L');
        $response->assertSee('APEXFLAT50');
        $response->assertSee('Top Quality');
        $response->assertSee('schema.org', false);
        $response->assertSee('"@type": "Store"', false);
        $response->assertSee('100 Commercial Street');
    }

    public function test_public_storefront_returns_404_for_suspended_or_unapproved_stores(): void
    {
        // 1. Non-existent slug -> 404
        $this->get(route('store.show', ['slug' => 'non-existent-store']))
            ->assertStatus(404);

        // 2. Suspended store -> 404
        $this->store->update(['status' => StoreStatus::SUSPENDED]);
        $this->get(route('store.show', ['slug' => 'apex-mega-mart']))
            ->assertStatus(404);

        // 3. Store active, but seller suspended / pending -> 404
        $this->store->update(['status' => StoreStatus::ACTIVE]);
        $this->seller->update(['status' => SellerStatus::PENDING]);
        $this->get(route('store.show', ['slug' => 'apex-mega-mart']))
            ->assertStatus(404);
    }

    public function test_customer_can_toggle_follow_store(): void
    {
        $customerUser = User::factory()->create();
        $customerUser->assignRole('customer');

        // Initial state: 0 followers
        $this->assertEquals(0, $this->store->rating_count);
        $this->assertFalse(StoreFollower::where('store_id', $this->store->id)->where('user_id', $customerUser->id)->exists());

        // 1. Follow via AJAX
        $response = $this->actingAs($customerUser)
            ->postJson(route('store.follow', ['slug' => $this->store->slug]));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'following' => true,
                'followers_count' => 1,
            ]);

        $this->assertTrue(StoreFollower::where('store_id', $this->store->id)->where('user_id', $customerUser->id)->exists());
        $this->assertEquals(1, $this->store->fresh()->rating_count);

        // 2. Unfollow via AJAX
        $response2 = $this->actingAs($customerUser)
            ->postJson(route('store.follow', ['slug' => $this->store->slug]));

        $response2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'following' => false,
                'followers_count' => 0,
            ]);

        $this->assertFalse(StoreFollower::where('store_id', $this->store->id)->where('user_id', $customerUser->id)->exists());
        $this->assertEquals(0, $this->store->fresh()->rating_count);
    }

    public function test_unauthenticated_user_cannot_follow_store(): void
    {
        // JSON request returns 401
        $this->postJson(route('store.follow', ['slug' => $this->store->slug]))
            ->assertStatus(401);

        // Web request redirects to login
        $this->post(route('store.follow', ['slug' => $this->store->slug]))
            ->assertRedirect(route('login'));
    }

    public function test_qr_code_endpoint_returns_valid_image_response(): void
    {
        $response = $this->actingAs($this->sellerUser)
            ->get(route('seller.store.qr_code'));

        $this->assertTrue(in_array($response->getStatusCode(), [200, 302], true));
        if ($response->getStatusCode() === 200) {
            $this->assertStringContainsString('image/png', (string) $response->headers->get('Content-Type'));
        }
    }

    public function test_store_timings_support_24_hours_and_overnight_schedule(): void
    {
        $timing24 = \App\Models\StoreTiming::updateOrCreate([
            'store_id' => $this->store->id,
            'day_of_week' => 2,
        ], [
            'opens_at' => '00:00:00',
            'closes_at' => '23:59:00',
            'is_closed' => false,
        ]);
        $this->assertTrue($timing24->is24Hours());
        $this->assertTrue($timing24->isOpenAt('03:30:00'));
        $this->assertTrue($timing24->isOpenAt('15:00:00'));
        $this->assertTrue($timing24->isOpenAt('23:59:00'));

        $timingOvernight = \App\Models\StoreTiming::updateOrCreate([
            'store_id' => $this->store->id,
            'day_of_week' => 3,
        ], [
            'opens_at' => '20:00:00',
            'closes_at' => '04:00:00',
            'is_closed' => false,
        ]);
        $this->assertFalse($timingOvernight->is24Hours());
        $this->assertTrue($timingOvernight->isOpenAt('22:00:00'));
        $this->assertTrue($timingOvernight->isOpenAt('02:00:00'));
        $this->assertFalse($timingOvernight->isOpenAt('12:00:00'));
    }

    public function test_seller_can_view_store_reports_page(): void
    {
        $response = $this->actingAs($this->sellerUser)
            ->get(route('seller.stores.reports'));

        $response->assertStatus(200);
        $response->assertSee('Store Analytics &amp; Revenue Reports', false);
        $response->assertSee('Delivered Revenue');
        $response->assertSee('Avg Order Value (AOV)');
        $response->assertSee('Store Outlets Performance Breakdown');
    }

    public function test_seller_can_export_store_reports_as_csv(): void
    {
        $response = $this->actingAs($this->sellerUser)
            ->get(route('seller.stores.reports.export', ['range' => '30d']));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('store_report_', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_seller_can_view_all_stores_list(): void
    {
        $response = $this->actingAs($this->sellerUser)
            ->get(route('seller.stores.index'));

        $response->assertStatus(200);
        $response->assertSee('Store Outlets &amp; Fulfillment Hubs', false);
        $response->assertSee($this->store->name);
        $response->assertSee('Total Store Outlets');
    }

    public function test_seller_can_access_create_store_page(): void
    {
        $response = $this->actingAs($this->sellerUser)
            ->get(route('seller.stores.create'));

        $response->assertStatus(200);
        $response->assertSee('Add Physical Store Outlet');
    }

    public function test_seller_can_create_new_physical_store_outlet(): void
    {
        $response = $this->actingAs($this->sellerUser)
            ->post(route('seller.stores.store'), [
                'name' => 'Metro Express - Indiranagar',
                'slug' => 'metro-express-indiranagar',
                'phone' => '9876543210',
                'email' => 'indiranagar@metro.com',
                'address' => '100ft Road, HAL 2nd Stage',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'pincode' => '560038',
                'lat' => 12.9719,
                'lng' => 77.6412,
                'delivery_radius_km' => 6.5,
                'accepts_quick_commerce' => 1,
                'accepts_pickup' => 1,
                'opens_at' => '08:00',
                'closes_at' => '23:00',
            ]);

        $response->assertRedirect(route('seller.stores.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('stores', [
            'name' => 'Metro Express - Indiranagar',
            'slug' => 'metro-express-indiranagar',
            'seller_id' => $this->seller->id,
        ]);

        $newStore = Store::where('slug', 'metro-express-indiranagar')->first();
        $this->assertNotNull($newStore);
        $this->assertDatabaseHas('inventory_locations', [
            'store_id' => $newStore->id,
            'seller_id' => $this->seller->id,
        ]);
    }

    public function test_seller_can_switch_active_store_outlet(): void
    {
        $secondStore = Store::factory()->create([
            'seller_id' => $this->seller->id,
            'name' => 'Koramangala Branch',
            'slug' => 'koramangala-branch',
            'status' => StoreStatus::ACTIVE,
        ]);

        $response = $this->actingAs($this->sellerUser)
            ->post(route('seller.switch_store'), [
                'store_id' => $secondStore->id,
            ]);

        $response->assertRedirect();
        $this->assertEquals($secondStore->id, session('seller_active_store_id'));
    }

    public function test_seller_can_edit_specific_store_outlet(): void
    {
        $secondStore = Store::factory()->create([
            'seller_id' => $this->seller->id,
            'name' => 'Whitefield Hub',
            'slug' => 'whitefield-hub',
            'status' => StoreStatus::ACTIVE,
        ]);

        $response = $this->actingAs($this->sellerUser)
            ->get(route('seller.store.edit', ['store_id' => $secondStore->id]));

        $response->assertStatus(200);
        $response->assertSee('Whitefield Hub');
    }
}
