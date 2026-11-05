<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReviewStatus;
use App\Enums\StoreConnectionType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\FulfilmentOrder;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\Store;
use App\Models\StoreConnection;
use App\Models\SupportTicket;
use App\Models\SyncLog;
use App\Models\TicketMessage;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingIntegrationAndSeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_and_demo_seeders_execution(): void
    {
        // Execute Production Seeder
        $this->seed(ProductionSeeder::class);

        // 1. Super Admin exists and has roles
        $admin = User::where('email', 'admin@onestall.in')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('super_admin'));
        $this->assertTrue($admin->hasRole('admin'));

        // 2. 12 Root Categories and Subcategories seeded
        $rootCategories = Category::root()->get();
        $this->assertGreaterThanOrEqual(12, $rootCategories->count());
        $allCategories = Category::all();
        $this->assertGreaterThanOrEqual(48, $allCategories->count());

        // 3. 10 Verified Brands seeded
        $brands = Brand::verified()->get();
        $this->assertGreaterThanOrEqual(10, $brands->count());
        $this->assertTrue($brands->pluck('name')->contains('Amul'));
        $this->assertTrue($brands->pluck('name')->contains('Tata Sampann'));

        // 4. Default Settings verified
        $this->assertEquals(10.00, (float) setting('default_commission_pct'));
        $this->assertEquals(8.00, (float) setting('default_delivery_radius_km'));
        $priority = setting('fulfilment_priority');
        $this->assertIsArray($priority);
        $this->assertEquals('Nearby Store', $priority[0]);
        $this->assertEquals('Nearby Seller', $priority[1]);
        $this->assertEquals('OneStall Store', $priority[2]);

        // Execute Demo Seeder
        $this->seed(DemoSeeder::class);

        // 5. 5 Demo Sellers and Stores
        $this->assertGreaterThanOrEqual(5, Seller::count());
        $this->assertGreaterThanOrEqual(5, Store::count());

        // 6. 40 Demo Products and inventory
        $this->assertGreaterThanOrEqual(40, Product::count());
        $this->assertGreaterThanOrEqual(40, Inventory::count());

        // 7. 20 Demo Customers
        $this->assertGreaterThanOrEqual(20, Customer::count());

        // 8. 100 Demo Orders in mixed statuses
        $ordersCount = Order::count();
        $this->assertGreaterThanOrEqual(100, $ordersCount);
        $this->assertGreaterThan(0, Order::where('status', OrderStatus::DELIVERED)->count());
        $this->assertGreaterThan(0, Order::where('status', OrderStatus::PLACED)->count());
        $this->assertGreaterThan(0, Order::where('status', OrderStatus::OUT_FOR_DELIVERY)->count());

        $this->assertGreaterThanOrEqual(100, FulfilmentOrder::count());
    }

    public function test_coupon_discount_calculation(): void
    {
        $percentCoupon = Coupon::create([
            'code' => 'TEST50',
            'type' => CouponType::PERCENT,
            'value' => 50.00,
            'max_discount' => 150.00,
            'min_order' => 200.00,
            'is_active' => true,
        ]);

        $flatCoupon = Coupon::create([
            'code' => 'TEST100FLAT',
            'type' => CouponType::FLAT,
            'value' => 100.00,
            'min_order' => 500.00,
            'is_active' => true,
        ]);

        // Subtotal below min order
        $this->assertEquals(0.00, $percentCoupon->calculateDiscount(150.00));
        // Subtotal 400: 50% = 200, but capped at max_discount 150
        $this->assertEquals(150.00, $percentCoupon->calculateDiscount(400.00));
        // Subtotal 250: 50% = 125 (under max 150)
        $this->assertEquals(125.00, $percentCoupon->calculateDiscount(250.00));

        // Flat coupon: 100 flat off 600
        $this->assertEquals(100.00, $flatCoupon->calculateDiscount(600.00));
        // Flat coupon: 0 off 400 (below min order 500)
        $this->assertEquals(0.00, $flatCoupon->calculateDiscount(400.00));
    }

    public function test_store_connections_and_sync_logs(): void
    {
        $seller = Seller::factory()->create();
        $store = Store::factory()->create(['seller_id' => $seller->id]);

        $connection = StoreConnection::create([
            'store_id' => $store->id,
            'type' => StoreConnectionType::PHYSICAL,
            'status' => 'active',
            'config' => ['pos_terminal_id' => 'POS-9988', 'location_code' => 'BLR-01'],
            'last_synced_at' => now(),
        ]);

        $this->assertEquals(StoreConnectionType::PHYSICAL, $connection->type);
        $this->assertIsArray($connection->config);
        $this->assertEquals('POS-9988', $connection->config['pos_terminal_id']);

        $syncLog = SyncLog::create([
            'connection_id' => $connection->id,
            'direction' => 'inward',
            'entity' => 'inventory',
            'status' => 'success',
            'message' => 'Synced 150 inventory SKUs from physical POS',
            'payload' => ['synced_count' => 150],
        ]);

        $this->assertCount(1, $connection->syncLogs);
        $this->assertEquals('success', $syncLog->status);
    }

    public function test_support_tickets_and_reviews(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        // Review
        $review = Review::create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'rating' => 5,
            'title' => 'Great product quality',
            'body' => 'Prompt delivery and well packaged.',
            'is_verified_purchase' => true,
            'status' => ReviewStatus::APPROVED,
        ]);

        $this->assertEquals(ReviewStatus::APPROVED, $review->status);
        $this->assertTrue(Review::approved()->where('id', $review->id)->exists());

        // Support ticket
        $ticket = SupportTicket::create([
            'ticket_number' => 'TKT-TEST-9901',
            'user_id' => $user->id,
            'subject' => 'Delivery delay query',
            'category' => 'logistics',
            'priority' => TicketPriority::HIGH,
            'status' => TicketStatus::OPEN,
        ]);

        $message = TicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => 'When will my package arrive?',
            'is_internal' => false,
        ]);

        $this->assertCount(1, $ticket->messages);
        $this->assertEquals(TicketStatus::OPEN, $ticket->status);
        $this->assertTrue(SupportTicket::open()->where('id', $ticket->id)->exists());
    }
}
