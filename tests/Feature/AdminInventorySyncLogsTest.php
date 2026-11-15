<?php

namespace Tests\Feature;

use App\Enums\SellerStatus;
use App\Enums\StoreConnectionType;
use App\Enums\StoreStatus;
use App\Models\Inventory;
use App\Models\Seller;
use App\Models\Store;
use App\Models\StoreConnection;
use App\Models\SyncLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminInventorySyncLogsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $sellerUser;
    protected Seller $seller;
    protected Store $store;
    protected StoreConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create([
            'email' => 'admin_synclogs_' . uniqid() . '@example.com',
            'status' => 'active',
        ]);
        $this->admin->assignRole('super_admin');

        $this->sellerUser = User::factory()->create();
        $this->seller = Seller::create([
            'user_id' => $this->sellerUser->id,
            'business_name' => 'Prime Retailers',
            'legal_name' => 'Prime Retailers India Pvt Ltd',
            'slug' => 'prime-retailers',
            'status' => SellerStatus::APPROVED,
        ]);

        $this->store = Store::create([
            'seller_id' => $this->seller->id,
            'name' => 'Prime Store Mumbai Central',
            'slug' => 'prime-store-mumbai-central',
            'status' => StoreStatus::ACTIVE,
            'delivery_radius_km' => 15.0,
        ]);

        $this->connection = StoreConnection::create([
            'store_id' => $this->store->id,
            'type' => StoreConnectionType::WOOCOMMERCE,
            'name' => 'Main POS Terminal Woo-01',
            'status' => 'connected',
            'api_key' => 'ck_test_' . uniqid(),
            'api_secret_hash' => hash('sha256', 'secret'),
        ]);
    }

    public function test_admin_can_view_sync_logs_index_with_metrics(): void
    {
        SyncLog::create([
            'connection_id' => $this->connection->id,
            'direction' => 'inward',
            'entity' => 'inventory',
            'status' => 'success',
            'message' => 'Synced 15 items from local POS',
            'payload' => ['synced_count' => 15, 'terminal' => 'POS-01'],
        ]);

        SyncLog::create([
            'connection_id' => $this->connection->id,
            'direction' => 'outward',
            'entity' => 'orders',
            'status' => 'failed',
            'message' => 'Timeout connecting to webhook endpoint',
            'payload' => ['error' => 'Connection timed out after 5000ms'],
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.inventory.sync_logs'));

        $response->assertOk();
        $response->assertSee('Bi-Directional POS &amp; Integration Sync Logs', false);
        $response->assertSee('Main POS Terminal Woo-01');
        $response->assertSee('Synced 15 items from local POS');
        $response->assertSee('Timeout connecting to webhook endpoint');
        $response->assertSee('Prime Store Mumbai Central');
    }

    public function test_admin_can_filter_sync_logs_by_status_and_entity(): void
    {
        $log1 = SyncLog::create([
            'connection_id' => $this->connection->id,
            'direction' => 'inward',
            'entity' => 'inventory',
            'status' => 'success',
            'message' => 'Stock adjustment accepted',
        ]);

        $log2 = SyncLog::create([
            'connection_id' => $this->connection->id,
            'direction' => 'outward',
            'entity' => 'orders',
            'status' => 'failed',
            'message' => 'Order push failed',
        ]);

        // Filter by status: failed
        $response = $this->actingAs($this->admin)->get(route('admin.inventory.sync_logs', ['status' => 'failed']));
        $response->assertOk();
        $response->assertSee('Order push failed');
        $response->assertDontSee('Stock adjustment accepted');

        // Filter by entity: inventory
        $response = $this->actingAs($this->admin)->get(route('admin.inventory.sync_logs', ['entity' => 'inventory']));
        $response->assertOk();
        $response->assertSee('Stock adjustment accepted');
        $response->assertDontSee('Order push failed');
    }

    public function test_admin_can_search_sync_logs_by_message_and_seller(): void
    {
        $log = SyncLog::create([
            'connection_id' => $this->connection->id,
            'direction' => 'inward',
            'entity' => 'inventory',
            'status' => 'success',
            'message' => 'Discrepancy resolved for SKU-AIRMAX-90',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.inventory.sync_logs', ['search' => 'SKU-AIRMAX-90']));
        $response->assertOk();
        $response->assertSee('Discrepancy resolved for SKU-AIRMAX-90');

        $response2 = $this->actingAs($this->admin)->get(route('admin.inventory.sync_logs', ['search' => 'Prime Retailers']));
        $response2->assertOk();
        $response2->assertSee('Main POS Terminal Woo-01');
    }

    public function test_admin_can_export_sync_logs_csv(): void
    {
        SyncLog::create([
            'connection_id' => $this->connection->id,
            'direction' => 'inward',
            'entity' => 'products',
            'status' => 'success',
            'message' => 'Catalog sync export test verification',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.inventory.sync_logs.export'));

        $response->assertOk();
        $this->assertTrue(str_contains((string) $response->headers->get('content-type'), 'text/csv'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('Log ID', $content);
        $this->assertStringContainsString('Catalog sync export test verification', $content);
        $this->assertStringContainsString('Main POS Terminal Woo-01', $content);
    }

    public function test_admin_can_prune_old_sync_logs(): void
    {
        // 45 days old log
        $oldLog = SyncLog::create([
            'connection_id' => $this->connection->id,
            'direction' => 'inward',
            'entity' => 'inventory',
            'status' => 'success',
            'message' => 'Old legacy log',
        ]);
        \Illuminate\Support\Facades\DB::table('sync_logs')->where('id', $oldLog->id)->update([
            'created_at' => Carbon::now()->subDays(45),
            'updated_at' => Carbon::now()->subDays(45),
        ]);

        // Recent log
        $recentLog = SyncLog::create([
            'connection_id' => $this->connection->id,
            'direction' => 'inward',
            'entity' => 'inventory',
            'status' => 'success',
            'message' => 'Recent active log',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.inventory.sync_logs.prune'), [
            'days' => 30,
            'status' => 'all',
        ]);

        $response->assertRedirect(route('admin.inventory.sync_logs'));
        $this->assertDatabaseMissing('sync_logs', ['id' => $oldLog->id]);
        $this->assertDatabaseHas('sync_logs', ['id' => $recentLog->id]);
    }

    public function test_unauthorized_user_cannot_view_sync_logs(): void
    {
        $guestUser = User::factory()->create();

        $response = $this->actingAs($guestUser)->get(route('admin.inventory.sync_logs'));
        $response->assertForbidden();
    }
}
