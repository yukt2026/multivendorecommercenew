<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\SellerStatus;
use App\Enums\SellerType;
use App\Enums\StoreStatus;
use App\Models\Brand;
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

class SellerProductTest extends TestCase
{
    use RefreshDatabase;

    protected User $sellerUser;
    protected Seller $seller;
    protected Store $store;
    protected Category $category;
    protected Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->sellerUser = User::factory()->create([
            'email' => 'merchant.product@onestall.in',
            'phone' => '+919876543250',
            'phone_verified_at' => now(),
            'email_verified_at' => now(),
        ]);
        $this->sellerUser->assignRole('seller_owner');

        $this->seller = Seller::factory()->create([
            'user_id' => $this->sellerUser->id,
            'business_name' => 'Prime Organics Ltd',
            'legal_name' => 'Prime Organics Private Limited',
            'seller_type' => SellerType::LOCAL_STORE,
            'status' => SellerStatus::APPROVED,
            'onboarding_step' => 7,
            'pan' => 'ABCDE1234F',
            'gstin' => '29ABCDE1234F1Z5',
        ]);

        $this->store = Store::factory()->create([
            'seller_id' => $this->seller->id,
            'name' => 'Prime Organics Store',
            'slug' => 'prime-organics-store',
            'status' => StoreStatus::ACTIVE,
        ]);

        $this->category = Category::factory()->create([
            'name' => 'Dairy & Breakfast',
            'slug' => 'dairy-breakfast',
            'is_active' => true,
        ]);

        $this->brand = Brand::factory()->create([
            'name' => 'Prime Pure',
            'slug' => 'prime-pure',
            'seller_id' => $this->seller->id,
            'is_verified' => true,
        ]);
    }

    public function test_seller_can_view_product_list_with_filters_and_search(): void
    {
        $product = Product::factory()->create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'title' => 'Pure A2 Cow Milk',
            'slug' => 'pure-a2-cow-milk',
            'status' => ProductStatus::ACTIVE,
            'product_type' => ProductType::SIMPLE,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'MILK-A2-500',
            'name' => '500ml Glass Bottle',
            'mrp' => 60.00,
            'selling_price' => 55.00,
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->sellerUser)
            ->get(route('seller.products.index'));

        $response->assertStatus(200);
        $response->assertSee('Pure A2 Cow Milk');
        $response->assertSee('MILK-A2-500');
        $response->assertSee('Products Catalogue');
        $response->assertSee('Add New Product');

        // Test search
        $searchResponse = $this->actingAs($this->sellerUser)
            ->get(route('seller.products.index', ['search' => 'MILK-A2']));

        $searchResponse->assertStatus(200);
        $searchResponse->assertSee('Pure A2 Cow Milk');
    }

    public function test_seller_cannot_view_or_touch_another_sellers_products(): void
    {
        // Another seller
        $otherUser = User::factory()->create();
        $otherUser->assignRole('seller_owner');
        $otherSeller = Seller::factory()->create(['user_id' => $otherUser->id]);

        $otherProduct = Product::factory()->create([
            'seller_id' => $otherSeller->id,
            'category_id' => $this->category->id,
            'title' => 'Rival Merchant Item',
        ]);

        $otherVariant = ProductVariant::factory()->create([
            'product_id' => $otherProduct->id,
            'sku' => 'RIVAL-001',
            'mrp' => 100.00,
            'selling_price' => 90.00,
        ]);

        // Attempting to edit other seller's product -> 403
        $this->actingAs($this->sellerUser)
            ->get(route('seller.products.edit', $otherProduct))
            ->assertForbidden();

        // Attempting to update other seller's product -> 403
        $this->actingAs($this->sellerUser)
            ->put(route('seller.products.update', $otherProduct), [
                'title' => 'Malicious Takeover',
                'category_id' => $this->category->id,
                'product_type' => 'simple',
                'simple_sku' => 'MAL-01',
                'simple_mrp' => 100.0,
                'simple_selling_price' => 90.0,
                'tax_percent' => 5.0,
                'return_window_days' => 7,
                'submit_action' => 'save_changes',
            ])
            ->assertForbidden();

        // Attempting to delete other seller's product -> 403
        $this->actingAs($this->sellerUser)
            ->delete(route('seller.products.destroy', $otherProduct))
            ->assertForbidden();

        // Attempting quick update on other seller's variant -> 403
        $this->actingAs($this->sellerUser)
            ->patchJson(route('seller.products.quick_update_variant', $otherVariant), [
                'selling_price' => 10.00,
            ])
            ->assertForbidden();
    }

    public function test_seller_can_create_simple_product_as_draft_and_submit_review(): void
    {
        // 1. Save as Draft
        $draftPayload = [
            'title' => 'Organic Fresh Paneer 200g',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'product_type' => 'simple',
            'short_desc' => 'Fresh buffalo milk paneer made daily.',
            'description' => '<p>Soft, fresh, and protein-packed paneer.</p>',
            'tax_percent' => 5.00,
            'return_window_days' => 2,
            'is_returnable' => '1',
            'is_cod_allowed' => '1',
            'is_quick_capable' => '1',
            'submit_action' => 'save_draft',
            'simple_sku' => 'PANEER-200G',
            'simple_mrp' => 120.00,
            'simple_selling_price' => 110.00,
            'simple_stock' => 40,
            'simple_weight_grams' => 200,
            'specifications' => [
                ['group' => 'General', 'key' => 'Shelf Life', 'value' => '7 Days'],
            ],
        ];

        $response = $this->actingAs($this->sellerUser)
            ->post(route('seller.products.store'), $draftPayload);

        $response->assertRedirect(route('seller.products.index'));
        $response->assertSessionHas('success');

        $draftProduct = Product::where('title', 'Organic Fresh Paneer 200g')->first();
        $this->assertNotNull($draftProduct);
        $this->assertEquals(ProductStatus::DRAFT, $draftProduct->status);
        $this->assertEquals($this->seller->id, $draftProduct->seller_id);
        $this->assertTrue($draftProduct->is_quick_capable);

        $variant = $draftProduct->variants()->first();
        $this->assertNotNull($variant);
        $this->assertEquals('PANEER-200G', $variant->sku);
        $this->assertEquals(110.00, (float) $variant->selling_price);

        // Check Inventory record created
        $inventory = Inventory::where('variant_id', $variant->id)->first();
        $this->assertNotNull($inventory);
        $this->assertEquals(40, $inventory->quantity_on_hand);
        $this->assertTrue((bool) $inventory->is_quick_available);

        // 2. Submit for Review
        $reviewPayload = array_merge($draftPayload, [
            'title' => 'Pure Desi Cow Ghee 500ml',
            'simple_sku' => 'GHEE-500ML',
            'submit_action' => 'submit_review',
        ]);

        $response2 = $this->actingAs($this->sellerUser)
            ->post(route('seller.products.store'), $reviewPayload);

        $response2->assertRedirect(route('seller.products.index'));

        $reviewProduct = Product::where('title', 'Pure Desi Cow Ghee 500ml')->first();
        $this->assertNotNull($reviewProduct);
        $this->assertEquals(ProductStatus::PENDING_REVIEW, $reviewProduct->status);
    }

    public function test_seller_can_create_variable_product_with_generated_variants(): void
    {
        $variablePayload = [
            'title' => 'Organic Honey Jar',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'product_type' => 'variable',
            'tax_percent' => 5.00,
            'return_window_days' => 7,
            'is_returnable' => '1',
            'is_cod_allowed' => '1',
            'is_quick_capable' => '1',
            'submit_action' => 'save_draft',
            'variants' => [
                [
                    'name' => 'Organic Honey - 250g',
                    'sku' => 'HONEY-250G',
                    'mrp' => 200.00,
                    'selling_price' => 180.00,
                    'stock' => 25,
                    'weight_grams' => 250,
                    'attributes' => ['Size' => '250g'],
                    'is_active' => '1',
                ],
                [
                    'name' => 'Organic Honey - 500g',
                    'sku' => 'HONEY-500G',
                    'mrp' => 380.00,
                    'selling_price' => 350.00,
                    'stock' => 15,
                    'weight_grams' => 500,
                    'attributes' => ['Size' => '500g'],
                    'is_active' => '1',
                ],
            ],
        ];

        $response = $this->actingAs($this->sellerUser)
            ->post(route('seller.products.store'), $variablePayload);

        $response->assertRedirect(route('seller.products.index'));

        $product = Product::where('title', 'Organic Honey Jar')->first();
        $this->assertNotNull($product);
        $this->assertEquals(ProductType::VARIABLE, $product->product_type);
        $this->assertCount(2, $product->variants);

        $v1 = $product->variants()->where('sku', 'HONEY-250G')->first();
        $v2 = $product->variants()->where('sku', 'HONEY-500G')->first();
        $this->assertNotNull($v1);
        $this->assertNotNull($v2);
        $this->assertEquals(180.00, (float) $v1->selling_price);
        $this->assertEquals(350.00, (float) $v2->selling_price);

        // Verify inventory entries
        $this->assertEquals(25, Inventory::where('variant_id', $v1->id)->value('quantity_on_hand'));
        $this->assertEquals(15, Inventory::where('variant_id', $v2->id)->value('quantity_on_hand'));
    }

    public function test_selling_price_cannot_exceed_mrp_validation(): void
    {
        // Simple product with selling_price > mrp
        $invalidPayload = [
            'title' => 'Invalid Pricing Product',
            'category_id' => $this->category->id,
            'product_type' => 'simple',
            'tax_percent' => 5.00,
            'return_window_days' => 7,
            'submit_action' => 'save_draft',
            'simple_sku' => 'INVALID-001',
            'simple_mrp' => 100.00,
            'simple_selling_price' => 120.00, // Invalid!
        ];

        $response = $this->actingAs($this->sellerUser)
            ->post(route('seller.products.store'), $invalidPayload);

        $response->assertSessionHasErrors(['simple_selling_price']);
    }

    public function test_editing_approved_product_sensitive_fields_retriggers_review(): void
    {
        $product = Product::factory()->create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'title' => 'Approved Herbal Tea',
            'status' => ProductStatus::ACTIVE,
            'product_type' => ProductType::SIMPLE,
            'tax_percent' => 5.00,
            'return_window_days' => 7,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'TEA-001',
            'mrp' => 150.00,
            'selling_price' => 130.00,
            'is_default' => true,
        ]);

        // 1. Non-sensitive update (updating dimensions/weight) -> stays ACTIVE
        $nonSensitivePayload = [
            'title' => 'Approved Herbal Tea',
            'category_id' => $this->category->id,
            'brand_id' => $product->brand_id,
            'product_type' => 'simple',
            'tax_percent' => 5.00,
            'return_window_days' => 7,
            'is_returnable' => '1',
            'hsn_code' => $product->hsn_code,
            'description' => $product->description,
            'simple_sku' => 'TEA-001',
            'simple_mrp' => 150.00,
            'simple_selling_price' => 130.00,
            'simple_weight_grams' => 300, // Non-sensitive change
            'simple_length' => 12.0,
            'submit_action' => 'save_changes',
        ];

        $response = $this->actingAs($this->sellerUser)
            ->put(route('seller.products.update', $product), $nonSensitivePayload);

        $response->assertRedirect(route('seller.products.edit', $product));
        $this->assertEquals(ProductStatus::ACTIVE, $product->fresh()->status);

        // 2. Sensitive update (changing selling price from 130 to 140) -> transitions to PENDING_REVIEW
        $sensitivePayload = array_merge($nonSensitivePayload, [
            'simple_selling_price' => 140.00,
        ]);

        $response2 = $this->actingAs($this->sellerUser)
            ->put(route('seller.products.update', $product), $sensitivePayload);

        $response2->assertRedirect(route('seller.products.edit', $product));
        $response2->assertSessionHas('info'); // Retriggered review notice
        $this->assertEquals(ProductStatus::PENDING_REVIEW, $product->fresh()->status);
    }

    public function test_inline_quick_update_of_price_and_stock(): void
    {
        $product = Product::factory()->create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'title' => 'Quick Restock Item',
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'RESTOCK-01',
            'mrp' => 200.00,
            'selling_price' => 180.00,
        ]);

        $location = InventoryLocation::create([
            'seller_id' => $this->seller->id,
            'name' => 'Test Hub',
            'type' => 'store',
            'is_active' => true,
        ]);

        Inventory::create([
            'variant_id' => $variant->id,
            'location_id' => $location->id,
            'quantity_on_hand' => 10,
        ]);

        // Quick edit price and stock
        $response = $this->actingAs($this->sellerUser)
            ->patchJson(route('seller.products.quick_update_variant', $variant), [
                'mrp' => 220.00,
                'selling_price' => 195.00,
                'quantity_on_hand' => 85,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'variant' => [
                    'mrp' => '220.00',
                    'selling_price' => '195.00',
                    'stock' => 85,
                ],
            ]);

        $this->assertEquals(195.00, (float) $variant->fresh()->selling_price);
        $this->assertEquals(85, Inventory::where('variant_id', $variant->id)->value('quantity_on_hand'));
    }

    public function test_bulk_actions_activate_deactivate_delete(): void
    {
        $p1 = Product::factory()->create(['seller_id' => $this->seller->id, 'category_id' => $this->category->id, 'status' => ProductStatus::ACTIVE]);
        $p2 = Product::factory()->create(['seller_id' => $this->seller->id, 'category_id' => $this->category->id, 'status' => ProductStatus::ACTIVE]);

        // 1. Deactivate
        $response = $this->actingAs($this->sellerUser)
            ->post(route('seller.products.bulk_action'), [
                'action' => 'deactivate',
                'product_ids' => [$p1->id, $p2->id],
            ]);

        $response->assertRedirect();
        $this->assertEquals(ProductStatus::INACTIVE, $p1->fresh()->status);
        $this->assertEquals(ProductStatus::INACTIVE, $p2->fresh()->status);

        // 2. Delete
        $response2 = $this->actingAs($this->sellerUser)
            ->post(route('seller.products.bulk_action'), [
                'action' => 'delete',
                'product_ids' => [$p1->id],
            ]);

        $response2->assertRedirect();
        $this->assertSoftDeleted('products', ['id' => $p1->id]);
        $this->assertDatabaseHas('products', ['id' => $p2->id]);
    }

    public function test_sku_live_availability_check(): void
    {
        ProductVariant::factory()->create([
            'sku' => 'EXISTING-SKU-99',
            'mrp' => 100,
            'selling_price' => 90,
        ]);

        // Taken SKU
        $resTaken = $this->actingAs($this->sellerUser)
            ->postJson(route('seller.products.check_sku'), ['sku' => 'EXISTING-SKU-99']);

        $resTaken->assertStatus(200)
            ->assertJson(['available' => false]);

        // Available SKU
        $resAvail = $this->actingAs($this->sellerUser)
            ->postJson(route('seller.products.check_sku'), ['sku' => 'BRAND-NEW-SKU-01']);

        $resAvail->assertStatus(200)
            ->assertJson(['available' => true]);
    }

    public function test_export_csv(): void
    {
        Product::factory()->create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'title' => 'Exportable Ghee',
        ]);

        $response = $this->actingAs($this->sellerUser)
            ->get(route('seller.products.export'));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
    }
}
