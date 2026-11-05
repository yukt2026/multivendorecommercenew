<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InventoryLocationType;
use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Enums\StoreConnectionType;
use App\Enums\StoreStaffRole;
use App\Enums\StoreStatus;
use App\Jobs\Integration\ImportCatalogJob;
use App\Jobs\Integration\SyncShopifyProductsJob;
use App\Jobs\Integration\SyncWoocommerceProductsJob;
use App\Models\CatalogImport;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\FulfilmentOrder;
use App\Models\Inventory;
use App\Models\InventoryLocation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Store;
use App\Models\StoreConnection;
use App\Models\StoreStaff;
use App\Models\SyncLog;
use App\Models\User;
use App\Services\Integration\CatalogImportService;
use App\Services\Integration\ConnectShopService;
use App\Services\Seller\StoreService;
use App\Services\Seller\StoreStaffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MultiStoreAndConnectShopTest extends TestCase
{
    use RefreshDatabase;

    protected User $sellerUser;
    protected Seller $seller;
    protected Store $store1;
    protected StoreService $storeService;
    protected StoreStaffService $staffService;
    protected ConnectShopService $connectService;
    protected CatalogImportService $importService;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'seller_owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'seller_staff', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->storeService = app(StoreService::class);
        $this->staffService = app(StoreStaffService::class);
        $this->connectService = app(ConnectShopService::class);
        $this->importService = app(CatalogImportService::class);

        // Create seller owner
        $this->sellerUser = User::factory()->create([
            'name' => 'Sunil Rao',
            'email' => 'sunil@zenithretail.in',
            'phone' => '9888877771',
        ]);
        $this->sellerUser->assignRole('seller');

        $this->seller = Seller::create([
            'user_id' => $this->sellerUser->id,
            'business_name' => 'Zenith Retail Superstores',
            'legal_name' => 'Zenith Retail Superstores Private Limited',
            'seller_type' => \App\Enums\SellerType::MULTI_STORE,
            'status' => SellerStatus::APPROVED,
            'onboarding_step' => 7,
        ]);

        // Default Store 1 (Indiranagar)
        $this->store1 = $this->storeService->createStore($this->seller, [
            'name' => 'Zenith Supermarket - Indiranagar',
            'slug' => 'zenith-indiranagar',
            'address' => 'Plot 45, 100ft Road, Indiranagar',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560038',
            'lat' => 12.9784,
            'lng' => 77.6408,
            'delivery_radius_km' => 8.0,
            'accepts_quick_commerce' => true,
        ]);
    }

    public function test_multi_store_creation_auto_creates_inventory_location_and_timings(): void
    {
        $this->actingAs($this->sellerUser);

        $response = $this->post(route('seller.stores.store'), [
            'name' => 'Zenith Quick Hub - Whitefield',
            'slug' => 'zenith-whitefield',
            'address' => 'ITPL Main Road',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560066',
            'lat' => 12.9864,
            'lng' => 77.7314,
            'delivery_radius_km' => 10.0,
            'accepts_quick_commerce' => true,
            'accepts_pickup' => true,
            'opens_at' => '08:00',
            'closes_at' => '23:00',
        ]);

        $response->assertRedirect(route('seller.stores.index'));

        $this->assertDatabaseHas('stores', [
            'seller_id' => $this->seller->id,
            'name' => 'Zenith Quick Hub - Whitefield',
            'slug' => 'zenith-whitefield',
            'delivery_radius_km' => 10.0,
        ]);

        $newStore = Store::where('slug', 'zenith-whitefield')->first();
        $this->assertNotNull($newStore);

        // Dedicated InventoryLocation created
        $this->assertDatabaseHas('inventory_locations', [
            'seller_id' => $this->seller->id,
            'store_id' => $newStore->id,
            'type' => InventoryLocationType::STORE->value,
        ]);

        // Timings created for all 7 days
        $this->assertEquals(7, $newStore->timings()->count());

        // Seller owns both stores
        $this->assertEquals(2, $this->seller->stores()->count());
    }

    public function test_store_switcher_toggles_session_scope(): void
    {
        $this->actingAs($this->sellerUser);

        $store2 = $this->storeService->createStore($this->seller, [
            'name' => 'Zenith Express - Koramangala',
            'slug' => 'zenith-koramangala',
            'address' => '5th Block Koramangala',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560034',
            'lat' => 12.9352,
            'lng' => 77.6245,
        ]);

        // Switch to Store 2
        $res = $this->post(route('seller.switch_store'), [
            'store_id' => $store2->id,
        ]);

        $res->assertSessionHas('seller_active_store_id', $store2->id);

        // Switch back to "All Outlets"
        $resAll = $this->post(route('seller.switch_store'), [
            'store_id' => 'all',
        ]);

        $resAll->assertSessionMissing('seller_active_store_id');
    }

    public function test_store_level_reports_analytics_calculation(): void
    {
        $this->actingAs($this->sellerUser);

        // Create Customer & Address
        $customerUser = User::factory()->create();
        $customer = Customer::create(['user_id' => $customerUser->id]);
        $addr = CustomerAddress::create([
            'user_id' => $customerUser->id,
            'customer_id' => $customer->id,
            'name' => 'Customer',
            'phone' => '9999988888',
            'line1' => 'MG Road',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560001',
        ]);

        // Category & Product
        $cat = Category::create(['name' => 'Bakery', 'slug' => 'bakery', 'is_active' => true]);
        $product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $cat->id,
            'title' => 'Artisan Sourdough Loaf',
            'slug' => 'artisan-sourdough-loaf',
            'status' => ProductStatus::ACTIVE,
            'is_active' => true,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SOURDOUGH-01',
            'name' => 'Whole Wheat',
            'selling_price' => 250.00,
            'mrp' => 300.00,
            'is_active' => true,
        ]);

        // Create Parent Order & Fulfilment Order
        $order = Order::create([
            'order_number' => 'ORD-REP-01',
            'customer_id' => $customer->id,
            'customer_address_id' => $addr->id,
            'address_snapshot' => [
                'name' => 'Customer',
                'line1' => 'MG Road',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'pincode' => '560001',
            ],
            'subtotal' => 500.00,
            'total' => 500.00,
            'status' => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'upi',
        ]);

        $fo = FulfilmentOrder::create([
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'store_id' => $this->store1->id,
            'fulfilment_order_number' => 'FO-REP-01',
            'fulfilment_type' => \App\Enums\FulfilmentType::QUICK,
            'status' => 'delivered',
            'subtotal' => 500.00,
            'delivery_fee' => 0.00,
            'delivered_at' => now(),
        ]);

        OrderItem::create([
            'fulfilment_order_id' => $fo->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'title' => $product->title,
            'sku' => $variant->sku,
            'price' => 250.00,
            'qty' => 2,
            'total' => 500.00,
        ]);

        $report = $this->storeService->getStoreReports($this->seller, $this->store1->id, '30d');

        $this->assertEquals(500.00, $report['gross_sales']);
        $this->assertEquals(1, $report['delivered_orders']);
        $this->assertEquals(500.00, $report['aov']);
        $this->assertEquals(1, $report['quick_orders_count']);
        $this->assertCount(1, $report['top_items']);
        $this->assertEquals('Artisan Sourdough Loaf', $report['top_items']->first()->title);

        $response = $this->get(route('seller.stores.reports', ['store_id' => $this->store1->id]));
        $response->assertOk();
        $response->assertSee('₹500.00');
    }

    public function test_staff_invitation_and_permission_matrix(): void
    {
        $this->actingAs($this->sellerUser);

        // Invite Staff Member as "packing" role
        $response = $this->post(route('seller.staff.store'), [
            'store_id' => $this->store1->id,
            'name' => 'Kiran Packaging Specialist',
            'email' => 'kiran.pack@example.com',
            'phone' => '9876500001',
            'role' => StoreStaffRole::PACKING->value,
            'permissions' => ['orders.view', 'orders.manage', 'orders.pack', 'packing.manage'],
        ]);

        $response->assertRedirect(route('seller.staff.index'));

        $this->assertDatabaseHas('users', ['email' => 'kiran.pack@example.com']);
        $staffUser = User::where('email', 'kiran.pack@example.com')->first();
        $this->assertNotNull($staffUser);

        $this->assertDatabaseHas('store_staff', [
            'store_id' => $this->store1->id,
            'user_id' => $staffUser->id,
            'role' => StoreStaffRole::PACKING->value,
            'status' => 'active',
        ]);

        // Verify granular permission enforcement on user
        $this->assertTrue($staffUser->hasStorePermission('orders.view', $this->store1));
        $this->assertTrue($staffUser->hasStorePermission('orders.pack', $this->store1));
        $this->assertFalse($staffUser->hasStorePermission('staff.manage', $this->store1));
        $this->assertFalse($staffUser->hasStorePermission('store.settings', $this->store1));

        // Update permissions via staff update endpoint
        $staffRecord = StoreStaff::where('user_id', $staffUser->id)->first();
        $this->put(route('seller.staff.update', $staffRecord), [
            'role' => StoreStaffRole::STORE_MANAGER->value,
            'permissions' => StoreStaffRole::STORE_MANAGER->defaultPermissions(),
        ])->assertRedirect(route('seller.staff.index'));

        $staffUser->refresh();
        $this->assertTrue($staffUser->hasStorePermission('staff.manage', $this->store1));

        // Revoke staff
        $this->delete(route('seller.staff.destroy', $staffRecord))
            ->assertRedirect(route('seller.staff.index'));

        $this->assertDatabaseMissing('store_staff', ['id' => $staffRecord->id]);
    }

    public function test_csv_upload_dry_run_and_queued_import(): void
    {
        Storage::fake('local');
        Queue::fake();

        $this->actingAs($this->sellerUser);

        $csvContent = "SKU,Title,Category,Selling Price,MRP,Quantity,Weight,Description\n"
            . "TSHIRT-BLK-M,Men Pure Cotton Black T-Shirt,Fashion,799.00,1299.00,40,350,100% Cotton\n"
            . "MUG-CER-01,Handcrafted Ceramic Coffee Mug,Home,399.00,599.00,15,400,Microwave safe\n";

        $file = UploadedFile::fake()->createWithContent('products_catalog.csv', $csvContent);

        // Upload and Preview
        $response = $this->post(route('seller.connections.csv.upload'), [
            'store_id' => $this->store1->id,
            'csv_file' => $file,
        ]);

        $response->assertOk();
        $response->assertSee('Men Pure Cotton Black T-Shirt');

        $import = CatalogImport::where('store_id', $this->store1->id)->first();
        $this->assertNotNull($import);
        $this->assertEquals(2, $import->total_rows);

        // Execute bulk import
        $importResponse = $this->post(route('seller.connections.csv.import', $import), [
            'mapping' => [
                'sku' => 'SKU',
                'title' => 'Title',
                'category' => 'Category',
                'selling_price' => 'Selling Price',
                'mrp' => 'MRP',
                'quantity' => 'Quantity',
                'weight_grams' => 'Weight',
                'description' => 'Description',
            ],
            'is_dry_run' => false,
        ]);

        $importResponse->assertRedirect(route('seller.connections.csv'));
        Queue::assertPushed(ImportCatalogJob::class);

        // Execute actual import processing
        $result = $this->importService->processImport($import, null, false);
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['imported']);

        // Verify products and inventory in DB
        $this->assertDatabaseHas('products', [
            'seller_id' => $this->seller->id,
            'title' => 'Men Pure Cotton Black T-Shirt',
        ]);
        $this->assertDatabaseHas('product_variants', [
            'sku' => 'TSHIRT-BLK-M',
            'selling_price' => 799.00,
        ]);
        $this->assertDatabaseHas('inventory', [
            'quantity_on_hand' => 40,
        ]);
    }

    public function test_woocommerce_connect_test_and_product_sync(): void
    {
        Queue::fake();
        $this->actingAs($this->sellerUser);

        // Connect WooCommerce
        $response = $this->post(route('seller.connections.woocommerce.connect'), [
            'store_id' => $this->store1->id,
            'store_url' => 'https://mockstore.example.com',
            'consumer_key' => 'ck_test_1234567890',
            'consumer_secret' => 'cs_test_0987654321',
        ]);

        $response->assertRedirect(route('seller.connections.woocommerce'));

        $this->assertDatabaseHas('store_connections', [
            'store_id' => $this->store1->id,
            'type' => StoreConnectionType::WOOCOMMERCE->value,
            'status' => 'active',
        ]);

        $connection = StoreConnection::where('store_id', $this->store1->id)->where('type', StoreConnectionType::WOOCOMMERCE)->first();

        // Test connection endpoint
        $testRes = $this->post(route('seller.connections.woocommerce.test'));
        $testRes->assertSessionHas('success');

        // Trigger Sync
        $syncRes = $this->post(route('seller.connections.woocommerce.sync'));
        $syncRes->assertSessionHas('success');

        Queue::assertPushed(SyncWoocommerceProductsJob::class);

        // Execute Sync job in testing environment
        $job = new SyncWoocommerceProductsJob($connection);
        $job->handle();

        $this->assertDatabaseHas('products', [
            'seller_id' => $this->seller->id,
            'title' => 'WooCommerce Signature Cotton Tee',
        ]);
        $this->assertDatabaseHas('product_variants', [
            'sku' => 'WC-TEE-01',
            'selling_price' => 899.00,
        ]);
        $this->assertDatabaseHas('sync_logs', [
            'connection_id' => $connection->id,
            'entity' => 'products',
            'status' => 'success',
        ]);
    }

    public function test_shopify_connect_test_and_sync(): void
    {
        Queue::fake();
        $this->actingAs($this->sellerUser);

        // Connect Shopify
        $response = $this->post(route('seller.connections.shopify.connect'), [
            'store_id' => $this->store1->id,
            'shop_domain' => 'mybrand-shop.myshopify.com',
            'access_token' => 'shpat_live_mock_token',
        ]);

        $response->assertRedirect(route('seller.connections.shopify'));

        $connection = StoreConnection::where('store_id', $this->store1->id)->where('type', StoreConnectionType::SHOPIFY)->first();
        $this->assertNotNull($connection);

        // Test Connection
        $this->post(route('seller.connections.shopify.test'))->assertSessionHas('success');

        // Trigger Sync
        $this->post(route('seller.connections.shopify.sync'))->assertSessionHas('success');
        Queue::assertPushed(SyncShopifyProductsJob::class);

        // Execute job
        $job = new SyncShopifyProductsJob($connection);
        $job->handle();

        $this->assertDatabaseHas('products', [
            'seller_id' => $this->seller->id,
            'title' => 'Shopify Artisan Coffee Beans 500g',
        ]);
        $this->assertDatabaseHas('product_variants', [
            'sku' => 'SHOPIFY-COFFEE-01',
            'selling_price' => 650.00,
        ]);
    }

    public function test_custom_website_api_generation_and_public_rest_endpoints(): void
    {
        $this->actingAs($this->sellerUser);

        // Generate Custom API Credentials
        $res = $this->post(route('seller.connections.custom_api.generate'), [
            'store_id' => $this->store1->id,
            'name' => 'Custom Production ERP',
            'scopes' => ['products:read', 'products:write', 'inventory:read', 'inventory:write', 'orders:read', 'orders:write'],
            'webhook_url' => 'https://erp.example.com/onestall/webhook',
        ]);

        $res->assertSessionHas('plain_api_key');
        $res->assertSessionHas('plain_api_secret');

        $plainKey = session('plain_api_key');
        $plainSecret = session('plain_api_secret');

        $this->assertDatabaseHas('store_connections', [
            'store_id' => $this->store1->id,
            'type' => StoreConnectionType::CUSTOM_API->value,
            'api_key' => $plainKey,
        ]);

        // Unauthenticated request to /api/v1/integrations/products should fail 401
        $this->getJson('/api/v1/integrations/products')->assertStatus(401);

        // Bad Secret should fail 401
        $this->getJson('/api/v1/integrations/products', [
            'X-Api-Key' => $plainKey,
            'X-Api-Secret' => 'wrong_secret',
        ])->assertStatus(401);

        // 1. GET Products with valid credentials
        $listRes = $this->getJson('/api/v1/integrations/products', [
            'X-Api-Key' => $plainKey,
            'X-Api-Secret' => $plainSecret,
        ]);
        $listRes->assertOk();
        $listRes->assertJsonStructure(['success', 'store_id', 'data', 'pagination']);

        // 2. POST Upsert Product via API
        $createRes = $this->postJson('/api/v1/integrations/products', [
            'title' => 'External ERP Wireless Gaming Mouse',
            'sku' => 'ERP-MOUSE-01',
            'selling_price' => 1899.00,
            'mrp' => 2499.00,
            'quantity' => 50,
            'category' => 'Electronics',
        ], [
            'X-Api-Key' => $plainKey,
            'X-Api-Secret' => $plainSecret,
        ]);

        $createRes->assertStatus(201);
        $createRes->assertJson([
            'success' => true,
            'sku' => 'ERP-MOUSE-01',
            'quantity' => 50,
        ]);

        $this->assertDatabaseHas('products', ['title' => 'External ERP Wireless Gaming Mouse']);
        $this->assertDatabaseHas('product_variants', ['sku' => 'ERP-MOUSE-01']);

        // 3. POST Adjust Inventory
        $adjustRes = $this->postJson('/api/v1/integrations/inventory/adjust', [
            'sku' => 'ERP-MOUSE-01',
            'quantity' => 85,
            'reason' => 'Stock intake from main warehouse',
        ], [
            'X-Api-Key' => $plainKey,
            'X-Api-Secret' => $plainSecret,
        ]);

        $adjustRes->assertOk();
        $adjustRes->assertJson([
            'success' => true,
            'quantity_on_hand' => 85,
        ]);

        $this->assertDatabaseHas('inventory', [
            'quantity_on_hand' => 85,
        ]);

        // 4. Inward Webhooks
        $connection = StoreConnection::where('api_key', $plainKey)->first();
        $this->postJson("/api/v1/integrations/woocommerce/webhook/{$connection->id}", [
            'id' => 99,
            'sku' => 'ERP-MOUSE-01',
            'stock_quantity' => 120,
        ], [
            'X-WC-Webhook-Topic' => 'product.updated',
        ])->assertOk();

        $this->assertDatabaseHas('inventory', [
            'quantity_on_hand' => 120,
        ]);
    }
}
