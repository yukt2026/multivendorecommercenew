<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InventoryLocationType;
use App\Enums\SellerStatus;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $customerUser;
    protected Seller $seller;
    protected Store $store;
    protected InventoryLocation $location;
    protected Product $product;
    protected ProductVariant $variant;
    protected Inventory $inventory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->adminUser = User::factory()->create([
            'email' => 'admin.ops@onestall.in',
            'name' => 'Operations Director',
        ]);
        $this->adminUser->assignRole('admin');

        $this->customerUser = User::factory()->create([
            'email' => 'customer@onestall.in',
            'name' => 'Customer User',
        ]);
        $this->customerUser->assignRole('customer');

        $this->seller = Seller::factory()->create([
            'business_name' => 'Metro Supermart Hub',
            'status' => SellerStatus::APPROVED,
        ]);

        $this->store = Store::factory()->create([
            'seller_id' => $this->seller->id,
            'name' => 'Metro Outlet Indiranagar',
        ]);

        $this->location = InventoryLocation::create([
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'name' => 'Indiranagar Store Shelf',
            'type' => InventoryLocationType::STORE,
            'is_active' => true,
        ]);

        $category = Category::factory()->create();
        $this->product = Product::factory()->create([
            'seller_id' => $this->seller->id,
            'category_id' => $category->id,
            'title' => 'Organic Cow Milk 1L',
        ]);

        $this->variant = ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'sku' => 'MILK-1L-ORG',
            'mrp' => 85.00,
            'selling_price' => 78.00,
        ]);

        $this->inventory = Inventory::create([
            'variant_id' => $this->variant->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 5,
            'low_stock_threshold' => 10,
            'is_quick_available' => true,
        ]);
    }

    public function test_admin_can_view_global_inventory_with_metrics(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/inventory');

        $response->assertStatus(200);
        $response->assertSee('Global Marketplace Inventory');
        $response->assertSee('Organic Cow Milk 1L');
        $response->assertSee('MILK-1L-ORG');
        $response->assertSee('Metro Supermart Hub');
        $response->assertSee('Indiranagar Store Shelf');
        $response->assertSee('Tracked SKU Allocations');
        $response->assertSee('Total Physical Units');
        $response->assertSee('Cart Locked Units');
    }

    public function test_admin_can_search_and_filter_global_inventory(): void
    {
        // 1. Search by SKU
        $searchRes = $this->actingAs($this->adminUser)->get('/admin/inventory?search=MILK-1L');
        $searchRes->assertStatus(200);
        $searchRes->assertSee('Organic Cow Milk 1L');

        // 2. Filter by Seller
        $sellerRes = $this->actingAs($this->adminUser)->get("/admin/inventory?seller_id={$this->seller->id}");
        $sellerRes->assertStatus(200);
        $sellerRes->assertSee('Organic Cow Milk 1L');

        // 3. Filter by Stock Status: in_stock
        $stockRes = $this->actingAs($this->adminUser)->get('/admin/inventory?stock_status=in_stock');
        $stockRes->assertStatus(200);
        $stockRes->assertSee('Organic Cow Milk 1L');

        // 4. Filter by Stock Status: out_of_stock should NOT match
        $outRes = $this->actingAs($this->adminUser)->get('/admin/inventory?stock_status=out_of_stock');
        $outRes->assertStatus(200);
        $outRes->assertSee('No global inventory balances match criteria');
    }

    public function test_admin_can_export_global_inventory_to_csv(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/inventory/export');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="global-inventory-', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $response = $this->get('/admin/inventory');
        $response->assertRedirect('/admin/login');
    }

    public function test_unauthorized_user_cannot_view_admin_inventory(): void
    {
        $response = $this->actingAs($this->customerUser)->get('/admin/inventory');
        $response->assertStatus(403);
    }
}
