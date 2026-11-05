<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Order\PlaceOrderAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\SellerStatus;
use App\Enums\SellerType;
use App\Models\AiActionLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Inventory;
use App\Models\InventoryLocation;
use App\Models\Order;
use App\Models\OrderFraudAssessment;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Store;
use App\Models\StoreTiming;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\AI\AiManager;
use App\Services\AI\Contracts\DemandForecastingInterface;
use App\Services\AI\Contracts\OrderFraudScorerInterface;
use App\Services\AI\Contracts\RecommendationServiceInterface;
use App\Services\AI\Contracts\SellerAiAssistantInterface;
use App\Services\AI\Contracts\SemanticSearchParserInterface;
use App\Services\AI\Contracts\SupportAiReplyInterface;
use App\Services\Storefront\CartService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $sellerUserA;
    protected User $sellerUserB;
    protected Seller $sellerA;
    protected Seller $sellerB;
    protected Store $storeA;
    protected Store $storeB;
    protected Customer $customer;
    protected User $customerUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        // Admin
        $this->adminUser = User::factory()->create([
            'email' => 'ai_admin@onestall.in',
            'name' => 'AI System Admin',
        ]);
        $this->adminUser->assignRole('super_admin');

        // Seller A
        $this->sellerUserA = User::factory()->create([
            'name' => 'Merchant Alice',
            'email' => 'alice@seller-ai.com',
        ]);
        $this->sellerUserA->assignRole('seller_owner');

        $this->sellerA = Seller::create([
            'user_id' => $this->sellerUserA->id,
            'business_name' => 'Alice Fashions LLP',
            'legal_name' => 'Alice Fashions Retail LLP',
            'seller_type' => SellerType::LOCAL_STORE,
            'status' => SellerStatus::APPROVED,
        ]);

        $this->storeA = Store::create([
            'seller_id' => $this->sellerA->id,
            'name' => 'Alice Flagship Koramangala',
            'slug' => 'alice-flagship-koramangala',
            'status' => 'active',
            'delivery_radius_km' => 10.0,
            'accepts_quick_commerce' => true,
        ]);

        // Seller B (Isolated Peer)
        $this->sellerUserB = User::factory()->create([
            'name' => 'Merchant Bob',
            'email' => 'bob@seller-ai.com',
        ]);
        $this->sellerUserB->assignRole('seller_owner');

        $this->sellerB = Seller::create([
            'user_id' => $this->sellerUserB->id,
            'business_name' => 'Bob Electronics Ltd',
            'legal_name' => 'Bob Electronics Limited',
            'seller_type' => SellerType::MARKETPLACE,
            'status' => SellerStatus::APPROVED,
        ]);

        $this->storeB = Store::create([
            'seller_id' => $this->sellerB->id,
            'name' => 'Bob Digital Mart',
            'slug' => 'bob-digital-mart',
            'status' => 'active',
            'delivery_radius_km' => 10.0,
        ]);

        // Customer
        $this->customerUser = User::factory()->create([
            'name' => 'Vikram Malhotra',
            'email' => 'vikram@example.com',
            'phone' => '9876543210',
        ]);
        $this->customerUser->assignRole('customer');
        $this->customer = Customer::create([
            'user_id' => $this->customerUser->id,
        ]);
    }

    // =========================================================================
    // 1. SEMANTIC & NATURAL-LANGUAGE SEARCH TESTS
    // =========================================================================

    public function test_natural_language_search_parsing_extracts_filters(): void
    {
        $parser = app(SemanticSearchParserInterface::class);

        // Test phrase: "black shirt under 1000 near me"
        $parsed = $parser->parse('black shirt under 1000 near me');

        $this->assertEquals('shirt', $parsed->cleanedKeyword);
        $this->assertEquals('Black', $parsed->colour);
        $this->assertEquals(1000.00, $parsed->maxPrice);
        $this->assertNull($parsed->minPrice);
        $this->assertTrue($parsed->isNearMe);

        $filters = $parsed->toFilterArray();
        $this->assertEquals(1000.00, $filters['max_price']);
        $this->assertTrue($filters['quick']);
        $this->assertEquals(['colour' => 'Black'], $filters['attributes']);

        // Check AI Action Log entry
        $log = AiActionLog::where('feature', 'semantic_search')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertEquals('parse_query', $log->action);
        $this->assertEquals('success', $log->status);
    }

    public function test_natural_language_search_price_range_and_speed(): void
    {
        $parser = app(SemanticSearchParserInterface::class);

        $parsed = $parser->parse('fast delivery milk between 50 and 80');

        $this->assertEquals('milk', $parsed->cleanedKeyword);
        $this->assertEquals(50.00, $parsed->minPrice);
        $this->assertEquals(80.00, $parsed->maxPrice);
        $this->assertTrue($parsed->isQuickCommerce);
    }

    public function test_semantic_search_respects_feature_flag(): void
    {
        config(['ai.features.semantic_search' => false]);
        $parser = app(SemanticSearchParserInterface::class);

        $raw = 'black shirt under 1000 near me';
        $parsed = $parser->parse($raw);

        $this->assertEquals($raw, $parsed->cleanedKeyword);
        $this->assertNull($parsed->maxPrice);
        $this->assertFalse($parsed->isNearMe);

        $log = AiActionLog::where('feature', 'semantic_search')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertEquals('flag_disabled', $log->status);
    }

    public function test_semantic_search_api_parse_endpoint(): void
    {
        $response = $this->getJson('/api/v1/search/parse?q=' . urlencode('red cotton kurti below 1500 nearby'));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.colour', 'Red');
        $response->assertJsonPath('data.max_price', 1500);
        $response->assertJsonPath('data.is_near_me', true);
    }

    // =========================================================================
    // 2. RECOMMENDATIONS ENGINE TESTS
    // =========================================================================

    public function test_similar_products_recommendations(): void
    {
        $category = Category::create(['name' => 'Men Apparel', 'slug' => 'men-apparel', 'is_active' => true]);

        $product1 = Product::create([
            'seller_id' => $this->sellerA->id,
            'category_id' => $category->id,
            'title' => 'Slim Fit Linen Shirt',
            'slug' => 'slim-fit-linen-shirt',
            'status' => ProductStatus::ACTIVE,
            'product_type' => ProductType::SIMPLE,
        ]);
        ProductVariant::create([
            'product_id' => $product1->id,
            'name' => 'Medium White',
            'sku' => 'SHIRT-LINEN-M',
            'selling_price' => 1200.00,
            'mrp' => 1500.00,
            'is_default' => true,
            'is_active' => true,
        ]);

        $product2 = Product::create([
            'seller_id' => $this->sellerB->id,
            'category_id' => $category->id,
            'title' => 'Casual Oxford Cotton Shirt',
            'slug' => 'casual-oxford-cotton-shirt',
            'status' => ProductStatus::ACTIVE,
            'product_type' => ProductType::SIMPLE,
        ]);
        ProductVariant::create([
            'product_id' => $product2->id,
            'name' => 'Medium Blue',
            'sku' => 'SHIRT-OXFORD-M',
            'selling_price' => 1100.00,
            'mrp' => 1400.00,
            'is_default' => true,
            'is_active' => true,
        ]);

        $recService = app(RecommendationServiceInterface::class);
        $similar = $recService->getSimilarProducts($product1, 5);

        $this->assertNotEmpty($similar);
        $this->assertTrue($similar->contains('id', $product2->id));
        $this->assertFalse($similar->contains('id', $product1->id)); // Must not recommend itself
    }

    public function test_recommendations_api_endpoints(): void
    {
        $category = Category::create(['name' => 'Daily Dairy', 'slug' => 'daily-dairy', 'is_active' => true]);
        $product = Product::create([
            'seller_id' => $this->sellerA->id,
            'category_id' => $category->id,
            'title' => 'Cow Milk 1L',
            'slug' => 'cow-milk-1l',
            'status' => ProductStatus::ACTIVE,
            'product_type' => ProductType::SIMPLE,
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'name' => '1L Pouch',
            'sku' => 'MILK-1L',
            'selling_price' => 60.00,
            'mrp' => 65.00,
            'is_default' => true,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}/recommendations");
        $response->assertOk();
        $response->assertJsonStructure(['similar', 'frequently_bought_together']);

        $persResponse = $this->getJson('/api/v1/recommendations/personalized');
        $persResponse->assertOk();
        $persResponse->assertJsonStructure(['personalized']);
    }

    // =========================================================================
    // 3. SELLER AI ASSISTANT (CATALOGUE & PRICING) TESTS
    // =========================================================================

    public function test_seller_ai_catalogue_content_generator(): void
    {
        $assistant = app(SellerAiAssistantInterface::class);

        $result = $assistant->generateCatalogueContent(
            seller: $this->sellerA,
            title: 'Wireless Ergonomic Keyboard',
            category: 'Computer Accessories',
            brand: 'LogiPro',
            keyFeatures: ['Bluetooth 5.3 + 2.4G', 'Rechargeable 500mAh', 'Silent Scissor Switches'],
            tone: 'engaging'
        );

        $this->assertStringContainsString('LogiPro Wireless Ergonomic Keyboard', $result['title']);
        $this->assertStringContainsString('Silent Scissor Switches', $result['description']);
        $this->assertNotEmpty($result['key_highlights']);
        $this->assertNotEmpty($result['meta_title']);
        $this->assertNotEmpty($result['meta_description']);

        $log = AiActionLog::where('feature', 'seller_assistant')
            ->where('seller_id', $this->sellerA->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('generate_catalogue_content', $log->action);
    }

    public function test_seller_ai_pricing_suggestions_with_competitor_benchmarks(): void
    {
        $category = Category::create(['name' => 'Audio & Headphones', 'slug' => 'audio-headphones', 'is_active' => true]);

        // Seller A variant: Current price 2,500
        $productA = Product::create([
            'seller_id' => $this->sellerA->id,
            'category_id' => $category->id,
            'title' => 'BassPro Wireless Earbuds',
            'slug' => 'basspro-wireless-earbuds',
            'status' => ProductStatus::ACTIVE,
            'product_type' => ProductType::SIMPLE,
        ]);
        $variantA = ProductVariant::create([
            'product_id' => $productA->id,
            'name' => 'Matte Black',
            'sku' => 'BASSPRO-BLK',
            'selling_price' => 2500.00,
            'mrp' => 3000.00,
            'is_default' => true,
            'is_active' => true,
        ]);

        // Seller B competitors in same category: Average ~1,800
        $productB = Product::create([
            'seller_id' => $this->sellerB->id,
            'category_id' => $category->id,
            'title' => 'SonicAir TWS Earbuds',
            'slug' => 'sonicair-tws-earbuds',
            'status' => ProductStatus::ACTIVE,
            'product_type' => ProductType::SIMPLE,
        ]);
        ProductVariant::create([
            'product_id' => $productB->id,
            'name' => 'White',
            'sku' => 'SONICAIR-WHT',
            'selling_price' => 1800.00,
            'mrp' => 2500.00,
            'is_default' => true,
            'is_active' => true,
        ]);

        $assistant = app(SellerAiAssistantInterface::class);
        $suggestion = $assistant->suggestPricing($this->sellerA, $variantA);

        $this->assertEquals(2500.00, $suggestion['current_selling_price']);
        $this->assertEquals(1800.00, $suggestion['category_avg_price']);
        $this->assertLessThan(2500.00, $suggestion['recommended_price']); // Suggests competitive price
        $this->assertGreaterThan(0, $suggestion['competitor_sample_size']);
        $this->assertNotEmpty($suggestion['rationale']);
    }

    public function test_seller_cannot_request_pricing_for_another_sellers_product(): void
    {
        $category = Category::create(['name' => 'Footwear', 'slug' => 'footwear', 'is_active' => true]);

        // Product owned by Seller A
        $productA = Product::create([
            'seller_id' => $this->sellerA->id,
            'category_id' => $category->id,
            'title' => 'Leather Oxford Shoes',
            'slug' => 'leather-oxford-shoes',
            'status' => ProductStatus::ACTIVE,
            'product_type' => ProductType::SIMPLE,
        ]);
        $variantA = ProductVariant::create([
            'product_id' => $productA->id,
            'name' => 'UK 9 Brown',
            'sku' => 'OXFORD-BRN-9',
            'selling_price' => 3500.00,
            'mrp' => 4500.00,
            'is_default' => true,
            'is_active' => true,
        ]);

        // Seller B attempts to probe Seller A's product pricing
        $this->expectException(AuthorizationException::class);
        $assistant = app(SellerAiAssistantInterface::class);
        $assistant->suggestPricing($this->sellerB, $variantA);
    }

    public function test_seller_portal_ai_api_endpoints_enforce_multi_tenancy(): void
    {
        $category = Category::create(['name' => 'Hardware', 'slug' => 'hardware', 'is_active' => true]);
        $productA = Product::create([
            'seller_id' => $this->sellerA->id,
            'category_id' => $category->id,
            'title' => 'Smart Lock V2',
            'slug' => 'smart-lock-v2',
            'status' => ProductStatus::ACTIVE,
            'product_type' => ProductType::SIMPLE,
        ]);
        $variantA = ProductVariant::create([
            'product_id' => $productA->id,
            'name' => 'Silver Edition',
            'sku' => 'LOCK-SLV',
            'selling_price' => 4999.00,
            'mrp' => 6999.00,
            'is_default' => true,
            'is_active' => true,
        ]);

        // Seller B attempts to call API for Seller A's variant &rarr; 403 Forbidden
        $response = $this->actingAs($this->sellerUserB)
            ->postJson(route('seller.ai.suggest_pricing', $variantA->id));

        $response->assertStatus(403);

        // Seller A calls API for own variant &rarr; 200 OK
        $legitResponse = $this->actingAs($this->sellerUserA)
            ->postJson(route('seller.ai.suggest_pricing', $variantA->id));

        $legitResponse->assertOk();
        $legitResponse->assertJsonPath('success', true);
    }

    // =========================================================================
    // 4. LOW-STOCK PREDICTION & DEMAND FORECASTING TESTS
    // =========================================================================

    public function test_demand_forecasting_calculates_run_rate_and_stockout(): void
    {
        $category = Category::create(['name' => 'Beverages', 'slug' => 'beverages', 'is_active' => true]);
        $product = Product::create([
            'seller_id' => $this->sellerA->id,
            'category_id' => $category->id,
            'title' => 'Cold Brew Coffee Concentrate (500ml)',
            'slug' => 'cold-brew-coffee-500ml',
            'status' => ProductStatus::ACTIVE,
            'product_type' => ProductType::SIMPLE,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => '500ml Bottle',
            'sku' => 'COLDBREW-500',
            'selling_price' => 300.00,
            'mrp' => 350.00,
            'is_default' => true,
            'is_active' => true,
        ]);

        $location = InventoryLocation::create([
            'seller_id' => $this->sellerA->id,
            'store_id' => $this->storeA->id,
            'name' => 'Koramangala Chill Shelf',
            'type' => 'store',
            'is_active' => true,
        ]);

        // Current available stock: 15 units
        Inventory::create([
            'variant_id' => $variant->id,
            'location_id' => $location->id,
            'quantity_on_hand' => 15,
            'quantity_reserved' => 0,
        ]);

        // Simulate sales: Create an order with 30 units sold in the last 7 days (~4.2 units/day)
        $parentOrder = Order::create([
            'order_number' => 'OS-SIM-TEST-01',
            'customer_id' => $this->customer->id,
            'address_snapshot' => ['city' => 'Bengaluru'],
            'subtotal' => 9000.00,
            'total' => 9000.00,
            'status' => OrderStatus::DELIVERED,
        ]);
        $fo = \App\Models\FulfilmentOrder::create([
            'order_id' => $parentOrder->id,
            'seller_id' => $this->sellerA->id,
            'store_id' => $this->storeA->id,
            'fulfilment_order_number' => 'FO-SIM-01',
            'fulfilment_type' => \App\Enums\FulfilmentType::STANDARD,
            'status' => 'delivered',
            'subtotal' => 9000.00,
            'total_amount' => 9000.00,
        ]);
        OrderItem::create([
            'fulfilment_order_id' => $fo->id,
            'variant_id' => $variant->id,
            'product_id' => $product->id,
            'title' => $product->title,
            'sku' => $variant->sku,
            'price' => 300.00,
            'qty' => 30,
            'total' => 9000.00,
            'created_at' => now()->subDays(3),
        ]);

        $forecasting = app(DemandForecastingInterface::class);
        $forecast = $forecasting->forecastForVariant($this->sellerA, $variant, 7);

        $this->assertNotNull($forecast);
        $this->assertGreaterThan(0, (float) $forecast->daily_sales_velocity);
        $this->assertLessThan(10.0, (float) $forecast->days_to_stockout);
        $this->assertContains($forecast->stock_status, ['low_stock', 'critical_stockout']);
        $this->assertGreaterThan(0, $forecast->recommended_reorder_qty);

        // Verify record in database
        $this->assertDatabaseHas('seller_demand_forecasts', [
            'seller_id' => $this->sellerA->id,
            'variant_id' => $variant->id,
        ]);
    }

    public function test_demand_forecast_scoped_strictly_to_seller(): void
    {
        $category = Category::create(['name' => 'Snacks', 'slug' => 'snacks', 'is_active' => true]);
        $productB = Product::create([
            'seller_id' => $this->sellerB->id,
            'category_id' => $category->id,
            'title' => 'Roasted Makhana 100g',
            'slug' => 'roasted-makhana-100g',
            'status' => ProductStatus::ACTIVE,
            'product_type' => ProductType::SIMPLE,
        ]);
        $variantB = ProductVariant::create([
            'product_id' => $productB->id,
            'name' => 'Peri Peri',
            'sku' => 'MAKHANA-PERI',
            'selling_price' => 120.00,
            'mrp' => 150.00,
            'is_default' => true,
            'is_active' => true,
        ]);

        // Seller A tries to forecast Seller B's inventory
        $this->expectException(AuthorizationException::class);
        $forecasting = app(DemandForecastingInterface::class);
        $forecasting->forecastForVariant($this->sellerA, $variantB);
    }

    // =========================================================================
    // 5. FRAUD & RISK SCORING TESTS
    // =========================================================================

    public function test_order_fraud_scorer_approves_normal_order(): void
    {
        $order = Order::create([
            'order_number' => 'OS-FR-NORM-01',
            'customer_id' => $this->customer->id,
            'address_snapshot' => [
                'name' => 'Vikram Malhotra',
                'phone' => '9876543210',
                'line1' => 'Flat 402, Green Glen Apartments',
                'city' => 'Bengaluru',
                'pincode' => '560034',
            ],
            'subtotal' => 800.00,
            'total' => 840.00,
            'payment_method' => PaymentMethod::UPI,
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::PROCESSING,
        ]);

        $scorer = app(OrderFraudScorerInterface::class);
        $assessment = $scorer->assessOrder($order);

        $this->assertNotNull($assessment);
        $this->assertLessThanOrEqual(30, $assessment->risk_score);
        $this->assertEquals('low', $assessment->risk_level);
        $this->assertEquals('approved', $assessment->decision);
    }

    public function test_order_fraud_scorer_flags_high_risk_abusive_order(): void
    {
        // 1. Simulate velocity abuse: 6 recent orders in the last hour
        for ($i = 1; $i <= 6; $i++) {
            Order::create([
                'order_number' => "OS-SPAM-0{$i}",
                'customer_id' => $this->customer->id,
                'address_snapshot' => ['city' => 'Bengaluru', 'pincode' => '560034'],
                'subtotal' => 500.00,
                'total' => 500.00,
                'created_at' => now()->subMinutes(10),
            ]);
        }

        // 2. High-value COD order with suspicious gibberish address and invalid pincode
        $riskyOrder = Order::create([
            'order_number' => 'OS-FR-RISK-99',
            'customer_id' => $this->customer->id,
            'address_snapshot' => [
                'name' => 'Vikram',
                'phone' => '123', // Malformed phone
                'line1' => 'asdf qwerty test', // Gibberish address
                'city' => 'Unknown',
                'pincode' => '999', // Malformed pincode
            ],
            'subtotal' => 4800.00,
            'total' => 4840.00,
            'payment_method' => PaymentMethod::COD,
            'payment_status' => PaymentStatus::COD_PENDING,
            'status' => OrderStatus::PLACED,
        ]);

        $scorer = app(OrderFraudScorerInterface::class);
        $assessment = $scorer->assessOrder($riskyOrder);

        $this->assertNotNull($assessment);
        $this->assertGreaterThanOrEqual(70, $assessment->risk_score);
        $this->assertEquals('high', $assessment->risk_level);
        $this->assertEquals('flagged_for_review', $assessment->decision);
        $this->assertNotEmpty($assessment->reasons);
        $this->assertTrue($assessment->isHighRisk());
    }

    // =========================================================================
    // 6. AUTOMATED SUPPORT REPLIES TESTS
    // =========================================================================

    public function test_support_ai_generates_contextual_tracking_reply(): void
    {
        $order = Order::create([
            'order_number' => 'OS-TRACK-77',
            'customer_id' => $this->customer->id,
            'address_snapshot' => ['city' => 'Bengaluru'],
            'subtotal' => 1200.00,
            'total' => 1200.00,
            'status' => OrderStatus::PROCESSING,
        ]);

        $ticket = SupportTicket::create([
            'ticket_number' => 'TCK-202611-001',
            'user_id' => $this->customerUser->id,
            'order_id' => $order->id,
            'subject' => 'Where is my order OS-TRACK-77? Need tracking details',
            'category' => 'Delivery',
            'priority' => \App\Enums\TicketPriority::MEDIUM,
            'status' => \App\Enums\TicketStatus::OPEN,
        ]);

        $replyService = app(SupportAiReplyInterface::class);
        $result = $replyService->generateReply($ticket, autoSendIfConfident: true);

        $this->assertEquals('tracking_delivery', $result['intent']);
        $this->assertGreaterThanOrEqual(0.80, $result['confidence']);
        $this->assertStringContainsString('OS-TRACK-77', $result['reply_text']);
        $this->assertTrue($result['auto_posted']);

        // Verify message created
        $this->assertDatabaseHas('ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'user_id' => null, // AI system author
        ]);
    }

    public function test_support_ai_generates_return_policy_reply(): void
    {
        $order = Order::create([
            'order_number' => 'OS-RET-88',
            'customer_id' => $this->customer->id,
            'address_snapshot' => ['city' => 'Bengaluru'],
            'subtotal' => 2400.00,
            'total' => 2400.00,
            'status' => OrderStatus::DELIVERED,
        ]);

        $ticket = SupportTicket::create([
            'ticket_number' => 'TCK-202611-002',
            'user_id' => $this->customerUser->id,
            'order_id' => $order->id,
            'subject' => 'I received a damaged shirt, want to return it',
            'category' => 'Returns',
            'priority' => \App\Enums\TicketPriority::HIGH,
            'status' => \App\Enums\TicketStatus::OPEN,
        ]);

        $replyService = app(SupportAiReplyInterface::class);
        $result = $replyService->generateReply($ticket, autoSendIfConfident: false);

        $this->assertEquals('return_exchange', $result['intent']);
        $this->assertStringContainsString('7-day return window', $result['reply_text']);
        $this->assertFalse($result['auto_posted']);
    }

    // =========================================================================
    // 7. AUDIT LOGGING & SELLER ACCESS CONTROL TESTS
    // =========================================================================

    public function test_all_ai_actions_create_audit_logs(): void
    {
        $aiManager = app(AiManager::class);

        $log = $aiManager->log(
            feature: 'seller_assistant',
            action: 'custom_test_action',
            userId: $this->sellerUserA->id,
            sellerId: $this->sellerA->id,
            promptSummary: 'Test prompt summary',
            input: ['foo' => 'bar'],
            output: ['baz' => 'qux'],
            latencyMs: 12.50
        );

        $this->assertNotNull($log);
        $this->assertDatabaseHas('ai_action_logs', [
            'feature' => 'seller_assistant',
            'action' => 'custom_test_action',
            'seller_id' => $this->sellerA->id,
        ]);

        // Seller A views their logs &rarr; Sees log
        $responseA = $this->actingAs($this->sellerUserA)
            ->getJson(route('seller.ai.action_logs'));
        $responseA->assertOk();
        $this->assertNotEmpty($responseA->json('data'));

        // Seller B views their logs &rarr; Empty (Cannot see Seller A's logs)
        $responseB = $this->actingAs($this->sellerUserB)
            ->getJson(route('seller.ai.action_logs'));
        $responseB->assertOk();
        $this->assertEmpty($responseB->json('data'));
    }
}
