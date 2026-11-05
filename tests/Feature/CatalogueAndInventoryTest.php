<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InventoryLocationType;
use App\Enums\MediaType;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\ReservationStatus;
use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryLocation;
use App\Models\InventoryReservation;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductMedia;
use App\Models\ProductSpecification;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueAndInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_nested_hierarchy_and_scopes(): void
    {
        $parent = Category::factory()->create([
            'name' => 'Electronics',
            'parent_id' => null,
            'is_quick_eligible' => false,
            'is_active' => true,
        ]);

        $child1 = Category::factory()->create([
            'name' => 'Mobile Phones',
            'parent_id' => $parent->id,
            'is_quick_eligible' => true,
            'is_active' => true,
        ]);

        $child2 = Category::factory()->create([
            'name' => 'Laptops',
            'parent_id' => $parent->id,
            'is_quick_eligible' => false,
            'is_active' => false,
        ]);

        $this->assertCount(2, $parent->children);
        $this->assertEquals('Electronics', $child1->parent->name);

        $activeCategories = Category::active()->get();
        $this->assertTrue($activeCategories->contains($parent));
        $this->assertTrue($activeCategories->contains($child1));
        $this->assertFalse($activeCategories->contains($child2));

        $quickCategories = Category::quickEligible()->get();
        $this->assertTrue($quickCategories->contains($child1));
        $this->assertFalse($quickCategories->contains($parent));
    }

    public function test_brands_and_verified_scope(): void
    {
        $seller = Seller::factory()->create();
        $verifiedBrand = Brand::factory()->create([
            'name' => 'Sony',
            'is_verified' => true,
            'seller_id' => $seller->id,
        ]);

        $unverifiedBrand = Brand::factory()->create([
            'name' => 'GenericLocal',
            'is_verified' => false,
        ]);

        $this->assertEquals($seller->id, $verifiedBrand->seller->id);
        $this->assertCount(1, Brand::verified()->where('id', $verifiedBrand->id)->get());
        $this->assertCount(0, Brand::verified()->where('id', $unverifiedBrand->id)->get());
    }

    public function test_product_and_variants_with_attributes_and_pricing(): void
    {
        $seller = Seller::factory()->create();
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();

        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'title' => 'OnePlus 12 5G',
            'status' => ProductStatus::ACTIVE,
            'product_type' => ProductType::VARIABLE,
            'tax_percent' => 18.00,
            'is_quick_capable' => true,
        ]);

        $variant1 = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'OP12-16-512-BLK',
            'name' => 'Silky Black 16GB/512GB',
            'attributes' => ['color' => 'Silky Black', 'ram' => '16GB', 'storage' => '512GB'],
            'mrp' => 69999.00,
            'selling_price' => 64999.00,
            'cost_price' => 58000.00,
            'is_default' => true,
        ]);

        $variant2 = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'OP12-12-256-GRN',
            'name' => 'Flowy Emerald 12GB/256GB',
            'attributes' => ['color' => 'Flowy Emerald', 'ram' => '12GB', 'storage' => '256GB'],
            'mrp' => 64999.00,
            'selling_price' => 59999.00,
            'cost_price' => 53000.00,
            'is_default' => false,
        ]);

        // Assert relationships & casts
        $this->assertCount(2, $product->variants);
        $this->assertEquals($variant1->id, $product->defaultVariant->id);
        $this->assertIsArray($variant1->attributes);
        $this->assertEquals('Silky Black', $variant1->attributes['color']);
        $this->assertSame('64999.00', (string) $variant1->selling_price);

        // Add media & specifications
        $media = ProductMedia::factory()->create([
            'product_id' => $product->id,
            'variant_id' => $variant1->id,
            'type' => MediaType::IMAGE,
            'path' => 'products/oneplus12.png',
            'sort_order' => 1,
        ]);

        $spec = ProductSpecification::factory()->create([
            'product_id' => $product->id,
            'group' => 'Processor',
            'key' => 'Chipset',
            'value' => 'Snapdragon 8 Gen 3',
        ]);

        $this->assertCount(1, $product->media);
        $this->assertEquals(MediaType::IMAGE, $product->media->first()->type);
        $this->assertCount(1, $product->specifications);
        $this->assertEquals('Snapdragon 8 Gen 3', $product->specifications->first()->value);
    }

    public function test_product_attributes_and_values(): void
    {
        $colorAttr = ProductAttribute::factory()->create(['name' => 'Color', 'slug' => 'color']);
        $redVal = ProductAttributeValue::factory()->create([
            'product_attribute_id' => $colorAttr->id,
            'value' => 'Crimson Red',
            'code' => '#DC143C',
        ]);

        $this->assertCount(1, $colorAttr->values);
        $this->assertEquals('Color', $redVal->attribute->name);
    }

    public function test_inventory_separation_available_quantity_and_scopes(): void
    {
        $seller = Seller::factory()->create();
        $store = Store::factory()->create(['seller_id' => $seller->id]);
        $variant = ProductVariant::factory()->create();

        // Location 1: Quick-commerce dark store
        $darkStoreLocation = InventoryLocation::factory()->create([
            'seller_id' => $seller->id,
            'store_id' => $store->id,
            'type' => InventoryLocationType::STORE,
            'name' => 'Koramangala Dark Store',
            'is_active' => true,
        ]);

        // Location 2: Regional Warehouse
        $warehouseLocation = InventoryLocation::factory()->create([
            'seller_id' => $seller->id,
            'store_id' => null,
            'type' => InventoryLocationType::WAREHOUSE,
            'name' => 'Bengaluru Central Hub',
            'is_active' => true,
        ]);

        // Inventory item 1: In Stock & Quick Available (On hand: 50, Reserved: 10 => Available: 40, Threshold: 15)
        $inv1 = Inventory::create([
            'variant_id' => $variant->id,
            'location_id' => $darkStoreLocation->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 10,
            'low_stock_threshold' => 15,
            'is_quick_available' => true,
        ]);

        // Inventory item 2: Low Stock & Not Quick (On hand: 6, Reserved: 2 => Available: 4, Threshold: 10)
        $inv2 = Inventory::create([
            'variant_id' => $variant->id,
            'location_id' => $warehouseLocation->id,
            'quantity_on_hand' => 6,
            'quantity_reserved' => 2,
            'low_stock_threshold' => 10,
            'is_quick_available' => false,
        ]);

        // Check computed accessor: available_quantity = on_hand - reserved
        $this->assertEquals(40, $inv1->available_quantity);
        $this->assertEquals(4, $inv2->available_quantity);

        // Check Scopes
        // inStock: (on_hand - reserved) > 0
        $inStock = Inventory::inStock()->pluck('id');
        $this->assertTrue($inStock->contains($inv1->id));
        $this->assertTrue($inStock->contains($inv2->id));

        // quickAvailable: is_quick_available = true AND inStock
        $quickAvailable = Inventory::quickAvailable()->pluck('id');
        $this->assertTrue($quickAvailable->contains($inv1->id));
        $this->assertFalse($quickAvailable->contains($inv2->id));

        // lowStock: (on_hand - reserved) <= low_stock_threshold
        $lowStock = Inventory::lowStock()->pluck('id');
        $this->assertFalse($lowStock->contains($inv1->id)); // 40 > 15
        $this->assertTrue($lowStock->contains($inv2->id));  // 4 <= 10

        // Test Out of Stock behavior:
        $inv1->update(['quantity_reserved' => 50]); // 50 on hand, 50 reserved => 0 available
        $this->assertEquals(0, $inv1->fresh()->available_quantity);
        $this->assertFalse(Inventory::inStock()->where('id', $inv1->id)->exists());
    }

    public function test_inventory_unique_variant_and_location_constraint(): void
    {
        $variant = ProductVariant::factory()->create();
        $location = InventoryLocation::factory()->create();

        Inventory::create([
            'variant_id' => $variant->id,
            'location_id' => $location->id,
            'quantity_on_hand' => 25,
            'quantity_reserved' => 0,
            'low_stock_threshold' => 5,
            'is_quick_available' => true,
        ]);

        $this->expectException(QueryException::class);

        // Duplicate variant and location must throw unique constraint exception
        Inventory::create([
            'variant_id' => $variant->id,
            'location_id' => $location->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'low_stock_threshold' => 5,
            'is_quick_available' => false,
        ]);
    }

    public function test_stock_movements_and_inventory_reservations(): void
    {
        $user = User::factory()->create();
        $inv = Inventory::factory()->create([
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
        ]);

        // Stock movement: Purchase inward
        $movement = StockMovement::create([
            'inventory_id' => $inv->id,
            'type' => StockMovementType::PURCHASE,
            'quantity' => 100,
            'reference_type' => null,
            'reference_id' => null,
            'note' => 'Initial batch stock purchase',
            'created_by' => $user->id,
        ]);

        $this->assertEquals(StockMovementType::PURCHASE, $movement->type);
        $this->assertEquals($user->id, $movement->creator->id);
        $this->assertCount(1, $inv->movements);

        // Inventory reservation
        $reservation = InventoryReservation::create([
            'inventory_id' => $inv->id,
            'order_ref' => 'ORD-2026-9901',
            'cart_ref' => 'CART-2026-5544',
            'quantity' => 3,
            'expires_at' => now()->addMinutes(15),
            'status' => ReservationStatus::ACTIVE,
        ]);

        $this->assertEquals(ReservationStatus::ACTIVE, $reservation->status);
        $this->assertCount(1, $inv->reservations);
        $this->assertTrue(InventoryReservation::active()->where('id', $reservation->id)->exists());

        // Expired reservation should not be in active scope
        $expiredReservation = InventoryReservation::create([
            'inventory_id' => $inv->id,
            'order_ref' => null,
            'cart_ref' => 'CART-EXPIRED',
            'quantity' => 2,
            'expires_at' => now()->subMinute(),
            'status' => ReservationStatus::ACTIVE,
        ]);

        $this->assertFalse(InventoryReservation::active()->where('id', $expiredReservation->id)->exists());
    }
}
