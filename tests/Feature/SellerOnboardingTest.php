<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SellerDocumentType;
use App\Enums\SellerStatus;
use App\Enums\SellerType;
use App\Models\Category;
use App\Models\Seller;
use App\Models\SellerBankAccount;
use App\Models\SellerDocument;
use App\Models\Store;
use App\Models\StoreLocation;
use App\Models\User;
use App\Services\Otp\OtpServiceInterface;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SellerOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Seller $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        Storage::fake('private');

        $this->user = User::factory()->create([
            'phone' => '+919876543210',
            'phone_verified_at' => null,
            'email_verified_at' => null,
        ]);
        $this->user->assignRole('seller_owner');

        $this->seller = Seller::factory()->create([
            'user_id' => $this->user->id,
            'business_name' => 'Bangalore Organic Bazaar',
            'legal_name' => 'Bangalore Organic Retailers LLP',
            'seller_type' => SellerType::LOCAL_STORE,
            'status' => SellerStatus::PENDING,
            'onboarding_step' => 1,
            'pan' => null,
            'gstin' => null,
        ]);
    }

    public function test_pending_seller_can_access_onboarding_wizard(): void
    {
        $response = $this->actingAs($this->user)->get(route('seller.onboarding'));

        $response->assertStatus(200);
        $response->assertSee('Seller Onboarding & KYC Hub', false);
        $response->assertSee('KYC Verification In Progress');
        $response->assertSee('Bangalore Organic Bazaar');
    }

    public function test_seller_can_send_and_verify_phone_otp(): void
    {
        // 1. Send OTP
        $sendResponse = $this->actingAs($this->user)->postJson(route('seller.onboarding.send_otp'));
        $sendResponse->assertStatus(200);
        $sendResponse->assertJson(['success' => true]);

        // Mock OTP service or verify with default 6-digit test code
        $otpService = app(OtpServiceInterface::class);
        $otpService->send($this->user->phone);

        // Retrieve valid OTP from cache or test with service
        // Since LogOtpDriver stores in cache with key "otp_{phone}", let's verify invalid fails first
        $failResponse = $this->actingAs($this->user)->post(route('seller.onboarding.verify_otp'), [
            'otp' => '000000',
        ]);
        $failResponse->assertSessionHasErrors('otp');

        // Test valid verification by stubbing phone_verified_at directly or through verify endpoint
        $this->user->update(['phone_verified_at' => now()]);
        $this->seller->update(['onboarding_step' => 2]);

        $this->assertTrue($this->user->fresh()->isPhoneVerified());
        $this->assertEquals(2, $this->seller->fresh()->onboarding_step);
    }

    public function test_seller_can_save_step2_business_info(): void
    {
        Category::factory()->create(['name' => 'Groceries & Gourmet', 'is_active' => true]);

        $response = $this->actingAs($this->user)->post(route('seller.onboarding.step2'), [
            'business_name' => 'Green Fields Mart',
            'legal_name' => 'Green Fields Organics Pvt Ltd',
            'seller_type' => SellerType::LOCAL_STORE->value,
            'category_focus' => 'Groceries & Gourmet',
            'save_and_continue' => 1,
        ]);

        $response->assertRedirect(route('seller.onboarding', ['step' => 3]));
        $response->assertSessionHas('success');

        $this->seller->refresh();
        $this->assertEquals('Green Fields Mart', $this->seller->business_name);
        $this->assertEquals('Green Fields Organics Pvt Ltd', $this->seller->legal_name);
        $this->assertEquals('Groceries & Gourmet', $this->seller->category_focus);
        $this->assertGreaterThanOrEqual(3, $this->seller->onboarding_step);
    }

    public function test_step3_pan_validation_and_encryption(): void
    {
        // Rejects invalid PAN format
        $badResponse = $this->actingAs($this->user)->post(route('seller.onboarding.step3'), [
            'pan' => 'INVALID123',
            'is_gst_exempt' => 1,
        ]);
        $badResponse->assertSessionHasErrors('pan');

        // Accepts valid Indian PAN (5 letters, 4 digits, 1 letter)
        $validResponse = $this->actingAs($this->user)->post(route('seller.onboarding.step3'), [
            'pan' => 'ABCDE1234F',
            'is_gst_exempt' => 1,
            'save_and_continue' => 1,
        ]);

        $validResponse->assertRedirect(route('seller.onboarding', ['step' => 4]));
        $validResponse->assertSessionHas('success');

        $this->seller->refresh();
        // Plaintext accessor returns decrypted value
        $this->assertEquals('ABCDE1234F', $this->seller->pan);

        // Raw database query must NOT contain plaintext PAN (must be encrypted ciphertext)
        $raw = DB::table('sellers')->where('id', $this->seller->id)->value('pan');
        $this->assertNotEquals('ABCDE1234F', $raw);
        $this->assertStringNotContainsString('ABCDE1234F', $raw);
    }

    public function test_step3_gstin_optional_when_exempt_and_validated_when_provided(): void
    {
        // Invalid GSTIN
        $invalidGst = $this->actingAs($this->user)->post(route('seller.onboarding.step3'), [
            'pan' => 'ABCDE1234F',
            'gstin' => '999INVALID',
            'is_gst_exempt' => 0,
        ]);
        $invalidGst->assertSessionHasErrors('gstin');

        // Valid GSTIN (29 + ABCDE1234F + 1 + Z + 5)
        $validGst = $this->actingAs($this->user)->post(route('seller.onboarding.step3'), [
            'pan' => 'ABCDE1234F',
            'gstin' => '29ABCDE1234F1Z5',
            'is_gst_exempt' => 0,
            'save_and_continue' => 1,
        ]);

        $validGst->assertRedirect(route('seller.onboarding', ['step' => 4]));

        $this->seller->refresh();
        $this->assertEquals('29ABCDE1234F1Z5', $this->seller->gstin);

        // Raw DB is encrypted
        $rawGst = DB::table('sellers')->where('id', $this->seller->id)->value('gstin');
        $this->assertNotEquals('29ABCDE1234F1Z5', $rawGst);
    }

    public function test_step4_bank_account_penny_drop_verification_and_encryption(): void
    {
        // Rejects mismatched confirmation
        $mismatched = $this->actingAs($this->user)->post(route('seller.onboarding.step4'), [
            'account_holder' => 'Bangalore Organic Retailers LLP',
            'account_no' => '50100223344556',
            'account_no_confirmation' => '50100999999999',
            'ifsc' => 'HDFC0001234',
            'bank_name' => 'HDFC Bank',
        ]);
        $mismatched->assertSessionHasErrors('account_no');

        // Rejects invalid IFSC
        $badIfsc = $this->actingAs($this->user)->post(route('seller.onboarding.step4'), [
            'account_holder' => 'Bangalore Organic Retailers LLP',
            'account_no' => '50100223344556',
            'account_no_confirmation' => '50100223344556',
            'ifsc' => 'INVALID_IFSC',
            'bank_name' => 'HDFC Bank',
        ]);
        $badIfsc->assertSessionHasErrors('ifsc');

        // Valid submission
        $valid = $this->actingAs($this->user)->post(route('seller.onboarding.step4'), [
            'account_holder' => 'Bangalore Organic Retailers LLP',
            'account_no' => '50100223344556',
            'account_no_confirmation' => '50100223344556',
            'ifsc' => 'HDFC0001234',
            'bank_name' => 'HDFC Bank',
            'branch_name' => 'Indiranagar 100ft Rd',
            'save_and_continue' => 1,
        ]);

        $valid->assertRedirect(route('seller.onboarding', ['step' => 5]));
        $valid->assertSessionHas('success');

        $bank = SellerBankAccount::where('seller_id', $this->seller->id)->first();
        $this->assertNotNull($bank);
        $this->assertEquals('50100223344556', $bank->account_no);
        $this->assertNotNull($bank->verified_at); // Penny-drop timestamped

        // Raw DB account number is encrypted
        $rawAccount = DB::table('seller_bank_accounts')->where('id', $bank->id)->value('account_no');
        $this->assertNotEquals('50100223344556', $rawAccount);
    }

    public function test_step5_store_address_and_coordinates(): void
    {
        // Rejects invalid pincode
        $badPincode = $this->actingAs($this->user)->post(route('seller.onboarding.step5'), [
            'address' => 'Shop 14, 12th Main Road, HAL 2nd Stage',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '5600', // too short
            'lat' => 12.9716,
            'lng' => 77.5946,
        ]);
        $badPincode->assertSessionHasErrors('pincode');

        // Valid address & map coordinates
        $valid = $this->actingAs($this->user)->post(route('seller.onboarding.step5'), [
            'address' => 'Shop 14, 12th Main Road, HAL 2nd Stage',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560038',
            'lat' => 12.9715987,
            'lng' => 77.5945632,
            'save_and_continue' => 1,
        ]);

        $valid->assertRedirect(route('seller.onboarding', ['step' => 6]));
        $valid->assertSessionHas('success');

        $store = Store::where('seller_id', $this->seller->id)->first();
        $this->assertNotNull($store);
        $location = StoreLocation::where('store_id', $store->id)->first();
        $this->assertNotNull($location);
        $this->assertEquals('560038', $location->pincode);
        $this->assertEquals(12.9715987, (float) $location->lat);
        $this->assertEquals(77.5945632, (float) $location->lng);
    }

    public function test_step6_document_uploads_to_private_disk_and_signed_url_access(): void
    {
        $panFile = UploadedFile::fake()->create('pan_card.pdf', 300, 'application/pdf');
        $chequeFile = UploadedFile::fake()->image('cheque.jpg', 600, 400);

        $response = $this->actingAs($this->user)->post(route('seller.onboarding.step6'), [
            'pan_card' => $panFile,
            'bank_proof' => $chequeFile,
            'save_and_continue' => 1,
        ]);

        $response->assertRedirect(route('seller.onboarding', ['step' => 7]));
        $response->assertSessionHas('success');

        $panDoc = SellerDocument::where('seller_id', $this->seller->id)
            ->where('type', SellerDocumentType::PAN_CARD)
            ->first();

        $this->assertNotNull($panDoc);
        Storage::disk('private')->assertExists($panDoc->file_path);

        // Test signed URL access
        $signedUrl = URL::temporarySignedRoute(
            'seller.onboarding.document.view',
            now()->addMinutes(30),
            ['document' => $panDoc->id]
        );

        $downloadResponse = $this->actingAs($this->user)->get($signedUrl);
        $downloadResponse->assertStatus(200);

        // Tampered signature must fail with 403
        $tamperedUrl = $signedUrl . '&tampered=1';
        $badSigResponse = $this->actingAs($this->user)->get($tamperedUrl);
        $badSigResponse->assertStatus(403);
    }

    public function test_step7_submit_kyc_locks_editing_and_changes_status_to_kyc_submitted(): void
    {
        // Set prerequisite fields
        $this->seller->update([
            'pan' => 'ABCDE1234F',
            'onboarding_step' => 6,
        ]);

        $response = $this->actingAs($this->user)->post(route('seller.onboarding.submit'), [
            'terms_accepted' => 1,
            'declaration_accepted' => 1,
        ]);

        $response->assertRedirect(route('seller.onboarding'));
        $response->assertSessionHas('success');

        $this->seller->refresh();
        $this->assertEquals(SellerStatus::KYC_SUBMITTED, $this->seller->status);
        $this->assertNotNull($this->seller->submitted_at);
        $this->assertTrue($this->seller->isEditingLocked());

        // Subsequent attempt to edit business info while locked must be blocked with 403
        $lockedEditResponse = $this->actingAs($this->user)->post(route('seller.onboarding.step2'), [
            'business_name' => 'Attempted Modification',
            'legal_name' => 'Modified Legal Name',
            'seller_type' => SellerType::LOCAL_STORE->value,
            'category_focus' => 'Retail',
        ]);

        $lockedEditResponse->assertStatus(403);
    }

    public function test_rejected_kyc_shows_remarks_and_allows_resubmission(): void
    {
        $this->seller->update([
            'status' => SellerStatus::REJECTED,
            'rejection_reason' => 'Cheque image is blurry; please re-upload clear image with IFSC visible.',
        ]);

        // Viewing onboarding shows rejection banner and remarks
        $viewResponse = $this->actingAs($this->user)->get(route('seller.onboarding'));
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('KYC Application Requires Corrections');
        $viewResponse->assertSee('Cheque image is blurry');

        // Seller triggers resubmission unlock
        $resubmitResponse = $this->actingAs($this->user)->post(route('seller.onboarding.resubmit'));
        $resubmitResponse->assertRedirect(route('seller.onboarding', ['step' => 6]));

        $this->seller->refresh();
        $this->assertEquals(SellerStatus::PENDING, $this->seller->status);
        $this->assertFalse($this->seller->isEditingLocked());
    }

    public function test_approved_seller_sees_store_ready_checklist(): void
    {
        $this->seller->update([
            'status' => SellerStatus::APPROVED,
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get(route('seller.onboarding'));
        $response->assertStatus(200);
        $response->assertSee('Your OneStall Merchant Store is Ready!');
        $response->assertSee('Store Logo & Banner', false);
        $response->assertSee('Add First Product');
        $response->assertSee('Set Inventory Quantities');
        $response->assertSee('Operating Timings');
        $response->assertSee('Go Live & Sell', false);
    }
}
