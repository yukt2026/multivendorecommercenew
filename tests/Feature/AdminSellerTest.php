<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\SellerDocumentStatus;
use App\Enums\SellerDocumentType;
use App\Enums\SellerStatus;
use App\Enums\SellerType;
use App\Enums\StoreStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerBankAccount;
use App\Models\SellerDocument;
use App\Models\Store;
use App\Models\StoreLocation;
use App\Models\User;
use App\Notifications\SellerApprovedNotification;
use App\Notifications\SellerRejectedNotification;
use App\Notifications\SellerSuspendedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdminSellerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $supportUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // Super Admin / Operations Lead (has all permissions including sellers.approve, sellers.suspend)
        $this->adminUser = User::factory()->create([
            'email' => 'admin.ops@onestall.in',
            'name' => 'Operations Director',
        ]);
        $this->adminUser->assignRole('admin');

        // Support Admin (does NOT have sellers.approve or sellers.suspend)
        $this->supportUser = User::factory()->create([
            'email' => 'support.agent@onestall.in',
            'name' => 'Support Desk Lead',
        ]);
        $this->supportUser->assignRole('support_admin');
    }

    public function test_admin_can_view_sellers_list_with_filters_and_search(): void
    {
        $sellerUser1 = User::factory()->create(['name' => 'Bangalore Organic Store', 'email' => 'bangalore@organic.in']);
        $seller1 = Seller::factory()->create([
            'user_id' => $sellerUser1->id,
            'business_name' => 'Bangalore Organic Emporium',
            'seller_type' => SellerType::LOCAL_STORE,
            'status' => SellerStatus::PENDING,
        ]);
        $store1 = Store::factory()->create(['seller_id' => $seller1->id]);
        StoreLocation::create([
            'store_id' => $store1->id,
            'address' => 'Indiranagar 100ft road',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560038',
            'lat' => 12.9716,
            'lng' => 77.5946,
        ]);

        $sellerUser2 = User::factory()->create(['name' => 'Delhi Electronics', 'email' => 'delhi@electro.in']);
        $seller2 = Seller::factory()->create([
            'user_id' => $sellerUser2->id,
            'business_name' => 'Delhi Gadget Hub',
            'seller_type' => SellerType::MARKETPLACE,
            'status' => SellerStatus::APPROVED,
        ]);

        // 1. Unfiltered list
        $response = $this->actingAs($this->adminUser)->get('/admin/sellers');
        $response->assertStatus(200);
        $response->assertSee('Bangalore Organic Emporium');
        $response->assertSee('Delhi Gadget Hub');

        // 2. Filter by status: pending
        $pendingResponse = $this->actingAs($this->adminUser)->get('/admin/sellers?status=pending');
        $pendingResponse->assertStatus(200);
        $pendingResponse->assertSee('Bangalore Organic Emporium');
        $pendingResponse->assertDontSee('Delhi Gadget Hub');

        // 3. Filter by search query
        $searchResponse = $this->actingAs($this->adminUser)->get('/admin/sellers?search=Bangalore');
        $searchResponse->assertStatus(200);
        $searchResponse->assertSee('Bangalore Organic Emporium');
        $searchResponse->assertDontSee('Delhi Gadget Hub');

        // 4. Filter by city
        $cityResponse = $this->actingAs($this->adminUser)->get('/admin/sellers?city=Bengaluru');
        $cityResponse->assertStatus(200);
        $cityResponse->assertSee('Bangalore Organic Emporium');
        $cityResponse->assertDontSee('Delhi Gadget Hub');
    }

    public function test_sellers_list_can_be_exported_to_csv(): void
    {
        $sellerUser = User::factory()->create();
        $seller = Seller::factory()->create([
            'user_id' => $sellerUser->id,
            'business_name' => 'Exportable Merchant LLP',
        ]);

        $response = $this->actingAs($this->adminUser)->get('/admin/sellers/export');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="onestall_sellers_export_', (string) $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Business Name', $content);
        $this->assertStringContainsString('Exportable Merchant LLP', $content);
    }

    public function test_seller_detail_page_renders_with_tabs_and_statistics(): void
    {
        $sellerUser = User::factory()->create(['name' => 'Ramesh Merchant', 'email' => 'ramesh@retail.in']);
        $seller = Seller::factory()->create([
            'user_id' => $sellerUser->id,
            'business_name' => 'Ramesh Kirana Mart',
            'legal_name' => 'Ramesh Retail Private Limited',
            'status' => SellerStatus::PENDING,
            'pan' => 'ABCDE1234F',
            'gstin' => '29ABCDE1234F1Z5',
        ]);

        $store = Store::factory()->create(['seller_id' => $seller->id, 'name' => 'Ramesh Kirana Store']);
        StoreLocation::create([
            'store_id' => $store->id,
            'address' => 'Brigade Road',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560001',
            'lat' => 12.9716,
            'lng' => 77.5946,
        ]);

        SellerDocument::create([
            'seller_id' => $seller->id,
            'type' => SellerDocumentType::PAN_CARD,
            'file_path' => 'documents/sellers/pan_sample.pdf',
            'status' => SellerDocumentStatus::PENDING,
        ]);

        SellerBankAccount::create([
            'seller_id' => $seller->id,
            'account_holder' => 'Ramesh Kirana Mart',
            'account_no' => '987654321000',
            'ifsc' => 'HDFC0001234',
            'bank_name' => 'HDFC Bank',
            'branch_name' => 'MG Road',
            'is_primary' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get("/admin/sellers/{$seller->id}");

        $response->assertStatus(200);
        $response->assertSee('Ramesh Kirana Mart');
        $response->assertSee('Ramesh Retail Private Limited');
        $response->assertSee('ABCDE1234F');
        $response->assertSee('29ABCDE1234F1Z5');
        $response->assertSee('PAN Card');
        $response->assertSee('HDFC Bank');
        $response->assertSee('Overview');
        $response->assertSee('Business &amp; KYC', false);
        $response->assertSee('Commission Override');
        $response->assertSee('Activity Log');
    }

    public function test_unauthorized_admin_cannot_approve_seller(): void
    {
        $sellerUser = User::factory()->create();
        $seller = Seller::factory()->create([
            'user_id' => $sellerUser->id,
            'status' => SellerStatus::PENDING,
        ]);

        // Support desk user lacks 'sellers.approve' permission
        $response = $this->actingAs($this->supportUser)->post("/admin/sellers/{$seller->id}/approve");

        $response->assertStatus(403);
        $this->assertEquals(SellerStatus::PENDING, $seller->fresh()->status);
    }

    public function test_authorized_admin_can_approve_seller_with_workflow(): void
    {
        Notification::fake();

        $sellerUser = User::factory()->create(['name' => 'Fresh Dairy Owner']);
        $seller = Seller::factory()->create([
            'user_id' => $sellerUser->id,
            'business_name' => 'Fresh Milk Co',
            'seller_type' => SellerType::LOCAL_STORE,
            'status' => SellerStatus::PENDING,
        ]);

        // Prior to approval, seller user does not have seller_owner role
        $this->assertFalse($sellerUser->hasRole('seller_owner'));

        $response = $this->actingAs($this->adminUser)->post("/admin/sellers/{$seller->id}/approve");

        $response->assertRedirect();
        $seller->refresh();

        // 1. Status updated to approved with timestamps
        $this->assertEquals(SellerStatus::APPROVED, $seller->status);
        $this->assertNotNull($seller->approved_at);
        $this->assertEquals($this->adminUser->id, $seller->approved_by);

        // 2. Default store auto-created with unique slug
        $this->assertCount(1, $seller->stores);
        $store = $seller->stores->first();
        $this->assertEquals('Fresh Milk Co', $store->name);
        $this->assertEquals(StoreStatus::ACTIVE, $store->status);
        $this->assertTrue($store->accepts_quick_commerce);

        // 3. seller_owner role assigned to user
        $this->assertTrue($sellerUser->fresh()->hasRole('seller_owner'));

        // 4. Queued notification sent
        Notification::assertSentTo($sellerUser, SellerApprovedNotification::class);

        // 5. Activity log generated
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Seller::class,
            'subject_id' => $seller->id,
            'causer_id' => $this->adminUser->id,
        ]);
    }

    public function test_rejecting_seller_requires_mandatory_remarks(): void
    {
        Notification::fake();

        $sellerUser = User::factory()->create();
        $seller = Seller::factory()->create([
            'user_id' => $sellerUser->id,
            'status' => SellerStatus::KYC_SUBMITTED,
        ]);

        // Attempting to reject without reason fails validation
        $failResponse = $this->actingAs($this->adminUser)->post("/admin/sellers/{$seller->id}/reject", [
            'reason' => '',
        ]);
        $failResponse->assertSessionHasErrors('reason');

        // Providing valid reason
        $response = $this->actingAs($this->adminUser)->post("/admin/sellers/{$seller->id}/reject", [
            'reason' => 'Invalid GSTIN number provided. Please upload valid certificate.',
        ]);

        $response->assertRedirect();
        $seller->refresh();

        $this->assertEquals(SellerStatus::REJECTED, $seller->status);
        $this->assertEquals('Invalid GSTIN number provided. Please upload valid certificate.', $seller->rejection_reason);

        // Notification sent
        Notification::assertSentTo($sellerUser, SellerRejectedNotification::class);

        // Audit log verified
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Seller::class,
            'subject_id' => $seller->id,
            'description' => "Rejected seller application #{$seller->id} ({$seller->business_name}). Reason: Invalid GSTIN number provided. Please upload valid certificate.",
        ]);
    }

    public function test_suspend_and_reactivate_workflow(): void
    {
        Notification::fake();

        $sellerUser = User::factory()->create();
        $seller = Seller::factory()->create([
            'user_id' => $sellerUser->id,
            'status' => SellerStatus::APPROVED,
        ]);

        $store = Store::factory()->create([
            'seller_id' => $seller->id,
            'status' => StoreStatus::ACTIVE,
        ]);

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'status' => ProductStatus::ACTIVE,
        ]);

        // 1. Suspend seller without reason fails
        $failResponse = $this->actingAs($this->adminUser)->post("/admin/sellers/{$seller->id}/suspend", [
            'reason' => '',
        ]);
        $failResponse->assertSessionHasErrors('reason');

        // 2. Suspend with reason succeeds and deactivates storefront + products
        $suspendResponse = $this->actingAs($this->adminUser)->post("/admin/sellers/{$seller->id}/suspend", [
            'reason' => 'Multiple counterfeit product flags received from consumers.',
        ]);

        $suspendResponse->assertRedirect();
        $seller->refresh();

        $this->assertEquals(SellerStatus::SUSPENDED, $seller->status);
        $this->assertEquals('Multiple counterfeit product flags received from consumers.', $seller->suspension_reason);
        $this->assertEquals(StoreStatus::SUSPENDED, $store->fresh()->status);
        $this->assertEquals(ProductStatus::INACTIVE, $product->fresh()->status);

        Notification::assertSentTo($sellerUser, SellerSuspendedNotification::class);

        // 3. Reactivate seller restores storefront and products
        $reactivateResponse = $this->actingAs($this->adminUser)->post("/admin/sellers/{$seller->id}/reactivate", [
            'reason' => 'Vendor submitted authentic authorized distributor invoices.',
        ]);

        $reactivateResponse->assertRedirect();
        $seller->refresh();

        $this->assertEquals(SellerStatus::APPROVED, $seller->status);
        $this->assertNull($seller->suspension_reason);
        $this->assertEquals(StoreStatus::ACTIVE, $store->fresh()->status);
        $this->assertEquals(ProductStatus::ACTIVE, $product->fresh()->status);
    }

    public function test_admin_can_approve_and_reject_individual_kyc_document(): void
    {
        $sellerUser = User::factory()->create();
        $seller = Seller::factory()->create(['user_id' => $sellerUser->id]);

        $document = SellerDocument::create([
            'seller_id' => $seller->id,
            'type' => SellerDocumentType::FSSAI_LICENSE,
            'file_path' => 'documents/fssai.pdf',
            'status' => SellerDocumentStatus::PENDING,
        ]);

        // Approve document
        $approveResponse = $this->actingAs($this->adminUser)->post("/admin/sellers/{$seller->id}/documents/{$document->id}/review", [
            'status' => 'approved',
            'remarks' => 'FSSAI License verified with national food safety registry.',
        ]);

        $approveResponse->assertRedirect();
        $this->assertEquals(SellerDocumentStatus::APPROVED, $document->fresh()->status);

        // Reject document without remarks fails
        $failResponse = $this->actingAs($this->adminUser)->post("/admin/sellers/{$seller->id}/documents/{$document->id}/review", [
            'status' => 'rejected',
            'remarks' => '',
        ]);
        $failResponse->assertSessionHasErrors('remarks');

        // Reject document with remarks
        $rejectResponse = $this->actingAs($this->adminUser)->post("/admin/sellers/{$seller->id}/documents/{$document->id}/review", [
            'status' => 'rejected',
            'remarks' => 'Expired validity period on certificate.',
        ]);
        $rejectResponse->assertRedirect();
        $this->assertEquals(SellerDocumentStatus::REJECTED, $document->fresh()->status);
        $this->assertEquals('Expired validity period on certificate.', $document->fresh()->remarks);
    }

    public function test_admin_can_verify_and_unverify_bank_account(): void
    {
        $sellerUser = User::factory()->create();
        $seller = Seller::factory()->create(['user_id' => $sellerUser->id]);

        $bank = SellerBankAccount::create([
            'seller_id' => $seller->id,
            'account_holder' => 'Test Merchant Account',
            'account_no' => '123456789012',
            'ifsc' => 'SBIN0001234',
            'bank_name' => 'State Bank of India',
            'is_primary' => true,
        ]);

        $this->assertNull($bank->verified_at);

        // Verify bank account
        $verifyResponse = $this->actingAs($this->adminUser)->post("/admin/sellers/{$seller->id}/bank-accounts/{$bank->id}/verify", [
            'verified' => '1',
        ]);
        $verifyResponse->assertRedirect();
        $this->assertNotNull($bank->fresh()->verified_at);

        // Unverify bank account
        $unverifyResponse = $this->actingAs($this->adminUser)->post("/admin/sellers/{$seller->id}/bank-accounts/{$bank->id}/verify", [
            'verified' => '0',
        ]);
        $unverifyResponse->assertRedirect();
        $this->assertNull($bank->fresh()->verified_at);
    }

    public function test_admin_can_update_commission_override(): void
    {
        $sellerUser = User::factory()->create();
        $seller = Seller::factory()->create([
            'user_id' => $sellerUser->id,
            'commission_override' => null,
        ]);

        // Set commission override to 7.50%
        $response = $this->actingAs($this->adminUser)->post("/admin/sellers/{$seller->id}/commission", [
            'commission_override' => 7.50,
            'remarks' => 'Special promotion partnership agreement.',
        ]);

        $response->assertRedirect();
        $this->assertEquals(7.50, $seller->fresh()->commission_override);

        // Reset to platform default (null)
        $resetResponse = $this->actingAs($this->adminUser)->post("/admin/sellers/{$seller->id}/commission", [
            'commission_override' => null,
            'remarks' => 'Reverted to platform default.',
        ]);
        $resetResponse->assertRedirect();
        $this->assertNull($seller->fresh()->commission_override);
    }

    public function test_admin_can_execute_bulk_actions_on_merchants(): void
    {
        Notification::fake();

        $seller1 = Seller::factory()->create(['status' => SellerStatus::PENDING]);
        $seller2 = Seller::factory()->create(['status' => SellerStatus::PENDING]);

        $response = $this->actingAs($this->adminUser)->post('/admin/sellers/bulk-action', [
            'action' => 'approve',
            'seller_ids' => [$seller1->id, $seller2->id],
        ]);

        $response->assertRedirect();
        $this->assertEquals(SellerStatus::APPROVED, $seller1->fresh()->status);
        $this->assertEquals(SellerStatus::APPROVED, $seller2->fresh()->status);
    }
}
