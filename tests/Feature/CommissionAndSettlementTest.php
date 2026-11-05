<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CODRemittanceStatus;
use App\Enums\CommissionRuleScope;
use App\Enums\FulfilmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SellerStatus;
use App\Enums\SettlementStatus;
use App\Enums\StoreStatus;
use App\Models\Category;
use App\Models\CodRemittance;
use App\Models\CommissionEntry;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\FulfilmentOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\SellerSettlement;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Services\Commission\CommissionService;
use App\Services\Order\OrderStateMachine;
use App\Services\Settlement\SettlementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CommissionAndSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $sellerUser;
    protected Seller $seller;
    protected User $otherSellerUser;
    protected Seller $otherSeller;
    protected Customer $customer;
    protected CustomerAddress $address;
    protected Category $category;
    protected Category $subCategory;
    protected CommissionService $commissionService;
    protected SettlementService $settlementService;
    protected OrderStateMachine $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->commissionService = app(CommissionService::class);
        $this->settlementService = app(SettlementService::class);
        $this->stateMachine = app(OrderStateMachine::class);

        // Platform settings
        Setting::set('default_commission_pct', '10.00');
        Setting::set('commission_gst_pct', '18.00');
        Setting::set('payment_gateway_fee_pct', '2.00');
        Setting::set('return_window_days', '7');
        Setting::set('settlement_cycle_days', '7');

        // Admin User
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->adminUser = User::factory()->create([
            'name' => 'Finance Admin',
            'email' => 'admin@onestall.local',
            'status' => 'active',
        ]);
        $this->adminUser->assignRole('super_admin');

        // Main Seller
        $this->sellerUser = User::factory()->create([
            'name' => 'Seller One',
            'email' => 'seller1@onestall.local',
            'status' => 'active',
        ]);
        $this->seller = Seller::create([
            'user_id' => $this->sellerUser->id,
            'business_name' => 'Alpha Electronics',
            'legal_name' => 'Alpha Electronics Pvt Ltd',
            'slug' => 'alpha-electronics',
            'status' => SellerStatus::APPROVED,
        ]);

        // Other Seller
        $this->otherSellerUser = User::factory()->create([
            'name' => 'Seller Two',
            'email' => 'seller2@onestall.local',
            'status' => 'active',
        ]);
        $this->otherSeller = Seller::create([
            'user_id' => $this->otherSellerUser->id,
            'business_name' => 'Beta Fashion',
            'legal_name' => 'Beta Fashion LLP',
            'slug' => 'beta-fashion',
            'status' => SellerStatus::APPROVED,
        ]);

        // Categories
        $this->category = Category::create([
            'name' => 'Consumer Electronics',
            'slug' => 'consumer-electronics',
            'is_active' => true,
        ]);
        $this->subCategory = Category::create([
            'name' => 'Smartphones',
            'slug' => 'smartphones',
            'parent_id' => $this->category->id,
            'is_active' => true,
        ]);

        // Customer
        $customerUser = User::factory()->create(['status' => 'active']);
        $this->customer = Customer::create(['user_id' => $customerUser->id]);
        $this->address = CustomerAddress::create([
            'user_id' => $customerUser->id,
            'customer_id' => $this->customer->id,
            'name' => 'Jane Buyer',
            'phone' => '9988776655',
            'line1' => 'MG Road',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560001',
            'is_default' => true,
        ]);
    }

    /**
     * Helper to create an order with a fulfilment order.
     */
    protected function createOrderWithPackage(Seller $seller, float $total = 1000.00, string $status = 'placed'): FulfilmentOrder
    {
        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'customer_id' => $this->customer->id,
            'customer_address_id' => $this->address->id,
            'address_snapshot' => [
                'name' => $this->address->name,
                'phone' => $this->address->phone,
                'line1' => $this->address->line1,
                'city' => $this->address->city,
                'state' => $this->address->state,
                'pincode' => $this->address->pincode,
            ],
            'status' => OrderStatus::PLACED,
            'payment_status' => PaymentStatus::PAID,
            'payment_method' => PaymentMethod::UPI,
            'subtotal' => $total,
            'tax' => 0.00,
            'discount' => 0.00,
            'delivery_fee' => 0.00,
            'total' => $total,
        ]);

        $store = Store::firstOrCreate(
            ['seller_id' => $seller->id],
            [
                'name' => $seller->business_name . ' Store',
                'slug' => $seller->slug . '-store',
                'status' => StoreStatus::ACTIVE,
            ]
        );

        return FulfilmentOrder::create([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'store_id' => $store->id,
            'fulfilment_order_number' => 'PKG-' . strtoupper(uniqid()),
            'fulfilment_type' => FulfilmentType::STANDARD,
            'status' => $status,
            'subtotal' => $total,
            'delivery_fee' => 0.00,
            'commission_amount' => $total * 0.1,
            'seller_payable' => $total * 0.9,
            'delivered_at' => $status === 'delivered' ? now() : null,
        ]);
    }

    /**
     * Test 1: Seller-specific rule has highest priority over promo, category, and global.
     */
    public function test_seller_specific_rule_has_highest_priority_over_all_other_tiers(): void
    {
        // Global rule: 10%
        CommissionRule::create([
            'name' => 'Global Default Rule',
            'scope' => CommissionRuleScope::GLOBAL,
            'percent' => 10.00,
            'priority' => 1,
            'is_active' => true,
        ]);

        // Category rule: 12%
        CommissionRule::create([
            'name' => 'Electronics Category Rule',
            'scope' => CommissionRuleScope::CATEGORY,
            'category_id' => $this->category->id,
            'percent' => 12.00,
            'priority' => 5,
            'is_active' => true,
        ]);

        // Promo rule: 5% (active now)
        CommissionRule::create([
            'name' => 'Flash Sale Promo',
            'scope' => CommissionRuleScope::PROMO,
            'percent' => 5.00,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'priority' => 10,
            'is_active' => true,
        ]);

        // Seller specific rule: 7%
        $sellerRule = CommissionRule::create([
            'name' => 'Alpha Electronics Exclusive VIP',
            'scope' => CommissionRuleScope::SELLER,
            'seller_id' => $this->seller->id,
            'percent' => 7.00,
            'priority' => 20,
            'is_active' => true,
        ]);

        $resolved = $this->commissionService->resolveRule(
            sellerId: $this->seller->id,
            categoryId: $this->category->id
        );

        $this->assertEquals('seller', $resolved['tier']);
        $this->assertEquals(7.00, $resolved['rate']);
        $this->assertEquals($sellerRule->id, $resolved['rule']->id);
    }

    /**
     * Test 2: Seller override column on Seller entity applies if no seller-specific rule exists.
     */
    public function test_seller_override_column_applies_ahead_of_promo_and_category(): void
    {
        $this->seller->commission_override_pct = '6.50';
        $this->seller->save();

        CommissionRule::create([
            'name' => 'Promo Rule',
            'scope' => CommissionRuleScope::PROMO,
            'percent' => 5.00,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'priority' => 10,
            'is_active' => true,
        ]);

        $resolved = $this->commissionService->resolveRule(
            sellerId: $this->seller->id,
            categoryId: $this->category->id
        );

        $this->assertEquals('seller_override_column', $resolved['tier']);
        $this->assertEquals(6.50, $resolved['rate']);
        $this->assertNull($resolved['rule']);
    }

    /**
     * Test 3: Active promotional rule takes precedence over category and global rules.
     */
    public function test_promotional_rule_within_dates_takes_precedence_over_category_and_global(): void
    {
        CommissionRule::create([
            'name' => 'Global Default',
            'scope' => CommissionRuleScope::GLOBAL,
            'percent' => 10.00,
            'priority' => 1,
            'is_active' => true,
        ]);

        CommissionRule::create([
            'name' => 'Category Rule',
            'scope' => CommissionRuleScope::CATEGORY,
            'category_id' => $this->category->id,
            'percent' => 14.00,
            'priority' => 5,
            'is_active' => true,
        ]);

        $promoRule = CommissionRule::create([
            'name' => 'Diwali Dhamaka Promo',
            'scope' => CommissionRuleScope::PROMO,
            'percent' => 4.50,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->addDays(5),
            'priority' => 15,
            'is_active' => true,
        ]);

        // Evaluate without a seller-specific override
        $resolved = $this->commissionService->resolveRule(
            sellerId: $this->seller->id,
            categoryId: $this->category->id
        );

        $this->assertEquals('promo', $resolved['tier']);
        $this->assertEquals(4.50, $resolved['rate']);
        $this->assertEquals($promoRule->id, $resolved['rule']->id);
    }

    /**
     * Test 4: Expired or future promotional rule falls back to category rule.
     */
    public function test_expired_promotional_rule_falls_back_to_category_rule(): void
    {
        // Expired promo rule
        CommissionRule::create([
            'name' => 'Expired Summer Sale',
            'scope' => CommissionRuleScope::PROMO,
            'percent' => 3.00,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
            'priority' => 50,
            'is_active' => true,
        ]);

        // Category rule
        $catRule = CommissionRule::create([
            'name' => 'Electronics Category',
            'scope' => CommissionRuleScope::CATEGORY,
            'category_id' => $this->category->id,
            'percent' => 12.50,
            'priority' => 5,
            'is_active' => true,
        ]);

        $resolved = $this->commissionService->resolveRule(
            sellerId: null,
            categoryId: $this->category->id
        );

        $this->assertEquals('category', $resolved['tier']);
        $this->assertEquals(12.50, $resolved['rate']);
        $this->assertEquals($catRule->id, $resolved['rule']->id);
    }

    /**
     * Test 5: Sub-category inherits parent category commission rule when no sub-category rule exists.
     */
    public function test_sub_category_inherits_parent_category_rule(): void
    {
        CommissionRule::create([
            'name' => 'Parent Electronics Category Rule',
            'scope' => CommissionRuleScope::CATEGORY,
            'category_id' => $this->category->id,
            'percent' => 11.00,
            'priority' => 5,
            'is_active' => true,
        ]);

        $resolved = $this->commissionService->resolveRule(
            sellerId: null,
            categoryId: $this->subCategory->id
        );

        $this->assertEquals('category', $resolved['tier']);
        $this->assertEquals(11.00, $resolved['rate']);
    }

    /**
     * Test 6: Fallback to global rule and database setting when no higher priority matches.
     */
    public function test_fallback_to_global_rule_and_setting(): void
    {
        // 1. With global rule
        $globalRule = CommissionRule::create([
            'name' => 'Global Marketplace Rule',
            'scope' => CommissionRuleScope::GLOBAL,
            'percent' => 8.50,
            'priority' => 1,
            'is_active' => true,
        ]);

        $resolved = $this->commissionService->resolveRule(null, null);
        $this->assertEquals('global', $resolved['tier']);
        $this->assertEquals(8.50, $resolved['rate']);

        // 2. Disable global rule, test fallback to Setting table
        $globalRule->delete();
        Setting::set('default_commission_pct', '13.25');

        $resolvedFallback = $this->commissionService->resolveRule(null, null);
        $this->assertEquals('setting_fallback', $resolvedFallback['tier']);
        $this->assertEquals(13.25, $resolvedFallback['rate']);
    }

    /**
     * Test 7: Calculate computes percentage + fixed fee, GST (18%), gateway fee (2%), and net seller payable.
     */
    public function test_commission_calculation_exact_mathematical_breakdown(): void
    {
        // Create rule: 10% commission + ₹50 fixed fee
        CommissionRule::create([
            'name' => 'Test Math Rule',
            'scope' => CommissionRuleScope::GLOBAL,
            'percent' => 10.00,
            'fixed_fee' => 50.00,
            'priority' => 1,
            'is_active' => true,
        ]);

        // Base order amount = ₹1,000.00, Shipping adjustment = ₹40.00
        $calc = $this->commissionService->calculate(
            baseAmount: 1000.00,
            sellerId: null,
            categoryId: null,
            shippingAdjustment: 40.00
        );

        // Expected:
        // commission = (1000 * 0.10) + 50 = ₹150.00
        $this->assertEquals(150.00, $calc['commission_amount']);

        // tax_on_commission (GST 18% on 150) = ₹27.00
        $this->assertEquals(27.00, $calc['tax_on_commission']);

        // gateway_fee (2% on 1000) = ₹20.00
        $this->assertEquals(20.00, $calc['gateway_fee']);

        // total deductions = 150 + 27 = ₹177.00
        $this->assertEquals(177.00, $calc['total_platform_deduction']);

        // net_payable = 1000 - 150 - 27 - 20 (gateway fee 2%) + 40 (shipping) = ₹843.00
        $this->assertEquals(843.00, $calc['net_payable']);
    }

    /**
     * Test 8: FulfilmentOrder transitioning to delivered automatically creates CommissionEntry.
     */
    public function test_commission_entry_created_automatically_on_order_delivery(): void
    {
        CommissionRule::create([
            'name' => 'Standard Global',
            'scope' => CommissionRuleScope::GLOBAL,
            'percent' => 10.00,
            'fixed_fee' => 0.00,
            'priority' => 1,
            'is_active' => true,
        ]);

        $fo = $this->createOrderWithPackage($this->seller, 2000.00, 'placed');

        // Transition through lifecycle to delivered
        $this->stateMachine->transitionFulfilmentOrder($fo, 'confirmed');
        $this->stateMachine->transitionFulfilmentOrder($fo, 'packed');
        $this->stateMachine->transitionFulfilmentOrder($fo, 'ready_to_ship');
        $this->stateMachine->transitionFulfilmentOrder($fo, 'picked_up');
        $this->stateMachine->transitionFulfilmentOrder($fo, 'in_transit');
        $this->stateMachine->transitionFulfilmentOrder($fo, 'out_for_delivery');
        $this->stateMachine->transitionFulfilmentOrder($fo, 'delivered');

        $this->assertDatabaseHas('commission_entries', [
            'fulfilment_order_id' => $fo->id,
            'entry_type' => 'standard',
            'base_amount' => 2000.00,
            'commission' => 200.00,
            'tax_on_commission' => 36.00, // 18% of 200
            'net_payable' => 1724.00, // 2000 - 200 - 36 - 40 (gateway fee)
        ]);
    }

    /**
     * Test 9: FulfilmentOrder refund creates a proportionate negative reversal entry.
     */
    public function test_commission_entry_reversal_on_order_refund(): void
    {
        $fo = $this->createOrderWithPackage($this->seller, 1000.00, 'delivered');
        $entry = $this->commissionService->recordForDeliveredFulfilmentOrder($fo);

        $this->assertEquals(100.00, (float) $entry->commission);

        // Process refund of ₹500 (50%)
        $reversal = $this->commissionService->recordReversal($fo, 500.00, 'Customer return partial');

        $this->assertEquals('reversal', $reversal->entry_type);
        $this->assertEquals(-500.00, (float) $reversal->base_amount);
        $this->assertEquals(-50.00, (float) $reversal->commission);
        $this->assertEquals(-9.00, (float) $reversal->tax_on_commission);
        $this->assertEquals(-431.00, (float) $reversal->net_payable);
    }

    /**
     * Test 10: SettlementService generates weekly batch for mature delivered orders past return window.
     */
    public function test_settlement_batch_generation_respects_return_window(): void
    {
        // 1. Mature order delivered 10 days ago (outside 7 day window)
        $matureFo = $this->createOrderWithPackage($this->seller, 1500.00, 'delivered');
        $matureFo->delivered_at = now()->subDays(10);
        $matureFo->save();
        $this->commissionService->recordForDeliveredFulfilmentOrder($matureFo);

        // 2. Immature order delivered 2 days ago (within 7 day window)
        $immatureFo = $this->createOrderWithPackage($this->seller, 800.00, 'delivered');
        $immatureFo->delivered_at = now()->subDays(2);
        $immatureFo->save();
        $this->commissionService->recordForDeliveredFulfilmentOrder($immatureFo);

        // Run batch generation
        $settlements = $this->settlementService->generateWeeklyBatch();

        $this->assertCount(1, $settlements);
        $settlement = $settlements->first();

        $this->assertEquals($this->seller->id, $settlement->seller_id);
        $this->assertEquals(SettlementStatus::PENDING, $settlement->status);
        $this->assertEquals(1500.00, (float) $settlement->gross);

        // Only mature order in settlement items
        $this->assertCount(1, $settlement->items);
        $this->assertEquals($matureFo->id, $settlement->items->first()->fulfilment_order_id);
    }

    /**
     * Test 11: Settlement batch reconciles collected COD payments by deducting cash held by merchant.
     */
    public function test_settlement_batch_reconciles_doorstep_cod_collections(): void
    {
        $fo = $this->createOrderWithPackage($this->seller, 2000.00, 'delivered');
        $fo->delivered_at = now()->subDays(10);
        $fo->save();
        $this->commissionService->recordForDeliveredFulfilmentOrder($fo);

        // COD remittance collected at doorstep
        CodRemittance::create([
            'seller_id' => $this->seller->id,
            'amount' => 500.00,
            'status' => CODRemittanceStatus::COLLECTED,
            'collected_at' => now()->subDays(10),
            'reference' => 'COD-CASH-TEST',
        ]);

        $settlements = $this->settlementService->generateWeeklyBatch();
        $settlement = $settlements->first();

        $this->assertEquals(500.00, (float) $settlement->cod_collected);
        // Gross 2000 - Comm 200 - Tax 36 - COD 500 = 1264
        $this->assertEquals(1264.00, (float) $settlement->net);

        // Check COD item is recorded with negative amount
        $codItem = $settlement->items()->where('type', 'cod_deduction')->first();
        $this->assertNotNull($codItem);
        $this->assertEquals(-500.00, (float) $codItem->amount);
    }

    /**
     * Test 12: Admin can hold, release, approve, and mark settlement paid with UTR reference.
     */
    public function test_settlement_workflow_hold_release_approve_and_mark_paid(): void
    {
        $settlement = SellerSettlement::create([
            'seller_id' => $this->seller->id,
            'settlement_number' => 'SET-TEST-001',
            'period_start' => now()->subDays(7),
            'period_end' => now(),
            'gross' => 1000.00,
            'commission' => 100.00,
            'tax_on_commission' => 18.00,
            'cod_collected' => 0.00,
            'adjustments' => 0.00,
            'net' => 882.00,
            'status' => SettlementStatus::PENDING,
        ]);

        // 1. Hold
        $this->actingAs($this->adminUser)
            ->post(route('admin.settlements.hold', $settlement), [
                'reason' => 'Bank KYC verification pending',
            ])
            ->assertRedirect();

        $settlement->refresh();
        $this->assertEquals(SettlementStatus::ON_HOLD, $settlement->status);
        $this->assertEquals('Bank KYC verification pending', $settlement->hold_reason);

        // 2. Release
        $this->actingAs($this->adminUser)
            ->post(route('admin.settlements.release', $settlement), [
                'note' => 'KYC verified successfully',
            ])
            ->assertRedirect();

        $settlement->refresh();
        $this->assertEquals(SettlementStatus::PENDING, $settlement->status);
        $this->assertNull($settlement->hold_reason);

        // 3. Approve
        $this->actingAs($this->adminUser)
            ->post(route('admin.settlements.approve', $settlement))
            ->assertRedirect();

        $settlement->refresh();
        $this->assertEquals(SettlementStatus::PROCESSING, $settlement->status);

        // 4. Mark Paid with Bank UTR
        $this->actingAs($this->adminUser)
            ->post(route('admin.settlements.pay', $settlement), [
                'reference' => 'UTR998877665544',
            ])
            ->assertRedirect();

        $settlement->refresh();
        $this->assertEquals(SettlementStatus::PAID, $settlement->status);
        $this->assertEquals('UTR998877665544', $settlement->reference);
        $this->assertNotNull($settlement->paid_at);
    }

    /**
     * Test 13: Interactive commission simulator endpoint returns winning rule and full fee calculation.
     */
    public function test_admin_commission_simulator_endpoint(): void
    {
        CommissionRule::create([
            'name' => 'Category Rule',
            'scope' => CommissionRuleScope::CATEGORY,
            'category_id' => $this->category->id,
            'percent' => 12.00,
            'fixed_fee' => 15.00,
            'priority' => 10,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->postJson(route('admin.commissions.simulate'), [
            'category_id' => $this->category->id,
            'amount' => 1000.00,
            'shipping_adjustment' => 0.00,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'tier' => 'category',
                'rule_name' => 'Category Rule',
                'rate_percent' => 12.00,
                'fixed_fee' => 15.00,
                'commission_amount' => 135.00, // 1000 * 0.12 + 15
                'tax_on_commission' => 24.30,  // 18% of 135
                'total_platform_deduction' => 159.30,
                'net_payable' => 820.70,
            ]);
    }

    /**
     * Test 14: PDF and CSV settlement statement download endpoints for Admin and Seller.
     */
    public function test_pdf_and_csv_statement_downloads(): void
    {
        $settlement = SellerSettlement::create([
            'seller_id' => $this->seller->id,
            'settlement_number' => 'SET-STMT-001',
            'period_start' => now()->subDays(7),
            'period_end' => now(),
            'gross' => 1000.00,
            'commission' => 100.00,
            'tax_on_commission' => 18.00,
            'cod_collected' => 0.00,
            'adjustments' => 0.00,
            'net' => 882.00,
            'status' => SettlementStatus::PAID,
            'reference' => 'UTR12345678',
        ]);

        // Admin PDF
        $adminPdf = $this->actingAs($this->adminUser)->get(route('admin.settlements.pdf', $settlement));
        $adminPdf->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // Admin CSV
        $adminCsv = $this->actingAs($this->adminUser)->get(route('admin.settlements.csv', $settlement));
        $adminCsv->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        // Seller PDF
        $sellerPdf = $this->actingAs($this->sellerUser)->get(route('seller.settlements.pdf', $settlement));
        $sellerPdf->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // Seller CSV
        $sellerCsv = $this->actingAs($this->sellerUser)->get(route('seller.settlements.csv', $settlement));
        $sellerCsv->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    /**
     * Test 15: Cross-seller tenant isolation ensures seller cannot access another seller's settlement.
     */
    public function test_seller_cannot_view_or_download_another_sellers_settlement(): void
    {
        $settlement = SellerSettlement::create([
            'seller_id' => $this->seller->id,
            'settlement_number' => 'SET-ISOLATION-001',
            'period_start' => now()->subDays(7),
            'period_end' => now(),
            'gross' => 500.00,
            'commission' => 50.00,
            'tax_on_commission' => 9.00,
            'cod_collected' => 0.00,
            'adjustments' => 0.00,
            'net' => 441.00,
            'status' => SettlementStatus::PAID,
        ]);

        // Other seller attempts to view Seller 1's settlement
        $this->actingAs($this->otherSellerUser)
            ->get(route('seller.settlements.show', $settlement))
            ->assertForbidden();

        $this->actingAs($this->otherSellerUser)
            ->get(route('seller.settlements.pdf', $settlement))
            ->assertForbidden();

        $this->actingAs($this->otherSellerUser)
            ->get(route('seller.settlements.csv', $settlement))
            ->assertForbidden();
    }

    /**
     * Test 16: Scheduled Artisan command settlements:generate runs batch generation with formatted output.
     */
    public function test_artisan_settlements_generate_command_executes_successfully(): void
    {
        $matureFo = $this->createOrderWithPackage($this->seller, 1200.00, 'delivered');
        $matureFo->delivered_at = now()->subDays(10);
        $matureFo->save();
        $this->commissionService->recordForDeliveredFulfilmentOrder($matureFo);

        $this->artisan('settlements:generate')
            ->expectsOutputToContain('Successfully generated 1 settlement batch(es)')
            ->assertSuccessful();

        $this->assertDatabaseHas('seller_settlements', [
            'seller_id' => $this->seller->id,
            'status' => 'pending',
            'gross' => 1200.00,
        ]);
    }

    /**
     * Test 17: Admin can create, update, toggle and delete commission rules.
     */
    public function test_admin_commission_rules_crud_lifecycle(): void
    {
        // Store
        $this->actingAs($this->adminUser)
            ->post(route('admin.commissions.store'), [
                'name' => 'Monsoon Sale Rule',
                'scope' => 'promo',
                'percent' => 7.50,
                'fixed_fee' => 10.00,
                'priority' => 25,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.commissions.index'));

        $rule = CommissionRule::where('name', 'Monsoon Sale Rule')->first();
        $this->assertNotNull($rule);
        $this->assertEquals(7.50, (float) $rule->percent);

        // Update
        $this->actingAs($this->adminUser)
            ->put(route('admin.commissions.update', $rule), [
                'name' => 'Monsoon Sale Rule Updated',
                'scope' => 'promo',
                'percent' => 6.00,
                'fixed_fee' => 5.00,
                'priority' => 30,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.commissions.index'));

        $rule->refresh();
        $this->assertEquals(6.00, (float) $rule->percent);
        $this->assertEquals('Monsoon Sale Rule Updated', $rule->name);

        // Toggle
        $this->actingAs($this->adminUser)
            ->postJson(route('admin.commissions.toggle', $rule))
            ->assertOk()
            ->assertJson(['success' => true, 'is_active' => false]);

        // Destroy
        $this->actingAs($this->adminUser)
            ->delete(route('admin.commissions.destroy', $rule))
            ->assertRedirect(route('admin.commissions.index'));

        $this->assertDatabaseMissing('commission_rules', ['id' => $rule->id]);
    }

    /**
     * Test 18: Admin can apply manual financial adjustment to a pending settlement.
     */
    public function test_admin_can_apply_adjustment_to_settlement(): void
    {
        $settlement = SellerSettlement::create([
            'seller_id' => $this->seller->id,
            'settlement_number' => 'SET-ADJ-001',
            'period_start' => now()->subDays(7),
            'period_end' => now(),
            'gross' => 1000.00,
            'commission' => 100.00,
            'tax_on_commission' => 18.00,
            'cod_collected' => 0.00,
            'adjustments' => 0.00,
            'net' => 882.00,
            'status' => SettlementStatus::PENDING,
        ]);

        $this->actingAs($this->adminUser)
            ->post(route('admin.settlements.adjustment', $settlement), [
                'amount' => 50.00,
                'reason' => 'Marketing reimbursement bonus',
            ])
            ->assertRedirect();

        $settlement->refresh();
        $this->assertEquals(50.00, (float) $settlement->adjustments);
        $this->assertEquals(932.00, (float) $settlement->net);

        $this->assertDatabaseHas('settlement_items', [
            'seller_settlement_id' => $settlement->id,
            'type' => 'adjustment',
            'amount' => 50.00,
            'description' => 'Marketing reimbursement bonus',
        ]);
    }

    /**
     * Test 19: Seller can access payments ledger and settlements dashboard with correct metrics.
     */
    public function test_seller_can_access_payments_ledger_and_settlements(): void
    {
        $fo = $this->createOrderWithPackage($this->seller, 1000.00, 'delivered');
        $this->commissionService->recordForDeliveredFulfilmentOrder($fo);

        $this->actingAs($this->sellerUser)
            ->get(route('seller.payments.index'))
            ->assertOk()
            ->assertViewIs('seller.payments.index')
            ->assertViewHas('entries');

        $this->actingAs($this->sellerUser)
            ->get(route('seller.settlements.index'))
            ->assertOk()
            ->assertViewIs('seller.settlements.index')
            ->assertViewHas('settlements');
    }
}
