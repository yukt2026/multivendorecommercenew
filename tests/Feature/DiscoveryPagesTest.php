<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\SellerType;
use App\Enums\StoreStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\ProductSpecification;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Store;
use App\Models\StoreLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscoveryPagesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Customer $customer;
    protected Seller $seller;
    protected Store $store;
    protected Brand $brand;
    protected Category $category;
    protected Product $product;
    protected ProductVariant $variant1;
    protected ProductVariant $variant2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'buyer@onestall.in',
            'phone' => '9123456789',
            'status' => 'active',
        ]);
        $this->customer = Customer::create([
            'user_id' => $this->user->id,
        ]);

        $sellerUser = User::factory()->create(['email' => 'merchant@onestall.in']);
        $this->seller = Seller::create([
            'user_id' => $sellerUser->id,
            'business_name' => 'Indiranagar Fresh Market',
            'legal_name' => 'Indiranagar Fresh Market Private Limited',
            'seller_type' => SellerType::LOCAL_STORE,
            'status' => 'approved',
        ]);

        $this->store = Store::create([
            'seller_id' => $this->seller->id,
            'name' => 'Indiranagar SuperStore',
            'slug' => 'indiranagar-superstore',
            'status' => StoreStatus::ACTIVE,
            'is_onestall_owned' => false,
            'accepts_quick_commerce' => true,
            'accepts_pickup' => true,
            'rating_avg' => 4.9,
            'rating_count' => 60,
            'delivery_radius_km' => 12.0,
        ]);

        StoreLocation::create([
            'store_id' => $this->store->id,
            'address' => '12th Main, HAL 2nd Stage, Indiranagar',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560038',
            'lat' => 12.9716,
            'lng' => 77.5946,
        ]);

        $this->brand = Brand::create([
            'name' => 'Amul Heritage',
            'slug' => 'amul-heritage',
            'is_verified' => true,
            'is_active' => true,
            'description' => 'The Taste of India dairy products.',
        ]);

        $this->category = Category::create([
            'name' => 'Dairy & Eggs',
            'slug' => 'dairy-eggs',
            'meta_description' => 'Fresh farm milk, artisanal cheese, curd and eggs delivered in minutes.',
            'is_active' => true,
            'is_quick_eligible' => true,
        ]);

        $this->product = Product::create([
            'seller_id' => $this->seller->id,
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'title' => 'Amul Gold Full Cream Milk 1L',
            'slug' => 'amul-gold-full-cream-milk-1l',
            'status' => ProductStatus::ACTIVE,
            'product_type' => ProductType::VARIABLE,
            'is_quick_capable' => true,
            'is_cod_allowed' => true,
            'tax_percent' => 5.0,
            'short_desc' => 'Pasteurized full cream fresh milk.',
            'description' => 'Nutritious pasteurized whole milk with 6% fat and 9% SNF.',
        ]);

        $this->variant1 = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'AMUL-GOLD-500ML',
            'name' => '500ml Pouch',
            'mrp' => 35.00,
            'selling_price' => 33.00,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->variant2 = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'AMUL-GOLD-1L',
            'name' => '1 Litre Pouch',
            'mrp' => 68.00,
            'selling_price' => 64.00,
            'is_default' => false,
            'is_active' => true,
        ]);

        $invLocation = InventoryLocation::create([
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'name' => 'Store Front Shelf',
            'type' => 'store',
        ]);

        Inventory::create([
            'location_id' => $invLocation->id,
            'variant_id' => $this->variant1->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
            'low_stock_threshold' => 5,
        ]);

        ProductSpecification::create([
            'product_id' => $this->product->id,
            'group' => 'Nutritional Info',
            'key' => 'Fat Content',
            'value' => '6.0%',
        ]);
    }

    public function test_search_page_renders_with_products_tab(): void
    {
        $response = $this->get(route('search', ['q' => 'Amul']));

        $response->assertStatus(200);
        $response->assertSee('Amul Gold Full Cream Milk 1L');
        $response->assertSee('Filters');
        $response->assertSee('Products (');
        $response->assertSee('Stores (');
        $response->assertSee('Brands (');
    }

    public function test_search_page_switches_tabs_to_stores_and_brands(): void
    {
        // Stores Tab
        $resStore = $this->get(route('search', ['q' => 'Indiranagar', 'tab' => 'stores']));
        $resStore->assertStatus(200);
        $resStore->assertSee('Indiranagar SuperStore');
        $resStore->assertSee('Visit Store');

        // Brands Tab
        $resBrand = $this->get(route('search', ['q' => 'Amul', 'tab' => 'brands']));
        $resBrand->assertStatus(200);
        $resBrand->assertSee('Amul Heritage');
    }

    public function test_search_filters_by_price_and_quick_delivery(): void
    {
        $response = $this->get(route('search', [
            'q' => 'Amul',
            'price_min' => 10,
            'price_max' => 50,
            'delivery_type' => 'quick',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Amul Gold Full Cream Milk 1L');
        $response->assertSee('⚡ 15m Quick Delivery');
    }

    public function test_category_page_renders_with_breadcrumbs_and_seo_block(): void
    {
        $response = $this->get(route('category.show', $this->category->slug));

        $response->assertStatus(200);
        $response->assertSee('Dairy &amp; Eggs', false);
        $response->assertSee('Fresh farm milk, artisanal cheese');
        $response->assertSee('Amul Gold Full Cream Milk 1L');
    }

    public function test_brand_page_renders_with_verified_badge_and_products(): void
    {
        $response = $this->get(route('brand.show', $this->brand->slug));

        $response->assertStatus(200);
        $response->assertSee('Amul Heritage');
        $response->assertSee('Verified Brand');
        $response->assertSee('Amul Gold Full Cream Milk 1L');
    }

    public function test_nearby_stores_page_renders_with_map_pins(): void
    {
        $response = $this->get(route('stores.index', ['radius_km' => 25]));

        $response->assertStatus(200);
        $response->assertSee('Nearby Stores');
        $response->assertSee('Indiranagar SuperStore');
        $response->assertSee('12th Main, HAL 2nd Stage, Indiranagar');
        $response->assertSee('Enter Storefront');
    }

    public function test_product_detail_page_renders_with_variants_and_schema(): void
    {
        $response = $this->get(route('product.show', $this->product->slug));

        $response->assertStatus(200);
        $response->assertSee('Amul Gold Full Cream Milk 1L');
        $response->assertSee('Nutritional Info');
        $response->assertSee('Fat Content');
        $response->assertSee('6.0%');
        $response->assertSee('500ml Pouch');
        $response->assertSee('1 Litre Pouch');
        $response->assertSee('https://schema.org');
        $response->assertSee('Customer Questions &amp; Answers', false);
    }

    public function test_pincode_check_endpoint(): void
    {
        $response = $this->postJson(route('storefront.pincode.check'), [
            'pincode' => '560038',
            'product_id' => $this->product->id,
            'variant_id' => $this->variant1->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'pincode' => '560038',
                'is_deliverable' => true,
            ]);
    }

    public function test_authenticated_customer_can_submit_review(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson(route('product.review.submit', $this->product->slug), [
            'rating' => 5,
            'title' => 'Fresh and delicious!',
            'body' => 'Delivered in under 20 minutes cold and fresh. Top notch quality!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'review' => [
                    'rating' => 5,
                    'title' => 'Fresh and delicious!',
                ],
            ]);

        $this->assertDatabaseHas('reviews', [
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'rating' => 5,
            'title' => 'Fresh and delicious!',
        ]);
    }

    public function test_authenticated_customer_can_ask_question(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson(route('product.question.submit', $this->product->slug), [
            'question' => 'Is this milk homogenized and suitable for toddlers?',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'question' => [
                    'question' => 'Is this milk homogenized and suitable for toddlers?',
                ],
            ]);

        $this->assertDatabaseHas('product_questions', [
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'question' => 'Is this milk homogenized and suitable for toddlers?',
        ]);
    }
}
