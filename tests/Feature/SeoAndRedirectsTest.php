<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RedirectRule;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Services\Seo\SeoService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SeoAndRedirectsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Seller $seller;
    protected Store $store;
    protected Category $category;
    protected Brand $brand;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->adminUser = User::factory()->create([
            'email' => 'seo.admin@onestall.in',
            'name' => 'SEO Director',
        ]);
        $this->adminUser->assignRole('admin');

        $sellerUser = User::factory()->create();
        $this->seller = Seller::create([
            'user_id' => $sellerUser->id,
            'business_name' => 'Apex Electronics India',
            'legal_name' => 'Apex Electronics India Pvt Ltd',
            'status' => 'approved',
        ]);

        $this->store = Store::create([
            'seller_id' => $this->seller->id,
            'name' => 'Apex MegaStore Indiranagar',
            'slug' => 'apex-megastore-indiranagar',
            'status' => 'active',
            'meta_title' => 'Apex Store Indiranagar - Best Electronics Deals',
            'meta_description' => 'Visit Apex Store Indiranagar for express gadgets and smartphones.',
        ]);

        $this->category = Category::create([
            'name' => 'Smartphones & Accessories',
            'slug' => 'smartphones-accessories',
            'is_active' => true,
            'meta_title' => 'Smartphones Online - Best Smartphone Deals | OneStall',
            'meta_description' => 'Buy top brand smartphones with instant 15-minute delivery.',
        ]);

        $this->brand = Brand::create([
            'name' => 'Zenith Tech',
            'slug' => 'zenith-tech',
            'meta_title' => 'Zenith Tech Mobile Devices & Accessories',
            'meta_description' => 'Official Zenith Tech flagship devices and accessories.',
        ]);

        $this->product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'title' => 'Zenith Ultra Pro 5G (128GB)',
            'slug' => 'zenith-ultra-pro-5g-128gb',
            'description' => 'Flagship 5G smartphone with AMOLED screen and triple camera system.',
            'status' => ProductStatus::ACTIVE,
            'product_type' => ProductType::SIMPLE,
            'tax_percent' => 18.00,
            'meta_title' => 'Zenith Ultra Pro 5G - Specs, Price & Fast Delivery',
            'meta_description' => 'Order Zenith Ultra Pro 5G with warranty and same-day delivery.',
        ]);

        ProductVariant::create([
            'product_id' => $this->product->id,
            'name' => 'Default Variant',
            'sku' => 'ZEN-PRO-128-BLK',
            'selling_price' => 49999.00,
            'mrp' => 54999.00,
            'is_active' => true,
        ]);
    }

    public function test_seo_metadata_and_json_ld_schema_for_product(): void
    {
        $response = $this->get(route('product.show', $this->product->slug));
        $response->assertOk();

        // 1. Meta Title, Description, Canonical
        $response->assertSee('<title>Zenith Ultra Pro 5G - Specs, Price &amp; Fast Delivery</title>', false);
        $response->assertSee('name="description" content="Order Zenith Ultra Pro 5G with warranty and same-day delivery."', false);
        $response->assertSee('<link rel="canonical" href="' . route('product.show', $this->product->slug) . '">', false);

        // 2. OpenGraph & Twitter tags
        $response->assertSee('property="og:type" content="product"', false);
        $response->assertSee('property="og:title" content="Zenith Ultra Pro 5G - Specs, Price &amp; Fast Delivery"', false);
        $response->assertSee('name="twitter:card" content="summary_large_image"', false);

        // 3. JSON-LD Schemas: Product, Organization, BreadcrumbList
        $response->assertSee('"@type": "Product"', false);
        $response->assertSee('"sku": "ZEN-PRO-128-BLK"', false);
        $response->assertSee('"@type": "BreadcrumbList"', false);
        $response->assertSee('"@type": "Organization"', false);
    }

    public function test_seo_metadata_for_category_brand_and_store(): void
    {
        // Category Page
        $catResponse = $this->get(route('category.show', $this->category->slug));
        $catResponse->assertOk();
        $catResponse->assertSee('Smartphones Online - Best Smartphone Deals | OneStall');
        $catResponse->assertSee('<link rel="canonical" href="' . route('category.show', $this->category->slug) . '">', false);

        // Brand Page
        $brandResponse = $this->get(route('brand.show', $this->brand->slug));
        $brandResponse->assertOk();
        $brandResponse->assertSee('Zenith Tech Mobile Devices & Accessories');

        // Store Page
        $storeResponse = $this->get(route('store.show', $this->store->slug));
        $storeResponse->assertOk();
        $storeResponse->assertSee('Apex Store Indiranagar - Best Electronics Deals');
    }

    public function test_noindex_applied_on_filtered_and_searched_pages(): void
    {
        // Clean category page has index
        $cleanResponse = $this->get(route('category.show', $this->category->slug));
        $cleanResponse->assertOk();
        $cleanResponse->assertSee('content="index, follow', false);

        // Filtered category page must have noindex
        $filteredResponse = $this->get(route('category.show', [
            'slug' => $this->category->slug,
            'min_price' => '1000',
            'sort' => 'price_asc',
        ]));
        $filteredResponse->assertOk();
        $filteredResponse->assertSee('content="noindex, follow"', false);

        // Search catalog page must have noindex
        $searchResponse = $this->get(route('search', ['q' => 'smartphone']));
        $searchResponse->assertOk();
        $searchResponse->assertSee('content="noindex, follow"', false);

        // Explicit noindex on product
        $this->product->update(['noindex' => true]);
        $noindexProductResponse = $this->get(route('product.show', $this->product->slug));
        $noindexProductResponse->assertOk();
        $noindexProductResponse->assertSee('content="noindex, follow"', false);
    }

    public function test_search_console_and_bing_verification_tags(): void
    {
        // Update settings
        Setting::set('google_site_verification', 'google-test-token-12345');
        Setting::set('bing_site_verification', 'bing-test-token-67890');

        $response = $this->get(route('home'));
        $response->assertOk();
        $response->assertSee('<meta name="google-site-verification" content="google-test-token-12345">', false);
        $response->assertSee('<meta name="msvalidate.01" content="bing-test-token-67890">', false);
    }

    public function test_dynamic_sitemaps_and_robots_txt_endpoints(): void
    {
        // 1. Main Sitemap Index
        $indexResponse = $this->get('/sitemap.xml');
        $indexResponse->assertOk();
        $indexResponse->assertHeader('Content-Type', 'application/xml; charset=utf-8');
        $indexResponse->assertSee('<sitemapindex', false);
        $indexResponse->assertSee('/sitemap-products-1.xml', false);
        $indexResponse->assertSee('/sitemap-categories.xml', false);

        // 2. Products Sitemap
        $prodResponse = $this->get('/sitemap-products-1.xml');
        $prodResponse->assertOk();
        $prodResponse->assertHeader('Content-Type', 'application/xml; charset=utf-8');
        $prodResponse->assertSee(route('product.show', $this->product->slug));

        // 3. Categories Sitemap
        $catResponse = $this->get('/sitemap-categories.xml');
        $catResponse->assertOk();
        $catResponse->assertSee(route('category.show', $this->category->slug));

        // 4. Brands Sitemap
        $brandResponse = $this->get('/sitemap-brands.xml');
        $brandResponse->assertOk();
        $brandResponse->assertSee(route('brand.show', $this->brand->slug));

        // 5. Stores Sitemap
        $storeResponse = $this->get('/sitemap-stores.xml');
        $storeResponse->assertOk();
        $storeResponse->assertSee(route('store.show', $this->store->slug));

        // 6. Pages Sitemap
        $pagesResponse = $this->get('/sitemap-pages.xml');
        $pagesResponse->assertOk();
        $pagesResponse->assertSee(url('/'));

        // 7. Robots.txt
        $robotsResponse = $this->get('/robots.txt');
        $robotsResponse->assertOk();
        $robotsResponse->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        $robotsResponse->assertSee('User-agent: *');
        $robotsResponse->assertSee('Disallow: /admin/');
        $robotsResponse->assertSee('Disallow: /checkout');
        $robotsResponse->assertSee('Sitemap: ' . url('/sitemap.xml'));
    }

    public function test_sitemap_generate_artisan_command(): void
    {
        $this->artisan('sitemap:generate')
            ->expectsOutputToContain('Regenerating sitemaps...')
            ->expectsOutputToContain('/sitemap.xml')
            ->assertExitCode(0);
    }

    public function test_301_and_302_redirect_middleware(): void
    {
        // Create 301 redirect rule
        $rule = RedirectRule::create([
            'source_path' => '/old-phones/zenith-5g',
            'target_path' => '/p/' . $this->product->slug,
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->assertEquals(0, $rule->hit_count);

        // Request old path
        $response = $this->get('/old-phones/zenith-5g');
        $response->assertStatus(301);
        $response->assertRedirect(url('/p/' . $this->product->slug));

        // Hit count recorded
        $rule->refresh();
        $this->assertEquals(1, $rule->hit_count);
        $this->assertNotNull($rule->last_accessed_at);

        // Preserves tracking query parameters
        $queryResponse = $this->get('/old-phones/zenith-5g?campaign=relaunch&affiliate=42');
        $queryResponse->assertStatus(301);
        $queryResponse->assertRedirectContains('/p/' . $this->product->slug);
        $queryResponse->assertRedirectContains('campaign=relaunch');
        $queryResponse->assertRedirectContains('affiliate=42');

        // Inactive rule does NOT redirect
        $rule->update(['is_active' => false]);
        $inactiveResponse = $this->get('/old-phones/zenith-5g');
        $inactiveResponse->assertStatus(404);
    }

    public function test_admin_redirect_manager_crud_and_csv_import(): void
    {
        // 1. Index
        $response = $this->actingAs($this->adminUser)->get(route('admin.redirects.index'));
        $response->assertOk();
        $response->assertSee('301 / 302 Redirect Manager');

        // 2. Store
        $storeResponse = $this->actingAs($this->adminUser)->post(route('admin.redirects.store'), [
            'source_path' => 'old-catalog/laptops',
            'target_path' => '/c/smartphones-accessories',
            'status_code' => '301',
            'is_active' => '1',
        ]);
        $storeResponse->assertRedirect();

        $rule = RedirectRule::where('source_path', '/old-catalog/laptops')->first();
        $this->assertNotNull($rule);
        $this->assertEquals('/c/smartphones-accessories', $rule->target_path);
        $this->assertEquals(301, $rule->status_code);

        // 3. Prevent redirect loop
        $loopResponse = $this->actingAs($this->adminUser)->post(route('admin.redirects.store'), [
            'source_path' => '/same-path',
            'target_path' => '/same-path',
            'status_code' => '301',
        ]);
        $loopResponse->assertRedirect();
        $loopResponse->assertSessionHas('error');

        // 4. Update
        $updateResponse = $this->actingAs($this->adminUser)->put(route('admin.redirects.update', $rule), [
            'source_path' => '/old-catalog/laptops',
            'target_path' => '/stores',
            'status_code' => '302',
            'is_active' => '1',
        ]);
        $updateResponse->assertRedirect();
        $this->assertEquals('/stores', $rule->fresh()->target_path);
        $this->assertEquals(302, $rule->fresh()->status_code);

        // 5. Toggle status
        $toggleResponse = $this->actingAs($this->adminUser)->post(route('admin.redirects.toggle', $rule));
        $toggleResponse->assertRedirect();
        $this->assertFalse($rule->fresh()->is_active);

        // 6. CSV Bulk Import
        $csvContent = "source,target,code\n/old-a,/new-a,301\n/old-b,/new-b,302\n";
        $csvFile = UploadedFile::fake()->createWithContent('redirects.csv', $csvContent);

        $importResponse = $this->actingAs($this->adminUser)->post(route('admin.redirects.import'), [
            'csv_file' => $csvFile,
        ]);
        $importResponse->assertRedirect();
        $this->assertDatabaseHas('redirect_rules', ['source_path' => '/old-a', 'target_path' => '/new-a']);
        $this->assertDatabaseHas('redirect_rules', ['source_path' => '/old-b', 'target_path' => '/new-b']);

        // 7. Delete
        $deleteResponse = $this->actingAs($this->adminUser)->delete(route('admin.redirects.destroy', $rule));
        $deleteResponse->assertRedirect();
        $this->assertDatabaseMissing('redirect_rules', ['id' => $rule->id]);
    }

    public function test_public_cms_page_rendering_and_seo(): void
    {
        $page = CmsPage::create([
            'title' => 'Terms of Service & User Agreement',
            'slug' => 'terms-of-service',
            'content' => "Welcome to OneStall.\nBy using our services, you agree to these terms.\nQuick commerce delivery guarantees apply to local radius orders.",
            'meta_title' => 'Terms of Service | OneStall Marketplace',
            'meta_description' => 'Read OneStall terms of service and customer agreement.',
            'is_active' => true,
        ]);

        $response = $this->get(route('pages.show', $page->slug));
        $response->assertOk();
        $response->assertSee('Terms of Service & User Agreement');
        $response->assertSee('Quick commerce delivery guarantees apply');
        $response->assertSee('<title>Terms of Service | OneStall Marketplace</title>', false);
        $response->assertSee('<link rel="canonical" href="' . route('pages.show', $page->slug) . '">', false);
    }
}
