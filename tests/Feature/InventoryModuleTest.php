<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\ExpireInventoryReservationsCommand;
use App\Enums\InventoryLocationType;
use App\Enums\ReservationStatus;
use App\Enums\SellerStatus;
use App\Enums\SellerType;
use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryLocation;
use App\Models\InventoryReservation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreConnection;
use App\Models\SyncLog;
use App\Models\User;
use App\Notifications\LowStockAlertNotification;
use App\Services\Inventory\InventoryService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InventoryModuleTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryService $service;
    protected User $sellerUser;
    protected Seller $seller;
    protected Store $store;
    protected InventoryLocation $locationA;
    protected InventoryLocation $locationB;
    protected Product $product;
    protected ProductVariant $variant;
    protected Inventory $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->service = app(InventoryService::class);

        $this->sellerUser = User::factory()->create([
            'email' => 'inventory.boss@onestall.in',
            'phone' => '+919876543266',
            'phone_verified_at' => now(),
            'email_verified_at' => now(),
        ]);
        $this->sellerUser->assignRole('seller_owner');

        $this->seller = Seller::factory()->create([
            'user_id' => $this->sellerUser->id,
            'business_name' => 'Metro Supermart Hub',
            'legal_name' => 'Metro Supermart Hub Pvt Ltd',
            'seller_type' => SellerType::LOCAL_STORE,
            'status' => SellerStatus::APPROVED,
            'onboarding_step' => 7,
        ]);

        $this->store = Store::factory()->create([
            'seller_id' => $this->seller->id,
            'name' => 'Metro Retail Outlet',
        ]);

        $this->locationA = InventoryLocation::create([
            'seller_id' => $this->seller->id,
            'store_id' => $this->store->id,
            'name' => 'Central Retail Store',
            'type' => InventoryLocationType::STORE,
            'is_active' => true,
        ]);

        $this->locationB = InventoryLocation::create([
            'seller_id' => $this->seller->id,
            'name' => 'East Hub Warehouse',
            'type' => InventoryLocationType::WAREHOUSE,
            'is_active' => true,
        ]);

        $category = Category::factory()->create();
        $this->product = Product::factory()->create([
            'seller_id' => $this->seller->id,
            'category_id' => $category->id,
            'title' => 'Fresh Farm Butter 500g',
        ]);

        $this->variant = ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'sku' => 'BUTTER-500G',
            'mrp' => 250.00,
            'selling_price' => 220.00,
        ]);

        $this->inventory = Inventory::create([
            'variant_id' => $this->variant->id,
            'location_id' => $this->locationA->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
            'low_stock_threshold' => 10,
            'is_quick_available' => true,
        ]);
    }

    public function test_service_adjust_sets_quantity_and_logs_movement(): void
    {
        $updated = $this->service->adjust($this->inventory, 85, 'Audit count reconciliation');

        $this->assertEquals(85, $updated->quantity_on_hand);
        $this->assertEquals(85, $updated->available_quantity);

        $movement = StockMovement::where('inventory_id', $this->inventory->id)->latest('id')->first();
        $this->assertNotNull($movement);
        $this->assertEquals(StockMovementType::ADJUSTMENT, $movement->type);
        $this->assertEquals(35, $movement->quantity); // +35 delta
        $this->assertEquals('Audit count reconciliation', $movement->note);
    }

    public function test_service_restock_increments_stock_and_logs_movement(): void
    {
        $updated = $this->service->restock($this->inventory, 25, 'Inward supplier delivery');

        $this->assertEquals(75, $updated->quantity_on_hand);

        $movement = StockMovement::where('inventory_id', $this->inventory->id)->latest('id')->first();
        $this->assertNotNull($movement);
        $this->assertEquals(StockMovementType::RESTOCK, $movement->type);
        $this->assertEquals(25, $movement->quantity);
    }

    public function test_service_reserve_locks_stock_and_creates_reservation_with_ttl(): void
    {
        $reservation = $this->service->reserve(
            $this->inventory,
            5,
            'ORDER-TEST-100',
            null,
            15 // 15 minutes TTL
        );

        $this->assertNotNull($reservation);
        $this->assertEquals(5, $reservation->quantity);
        $this->assertEquals('ORDER-TEST-100', $reservation->order_ref);
        $this->assertEquals(ReservationStatus::ACTIVE, $reservation->status);
        $this->assertTrue($reservation->expires_at->isFuture());

        $this->inventory->refresh();
        $this->assertEquals(50, $this->inventory->quantity_on_hand);
        $this->assertEquals(5, $this->inventory->quantity_reserved);
        $this->assertEquals(45, $this->inventory->available_quantity);

        $movement = StockMovement::where('inventory_id', $this->inventory->id)->latest('id')->first();
        $this->assertEquals(StockMovementType::RESERVE, $movement->type);
        $this->assertEquals(-5, $movement->quantity);
    }

    public function test_concurrency_two_simultaneous_orders_cannot_oversell_last_unit(): void
    {
        // 1 unit on hand, 0 reserved -> available = 1
        $this->service->adjust($this->inventory, 1, 'Set to exactly 1 unit for race condition test');
        $this->assertEquals(1, $this->inventory->fresh()->available_quantity);

        // Order 1 reserves the last unit
        $reservation1 = $this->service->reserve($this->inventory, 1, 'ORDER-RACE-001');
        $this->assertNotNull($reservation1);
        $this->assertEquals(0, $this->inventory->fresh()->available_quantity);

        // Order 2 simultaneously attempts to reserve 1 unit -> MUST FAIL with InsufficientStockException!
        $this->expectException(InsufficientStockException::class);
        $this->service->reserve($this->inventory, 1, 'ORDER-RACE-002');
    }

    public function test_concurrency_second_order_fails_when_requesting_more_than_available(): void
    {
        // Set stock to 2 units
        $this->service->adjust($this->inventory, 2, 'Two units stock');
        $this->assertEquals(2, $this->inventory->fresh()->available_quantity);

        // Order 1 reserves 2 units
        $res = $this->service->reserve($this->inventory, 2, 'ORDER-BULK-1');
        $this->assertNotNull($res);
        $this->assertEquals(0, $this->inventory->fresh()->available_quantity);

        // Order 2 attempts to reserve 1 unit -> Throws InsufficientStockException
        $this->expectException(InsufficientStockException::class);
        $this->service->reserve($this->inventory, 1, 'ORDER-BULK-2');
    }

    public function test_service_release_restores_available_stock(): void
    {
        $res = $this->service->reserve($this->inventory, 10, 'ORDER-CANCEL-01');
        $this->assertEquals(40, $this->inventory->fresh()->available_quantity);

        // Release reservation
        $this->service->release($res, 'Customer abandoned checkout');

        $this->assertEquals(ReservationStatus::RELEASED, $res->fresh()->status);
        $this->inventory->refresh();
        $this->assertEquals(0, $this->inventory->quantity_reserved);
        $this->assertEquals(50, $this->inventory->available_quantity);

        $movement = StockMovement::where('inventory_id', $this->inventory->id)->latest('id')->first();
        $this->assertEquals(StockMovementType::RELEASE, $movement->type);
        $this->assertEquals(10, $movement->quantity);
    }

    public function test_service_release_for_order_auto_releases_on_cancellation(): void
    {
        $this->service->reserve($this->inventory, 4, 'ORDER-FAIL-99');
        $this->assertEquals(4, $this->inventory->fresh()->quantity_reserved);

        // Auto release for order
        $released = $this->service->releaseForOrder('ORDER-FAIL-99', 'Payment gateway failed');

        $this->assertEquals(1, $released);
        $this->assertEquals(0, $this->inventory->fresh()->quantity_reserved);
    }

    public function test_service_commit_sale_finalizes_order_and_decrements_on_hand(): void
    {
        $res = $this->service->reserve($this->inventory, 8, 'ORDER-PAID-88');
        $this->assertEquals(50, $this->inventory->fresh()->quantity_on_hand);
        $this->assertEquals(8, $this->inventory->fresh()->quantity_reserved);

        // Commit sale upon payment
        $this->service->commitSale($res);

        $this->assertEquals(ReservationStatus::CONFIRMED, $res->fresh()->status);
        $this->inventory->refresh();
        $this->assertEquals(42, $this->inventory->quantity_on_hand); // 50 - 8 = 42
        $this->assertEquals(0, $this->inventory->quantity_reserved);  // 8 - 8 = 0
        $this->assertEquals(42, $this->inventory->available_quantity);

        $movement = StockMovement::where('inventory_id', $this->inventory->id)->latest('id')->first();
        $this->assertEquals(StockMovementType::SALE, $movement->type);
        $this->assertEquals(-8, $movement->quantity);
    }

    public function test_service_transfer_moves_stock_safely_between_own_locations(): void
    {
        // Transfer 15 units from Location A (store) to Location B (warehouse)
        $this->service->transfer(
            $this->variant->id,
            $this->locationA,
            $this->locationB,
            15,
            'Restocking east warehouse hub'
        );

        $invA = Inventory::where('variant_id', $this->variant->id)->where('location_id', $this->locationA->id)->first();
        $invB = Inventory::where('variant_id', $this->variant->id)->where('location_id', $this->locationB->id)->first();

        $this->assertEquals(35, $invA->quantity_on_hand); // 50 - 15 = 35
        $this->assertEquals(15, $invB->quantity_on_hand); // 0 + 15 = 15

        // Verify stock movements recorded at both ends
        $this->assertTrue(StockMovement::where('inventory_id', $invA->id)->where('quantity', -15)->exists());
        $this->assertTrue(StockMovement::where('inventory_id', $invB->id)->where('quantity', 15)->exists());
    }

    public function test_scheduled_command_releases_expired_reservations(): void
    {
        // Create an expired reservation
        $res = InventoryReservation::create([
            'inventory_id' => $this->inventory->id,
            'order_ref' => 'ORDER-EXPIRED',
            'quantity' => 5,
            'expires_at' => Carbon::now()->subMinutes(5), // Expired 5 mins ago
            'status' => ReservationStatus::ACTIVE,
        ]);
        $this->inventory->update(['quantity_reserved' => 5]);

        // Run artisan command
        $this->artisan('inventory:expire-reservations')
            ->expectsOutputToContain('Successfully released 1 expired inventory reservation(s).')
            ->assertExitCode(0);

        $this->assertEquals(ReservationStatus::RELEASED, $res->fresh()->status);
        $this->assertEquals(0, $this->inventory->fresh()->quantity_reserved);
    }

    public function test_low_stock_notification_queued_when_available_dips_below_threshold(): void
    {
        Notification::fake();

        // Threshold is 10. Adjust available stock to 8 (below threshold 10)
        $this->service->adjust($this->inventory, 8, 'Depleted inventory');

        Notification::assertSentTo(
            $this->sellerUser,
            LowStockAlertNotification::class,
            function (LowStockAlertNotification $notification) {
                return $notification->inventory->id === $this->inventory->id;
            }
        );
    }

    public function test_seller_can_view_inventory_table_with_metrics_and_filters(): void
    {
        $response = $this->actingAs($this->sellerUser)
            ->get(route('seller.inventory.index'));

        $response->assertStatus(200);
        $response->assertSee('Store Inventory &amp; Stock Balances', false);
        $response->assertSee('BUTTER-500G');
        $response->assertSee('Central Retail Store');
        $response->assertSee('Total On-Hand');
        $response->assertSee('Active Reserved');
        $response->assertSee('Total Available');
    }

    public function test_seller_can_inline_update_inventory(): void
    {
        $response = $this->actingAs($this->sellerUser)
            ->putJson(route('seller.inventory.update', $this->inventory), [
                'quantity_on_hand' => 65,
                'low_stock_threshold' => 12,
                'is_quick_available' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'inventory' => [
                    'quantity_on_hand' => 65,
                    'low_stock_threshold' => 12,
                ],
            ]);

        $this->assertEquals(65, $this->inventory->fresh()->quantity_on_hand);
        $this->assertEquals(12, $this->inventory->fresh()->low_stock_threshold);
    }

    public function test_seller_can_fetch_stock_movements_history(): void
    {
        $this->service->adjust($this->inventory, 60, 'Initial count');

        $response = $this->actingAs($this->sellerUser)
            ->getJson(route('seller.inventory.movements', $this->inventory));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'sku' => 'BUTTER-500G',
            ]);
    }

    public function test_seller_can_bulk_update_stock_via_csv_import(): void
    {
        $csvContent = "sku,location_id,quantity_on_hand,low_stock_threshold\nBUTTER-500G,{$this->locationA->id},120,15\n";
        $file = UploadedFile::fake()->createWithContent('inventory.csv', $csvContent);

        $response = $this->actingAs($this->sellerUser)
            ->post(route('seller.inventory.import_csv'), [
                'csv_file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->inventory->refresh();
        $this->assertEquals(120, $this->inventory->quantity_on_hand);
        $this->assertEquals(15, $this->inventory->low_stock_threshold);
    }

    public function test_admin_can_view_global_inventory_overview(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)
            ->get(route('admin.inventory.index'));

        $response->assertStatus(200);
        $response->assertSee('Global Marketplace Inventory');
        $response->assertSee('BUTTER-500G');
        $response->assertSee('Metro Supermart Hub');
    }

    public function test_admin_can_view_sync_logs(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $conn = StoreConnection::create([
            'store_id' => $this->store->id,
            'type' => 'woocommerce',
            'status' => 'active',
            'config' => ['api_key' => '123'],
        ]);

        SyncLog::create([
            'connection_id' => $conn->id,
            'direction' => 'inward',
            'entity' => 'inventory',
            'status' => 'success',
            'message' => 'Synced 15 SKUs from POS terminal',
            'payload' => ['sku' => 'BUTTER-500G', 'qty' => 50],
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.inventory.sync_logs'));

        $response->assertStatus(200);
        $response->assertSee('Synced 15 SKUs from POS terminal');
        $response->assertSee('Integration Sync Logs');
    }

    public function test_non_admin_cannot_view_admin_inventory(): void
    {
        // Seller cannot view admin global inventory
        $this->actingAs($this->sellerUser)
            ->get(route('admin.inventory.index'))
            ->assertForbidden();
    }
}
