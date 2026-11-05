<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Enums\SellerType;
use App\Enums\StoreStatus;
use App\Models\Customer;
use App\Models\FulfilmentOrder;
use App\Models\Inventory;
use App\Models\InventoryLocation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\Seller;
use App\Models\SellerBankAccount;
use App\Models\Store;
use App\Models\StoreLocation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerDashboardTest extends TestCase
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
            'email' => 'merchant.boss@onestall.in',
            'phone' => '+919876543299',
            'phone_verified_at' => now(),
            'email_verified_at' => now(),
        ]);
        $this->sellerUser->assignRole('seller_owner');

        $this->seller = Seller::factory()->create([
            'user_id' => $this->sellerUser->id,
            'business_name' => 'Metro Supermart LLP',
            'legal_name' => 'Metro Supermart Private Limited',
            'seller_type' => SellerType::LOCAL_STORE,
            'status' => SellerStatus::APPROVED,
            'onboarding_step' => 7,
            'pan' => 'ABCDE1234F',
            'gstin' => '29ABCDE1234F1Z5',
            'approved_at' => now(),
        ]);

        $this->store = Store::factory()->create([
            'seller_id' => $this->seller->id,
            'name' => 'Metro Indiranagar Branch',
            'slug' => 'metro-indiranagar-branch',
            'status' => StoreStatus::ACTIVE,
            'accepts_quick_commerce' => true,
            'accepts_pickup' => true,
        ]);

        StoreLocation::factory()->create([
            'store_id' => $this->store->id,
            'address' => '100ft Road, HAL 2nd Stage, Indiranagar',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560038',
            'lat' => 12.9716,
            'lng' => 77.5946,
        ]);

        SellerBankAccount::factory()->create([
            'seller_id' => $this->seller->id,
            'account_holder' => 'Metro Supermart Private Limited',
            'account_no' => '50100445566778',
            'ifsc' => 'HDFC0001234',
            'bank_name' => 'HDFC Bank',
            'is_primary' => true,
        ]);
    }

    public function test_guest_is_redirected_to_seller_login(): void
    {
        $response = $this->get(route('seller.dashboard'));
        $response->assertRedirect(route('seller.login'));
    }

    public function test_pending_seller_is_redirected_to_onboarding(): void
    {
        $this->seller->update(['status' => SellerStatus::PENDING]);

        $response = $this->actingAs($this->sellerUser)->get(route('seller.dashboard'));
        $response->assertRedirect(route('seller.onboarding'));
    }

    public function test_approved_seller_can_access_dashboard_with_all_kpis_and_widgets(): void
    {
        // Create an inventory location and product
        $location = InventoryLocation::factory()->create([
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'name' => 'Indiranagar Floor Shelf',
        ]);

        $product = Product::factory()->create([
            'seller_id' => $this->seller->id,
            'title' => 'Tata Sampann Toor Dal 1kg',
            'status' => ProductStatus::ACTIVE,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'DAL-TOOR-1KG',
            'selling_price' => 175.00,
        ]);

        // Create a low-stock inventory record
        $inventory = Inventory::factory()->create([
            'variant_id' => $variant->id,
            'location_id' => $location->id,
            'quantity_on_hand' => 4,
            'quantity_reserved' => 1,
            'low_stock_threshold' => 10,
        ]);

        // Create an urgent order
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $fulfilment = FulfilmentOrder::factory()->create([
            'order_id' => $order->id,
            'fulfilment_order_number' => 'FO-TEST-8899',
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'placed',
            'seller_payable' => 350.00,
        ]);

        OrderItem::factory()->create([
            'fulfilment_order_id' => $fulfilment->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'title' => 'Tata Sampann Toor Dal 1kg',
            'qty' => 2,
            'price' => 175.00,
            'total' => 350.00,
        ]);

        $response = $this->actingAs($this->sellerUser)->get(route('seller.dashboard'));

        $response->assertStatus(200);

        // Header & Core title
        $response->assertSee('Merchant Operations Hub');

        // All 8 KPI Card Labels
        $response->assertSee("Today's Sales");
        $response->assertSee('Total Orders');
        $response->assertSee('Quick Orders');
        $response->assertSee('Online Orders');
        $response->assertSee('Pending Orders');
        $response->assertSee('Delivered Orders');
        $response->assertSee('Returns');
        $response->assertSee('Low Stock Items');

        // Widgets
        $response->assertSee('Orders Needing Immediate Action');
        $response->assertSee('#FO-TEST-8899');
        $response->assertSee('Low Stock Warning');
        $response->assertSee('Tata Sampann Toor Dal 1kg');
        $response->assertSee('DAL-TOOR-1KG');
        $response->assertSee('Settlements & Payouts', false);
        $response->assertSee('Customer Feedback');
        $response->assertSee('Merchant Readiness');
        $response->assertSee('Platform Notices');
    }

    public function test_chart_data_endpoint_returns_json_structure(): void
    {
        $response = $this->actingAs($this->sellerUser)->getJson(route('seller.chart_data', ['range' => '30d']));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'sales_trend' => ['labels', 'sales', 'orders'],
            'channel' => ['labels', 'data', 'colors'],
            'top_products' => ['labels', 'data'],
            'is_multi_store',
            'revenue_by_store',
        ]);
    }

    public function test_multi_store_switcher_scopes_dashboard_data(): void
    {
        // Add a second store for this merchant
        $secondStore = Store::factory()->create([
            'seller_id' => $this->seller->id,
            'name' => 'Metro Whitefield Dark Store',
            'slug' => 'metro-whitefield-dark-store',
            'status' => StoreStatus::ACTIVE,
        ]);

        // Switch context to second store
        $switchResponse = $this->actingAs($this->sellerUser)->post(route('seller.switch_store'), [
            'store_id' => $secondStore->id,
        ]);

        $switchResponse->assertSessionHas('seller_active_store_id', $secondStore->id);
        $switchResponse->assertSessionHas('success');

        // Accessing dashboard now reflects scoped store
        $dashResponse = $this->actingAs($this->sellerUser)->get(route('seller.dashboard'));
        $dashResponse->assertStatus(200);
        $dashResponse->assertSee('Metro Whitefield Dark Store');

        // Reset to all stores
        $resetResponse = $this->actingAs($this->sellerUser)->post(route('seller.switch_store'), [
            'store_id' => 'all',
        ]);
        $resetResponse->assertSessionMissing('seller_active_store_id');
    }

    public function test_store_status_toggle_opens_and_closes_store(): void
    {
        $this->assertEquals(StoreStatus::ACTIVE, $this->store->fresh()->status);

        // Toggle to Inactive / Closed
        $toggleResponse = $this->actingAs($this->sellerUser)->post(route('seller.store.toggle_status'), [
            'store_id' => $this->store->id,
        ]);

        $toggleResponse->assertRedirect();
        $this->assertEquals(StoreStatus::INACTIVE, $this->store->fresh()->status);

        // Toggle back to Active / Open
        $this->actingAs($this->sellerUser)->post(route('seller.store.toggle_status'), [
            'store_id' => $this->store->id,
        ]);

        $this->assertEquals(StoreStatus::ACTIVE, $this->store->fresh()->status);
    }

    public function test_seller_can_execute_inline_order_actions(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $fulfilment = FulfilmentOrder::factory()->create([
            'order_id' => $order->id,
            'fulfilment_order_number' => 'FO-ACTION-001',
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'fulfilment_type' => FulfilmentType::QUICK,
            'status' => 'placed',
        ]);

        // 1. Accept Order
        $acceptResp = $this->actingAs($this->sellerUser)->post(route('seller.orders.accept', $fulfilment));
        $acceptResp->assertRedirect();
        $this->assertEquals('store_accepted', $fulfilment->fresh()->status);

        // 2. Pack Order
        $packResp = $this->actingAs($this->sellerUser)->post(route('seller.orders.pack', $fulfilment));
        $packResp->assertRedirect();
        $this->assertEquals('packing', $fulfilment->fresh()->status);

        // 3. Ready for Handover
        $readyResp = $this->actingAs($this->sellerUser)->post(route('seller.orders.ready', $fulfilment));
        $readyResp->assertRedirect();
        $this->assertEquals('ready', $fulfilment->fresh()->status);
    }

    public function test_seller_can_quick_restock_low_stock_inventory(): void
    {
        $location = InventoryLocation::factory()->create([
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
        ]);

        $product = Product::factory()->create(['seller_id' => $this->seller->id]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $inventory = Inventory::factory()->create([
            'variant_id' => $variant->id,
            'location_id' => $location->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
            'low_stock_threshold' => 15,
        ]);

        $response = $this->actingAs($this->sellerUser)->post(route('seller.inventory.quick_restock', $inventory), [
            'added_quantity' => 45,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals(50, $inventory->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('stock_movements', [
            'inventory_id' => $inventory->id,
            'quantity' => 45,
        ]);
    }

    public function test_seller_cannot_act_on_another_sellers_orders_or_inventory(): void
    {
        $otherUser = User::factory()->create();
        $otherUser->assignRole('seller_owner');
        $otherSeller = Seller::factory()->create(['user_id' => $otherUser->id]);

        $otherCustomer = Customer::factory()->create();
        $otherOrder = Order::factory()->create(['customer_id' => $otherCustomer->id]);
        $otherFulfilment = FulfilmentOrder::factory()->create([
            'order_id' => $otherOrder->id,
            'seller_id' => $otherSeller->id,
            'status' => 'placed',
        ]);

        // Attempting to accept another seller's order must return 403
        $orderResp = $this->actingAs($this->sellerUser)->post(route('seller.orders.accept', $otherFulfilment));
        $orderResp->assertStatus(403);

        // Attempting to restock another seller's inventory must return 403
        $otherLocation = InventoryLocation::factory()->create(['seller_id' => $otherSeller->id]);
        $otherProduct = Product::factory()->create(['seller_id' => $otherSeller->id]);
        $otherVariant = ProductVariant::factory()->create(['product_id' => $otherProduct->id]);
        $otherInventory = Inventory::factory()->create([
            'variant_id' => $otherVariant->id,
            'location_id' => $otherLocation->id,
        ]);

        $inventoryResp = $this->actingAs($this->sellerUser)->post(route('seller.inventory.quick_restock', $otherInventory), [
            'added_quantity' => 20,
        ]);
        $inventoryResp->assertStatus(403);
    }
}
