<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CODRemittanceStatus;
use App\Enums\SellerStatus;
use App\Enums\SettlementStatus;
use App\Models\CodRemittance;
use App\Models\CommissionEntry;
use App\Models\FulfilmentOrder;
use App\Models\Order;
use App\Models\Seller;
use App\Models\SellerSettlement;
use App\Models\SettlementItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminSettlementsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $unauthorizedUser;
    protected Seller $seller;
    protected SellerSettlement $settlement;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Roles
        $adminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $customerRole = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $this->adminUser = User::factory()->create(['email' => 'admin_settlement@onestall.test']);
        $this->adminUser->assignRole($adminRole);

        $this->unauthorizedUser = User::factory()->create(['email' => 'customer_user@onestall.test']);
        $this->unauthorizedUser->assignRole($customerRole);

        // 2. Seller
        $sellerUser = User::factory()->create(['email' => 'merchant_settlement@onestall.test']);
        $this->seller = Seller::create([
            'user_id' => $sellerUser->id,
            'business_name' => 'Green Valley Organics',
            'legal_name' => 'Green Valley Organics Pvt Ltd',
            'seller_type' => 'marketplace',
            'status' => SellerStatus::APPROVED,
        ]);

        // 3. Settlement
        $this->settlement = SellerSettlement::create([
            'seller_id' => $this->seller->id,
            'settlement_number' => 'SET-TEST-998877',
            'period_start' => now()->subDays(14)->toDateString(),
            'period_end' => now()->subDays(7)->toDateString(),
            'gross' => 5000.00,
            'commission' => 500.00,
            'tax_on_commission' => 90.00,
            'cod_collected' => 0.00,
            'adjustments' => 0.00,
            'net' => 4410.00,
            'status' => SettlementStatus::PENDING,
        ]);

        SettlementItem::create([
            'seller_settlement_id' => $this->settlement->id,
            'amount' => 4410.00,
            'type' => 'order_payout',
            'description' => 'Delivered order payout package #101',
        ]);
    }

    public function test_guest_is_redirected_from_settlements(): void
    {
        $response = $this->get(route('admin.settlements.index'));
        $response->assertRedirect();
    }

    public function test_unauthorized_user_is_forbidden(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->get(route('admin.settlements.index'));
        $response->assertForbidden();
    }

    public function test_admin_can_view_settlements_index_with_kpi_cards(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.settlements.index'));

        $response->assertOk();
        $response->assertSee('Seller Settlements &amp; Disbursements', false);
        $response->assertSee('SET-TEST-998877');
        $response->assertSee('Green Valley Organics');
        $response->assertSee(format_currency(4410.00));
        $response->assertSee('Pending Approval');
    }

    public function test_admin_can_filter_settlements_by_status(): void
    {
        // Create an on-hold settlement
        SellerSettlement::create([
            'seller_id' => $this->seller->id,
            'settlement_number' => 'SET-ONHOLD-112233',
            'period_start' => now()->subDays(20)->toDateString(),
            'period_end' => now()->subDays(15)->toDateString(),
            'gross' => 2000.00,
            'commission' => 200.00,
            'tax_on_commission' => 36.00,
            'cod_collected' => 0.00,
            'adjustments' => 0.00,
            'net' => 1764.00,
            'status' => SettlementStatus::ON_HOLD,
            'hold_reason' => 'Dispute with merchant',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.settlements.index', ['status' => 'on_hold']));

        $response->assertOk();
        $response->assertSee('SET-ONHOLD-112233');
        $response->assertDontSee('SET-TEST-998877');
    }

    public function test_admin_can_filter_settlements_by_seller(): void
    {
        $otherUser = User::factory()->create();
        $otherSeller = Seller::create([
            'user_id' => $otherUser->id,
            'business_name' => 'Royal Bakery Mart',
            'legal_name' => 'Royal Bakery LLP',
            'seller_type' => 'marketplace',
            'status' => SellerStatus::APPROVED,
        ]);

        SellerSettlement::create([
            'seller_id' => $otherSeller->id,
            'settlement_number' => 'SET-ROYAL-445566',
            'period_start' => now()->subDays(10)->toDateString(),
            'period_end' => now()->subDays(3)->toDateString(),
            'gross' => 1000.00,
            'commission' => 100.00,
            'tax_on_commission' => 18.00,
            'cod_collected' => 0.00,
            'adjustments' => 0.00,
            'net' => 882.00,
            'status' => SettlementStatus::PENDING,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.settlements.index', ['seller_id' => $this->seller->id]));

        $response->assertOk();
        $response->assertSee('SET-TEST-998877');
        $response->assertDontSee('SET-ROYAL-445566');
    }

    public function test_admin_can_search_settlements_by_seller_name_without_sql_errors(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.settlements.index', ['search' => 'Green Valley']));

        $response->assertOk();
        $response->assertSee('SET-TEST-998877');
        $response->assertSee('Green Valley Organics');
    }

    public function test_admin_can_search_settlements_by_number_or_utr(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.settlements.index', ['search' => '998877']));

        $response->assertOk();
        $response->assertSee('SET-TEST-998877');
    }

    public function test_admin_can_view_settlement_console_show(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.settlements.show', $this->settlement));

        $response->assertOk();
        $response->assertSee('SET-TEST-998877');
        $response->assertSee('Green Valley Organics');
        $response->assertSee('Delivered order payout package #101');
        $response->assertSee(format_currency(4410.00));
    }

    public function test_admin_can_approve_settlement(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.settlements.approve', $this->settlement));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->settlement->refresh();
        $this->assertEquals(SettlementStatus::PROCESSING, $this->settlement->status);
    }

    public function test_admin_can_mark_settlement_paid_with_utr(): void
    {
        // First approve
        $this->settlement->update(['status' => SettlementStatus::PROCESSING]);

        $response = $this->actingAs($this->adminUser)->post(route('admin.settlements.pay', $this->settlement), [
            'reference' => 'UTR-HDFC-20261115-001',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->settlement->refresh();
        $this->assertEquals(SettlementStatus::PAID, $this->settlement->status);
        $this->assertEquals('UTR-HDFC-20261115-001', $this->settlement->reference);
        $this->assertNotNull($this->settlement->paid_at);
    }

    public function test_admin_can_hold_and_release_settlement(): void
    {
        // Hold
        $responseHold = $this->actingAs($this->adminUser)->post(route('admin.settlements.hold', $this->settlement), [
            'reason' => 'Bank IFSC verification failed',
        ]);
        $responseHold->assertRedirect();
        $this->settlement->refresh();
        $this->assertEquals(SettlementStatus::ON_HOLD, $this->settlement->status);
        $this->assertEquals('Bank IFSC verification failed', $this->settlement->hold_reason);

        // Release
        $responseRelease = $this->actingAs($this->adminUser)->post(route('admin.settlements.release', $this->settlement), [
            'note' => 'IFSC updated and re-verified',
        ]);
        $responseRelease->assertRedirect();
        $this->settlement->refresh();
        $this->assertEquals(SettlementStatus::PENDING, $this->settlement->status);
        $this->assertNull($this->settlement->hold_reason);
    }

    public function test_admin_can_add_adjustment(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.settlements.adjustment', $this->settlement), [
            'amount' => 150.00,
            'reason' => 'Special promotion credit',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->settlement->refresh();
        $this->assertEquals(150.00, (float) $this->settlement->adjustments);
        $this->assertEquals(4560.00, (float) $this->settlement->net);

        $this->assertDatabaseHas('settlement_items', [
            'seller_settlement_id' => $this->settlement->id,
            'type' => 'adjustment',
            'amount' => 150.00,
            'description' => 'Special promotion credit',
        ]);
    }

    public function test_admin_can_download_pdf_statement(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.settlements.pdf', $this->settlement));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_admin_can_download_items_csv(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.settlements.csv', $this->settlement));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Item ID', $content);
        $this->assertStringContainsString('Delivered order payout package #101', $content);
    }

    public function test_admin_can_export_summary_csv(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.settlements.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Settlement Number', $content);
        $this->assertStringContainsString('SET-TEST-998877', $content);
        $this->assertStringContainsString('Green Valley Organics', $content);
    }

    public function test_admin_can_trigger_batch_generation(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.settlements.generate'), [
            'seller_id' => $this->seller->id,
            'period_end' => now()->toDateString(),
        ]);

        $response->assertRedirect(route('admin.settlements.index'));
    }
}
