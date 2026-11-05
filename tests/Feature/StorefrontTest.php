<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\StoreStatus;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Store;
use App\Models\StoreLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected User $customerUser;
    protected Customer $customer;
    protected Seller $seller;
    protected Store $store;
    protected Product $product;
    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerUser = User::factory()->create([
            'email' => 'customer@onestall.in',
            'phone' => '9876543210',
            'status' => 'active',
        ]);
        $this->customer = Customer::create([
            'user_id' => $this->customerUser->id,
        ]);

        $sellerUser = User::factory()->create(['email' => 'seller@onestall.in']);
        $this->seller = Seller::create([
            'user_id' => $sellerUser->id,
            'business_name' => 'Apex Retailers',
            'legal_name' => 'Apex Retailers Private Limited',
            'seller_type' => \App\Enums\SellerType::LOCAL_STORE,
            'status' => 'approved',
        ]);

        $this->store = Store::create([
            'seller_id' => $this->seller->id,
            'name' => 'Apex Indiranagar Outlet',
            'slug' => 'apex-indiranagar-outlet',
            'status' => StoreStatus::ACTIVE,
            'is_onestall_owned' => false,
            'accepts_quick_commerce' => true,
            'accepts_pickup' => true,
            'rating_avg' => 4.8,
            'rating_count' => 45,
            'delivery_radius_km' => 10.0,
        ]);

        StoreLocation::create([
            'store_id' => $this->store->id,
            'address' => '100 Feet Road, Indiranagar',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560038',
            'lat' => 12.9716,
            'lng' => 77.5946,
        ]);

        $brand = Brand::create([
            'name' => 'FreshFarm Naturals',
            'slug' => 'freshfarm-naturals',
            'is_verified' => true,
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Fresh Groceries',
            'slug' => 'fresh-groceries',
            'is_active' => true,
            'is_quick_eligible' => true,
        ]);

        $this->product = Product::create([
            'seller_id' => $this->seller->id,
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'title' => 'Organic Cow Milk A2 1L',
            'slug' => 'organic-cow-milk-a2-1l',
            'status' => ProductStatus::ACTIVE,
            'product_type' => ProductType::SIMPLE,
            'is_quick_capable' => true,
            'tax_percent' => 5.0,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'MILK-A2-1L',
            'name' => '1 Litre Pouch',
            'mrp' => 90.00,
            'selling_price' => 75.00,
            'is_default' => true,
            'is_active' => true,
        ]);

        Banner::create([
            'title' => 'Express Morning Delivery',
            'position' => 'home_hero',
            'image' => 'banners/hero_milk.jpg',
            'link' => '/categories/fresh-groceries',
            'sort' => 1,
            'is_active' => true,
        ]);
    }

    public function test_storefront_homepage_renders_successfully(): void
    {
        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $response->assertSee('OneStall');
        $response->assertSee('Explore Categories');
        $response->assertSee('Nearby Retail Stores');
        $response->assertSee('Organic Cow Milk A2 1L');
        $response->assertSee('FreshFarm Naturals');
        $response->assertSee('Express Morning Delivery');
    }

    public function test_location_current_endpoint_returns_location(): void
    {
        $response = $this->getJson(route('storefront.location.current'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'current' => ['city', 'pincode', 'lat', 'lng', 'address_label'],
                'popular',
                'saved',
            ]);
    }

    public function test_location_can_be_set_via_api(): void
    {
        $response = $this->postJson(route('storefront.location.set'), [
            'city' => 'Mumbai',
            'pincode' => '400001',
            'state' => 'Maharashtra',
            'lat' => 18.9388,
            'lng' => 72.8354,
            'address_label' => 'Mumbai South 400001',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'location' => [
                    'city' => 'Mumbai',
                    'pincode' => '400001',
                ],
            ])
            ->assertCookie('customer_location');
    }

    public function test_location_search_returns_matching_cities(): void
    {
        $response = $this->getJson(route('storefront.location.search', ['q' => 'Bengaluru']));

        $response->assertStatus(200)
            ->assertJsonPath('results.0.city', 'Bengaluru');
    }

    public function test_cart_add_update_remove_and_summary_lifecycle(): void
    {
        $this->actingAs($this->customerUser);

        // 1. Initial cart summary is empty
        $resSummary = $this->getJson(route('storefront.cart.summary'));
        $resSummary->assertStatus(200)
            ->assertJsonPath('cart.item_count', 0);

        // 2. Add product to cart
        $resAdd = $this->postJson(route('storefront.cart.add'), [
            'variant_id' => $this->variant->id,
            'quantity' => 2,
        ]);

        $resAdd->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'cart' => [
                    'item_count' => 2,
                    'subtotal' => 150.00,
                ],
            ]);

        $itemId = $resAdd->json('cart.items.0.id');
        $this->assertNotNull($itemId);

        // 3. Update quantity to 4
        $resUpdate = $this->postJson(route('storefront.cart.update', $itemId), [
            'quantity' => 4,
        ]);

        $resUpdate->assertStatus(200)
            ->assertJsonPath('cart.item_count', 4)
            ->assertJsonPath('cart.subtotal', 300);

        // 4. Remove item
        $resRemove = $this->postJson(route('storefront.cart.remove', $itemId));
        $resRemove->assertStatus(200)
            ->assertJsonPath('cart.item_count', 0)
            ->assertJsonPath('cart.subtotal', 0);
    }

    public function test_search_suggestions_returns_matching_results(): void
    {
        $response = $this->getJson(route('storefront.search.suggestions', [
            'q' => 'Organic',
            'type' => 'all',
        ]));

        $response->assertStatus(200)
            ->assertJsonPath('query', 'Organic');

        $products = $response->json('results.products');
        $this->assertNotEmpty($products);
        $this->assertEquals('Organic Cow Milk A2 1L', $products[0]['title']);
    }

    public function test_wishlist_toggle_for_authenticated_customer(): void
    {
        $this->actingAs($this->customerUser);

        // Toggle Add
        $resAdd = $this->postJson(route('storefront.wishlist.toggle'), [
            'product_id' => $this->product->id,
        ]);

        $resAdd->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'is_wishlisted' => true,
                'wishlist_count' => 1,
            ]);

        // Status check
        $resStatus = $this->getJson(route('storefront.wishlist.status'));
        $resStatus->assertStatus(200)
            ->assertJsonPath('count', 1);

        // Toggle Remove
        $resRemove = $this->postJson(route('storefront.wishlist.toggle'), [
            'product_id' => $this->product->id,
        ]);

        $resRemove->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'is_wishlisted' => false,
                'wishlist_count' => 0,
            ]);
    }
}
