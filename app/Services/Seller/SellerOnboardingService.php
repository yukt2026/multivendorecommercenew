<?php

declare(strict_types=1);

namespace App\Services\Seller;

use App\Enums\SellerDocumentStatus;
use App\Enums\SellerDocumentType;
use App\Enums\SellerStatus;
use App\Models\Category;
use App\Models\Seller;
use App\Models\SellerBankAccount;
use App\Models\SellerDocument;
use App\Models\Store;
use App\Models\StoreLocation;
use App\Models\User;
use App\Services\Banking\BankVerificationServiceInterface;
use App\Services\Otp\OtpServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SellerOnboardingService
{
    public function __construct(
        protected OtpServiceInterface $otpService,
        protected BankVerificationServiceInterface $bankService
    ) {}

    /**
     * Get complete structured state for the onboarding wizard.
     *
     * @return array<string, mixed>
     */
    public function getOnboardingState(Seller $seller, User $user): array
    {
        $seller->loadMissing(['stores.location', 'bankAccounts', 'documents']);

        $primaryBank = $seller->primaryBankAccount ?: $seller->bankAccounts->first();
        $primaryStore = $seller->stores->first();
        $storeLocation = $primaryStore?->location;

        $documents = $seller->documents->keyBy(fn (SellerDocument $doc) => $doc->type->value);

        // Pre-generate secure signed URLs for existing uploaded documents (valid 60 mins)
        $documentUrls = [];
        foreach ($documents as $typeKey => $doc) {
            $documentUrls[$typeKey] = URL::temporarySignedRoute(
                'seller.onboarding.document.view',
                now()->addMinutes(60),
                ['document' => $doc->id]
            );
        }

        $categories = Category::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug']);

        // Check completion criteria per step
        $step1Done = $user->isPhoneVerified() || $user->isEmailVerified();
        $step2Done = !empty($seller->business_name) && !empty($seller->legal_name) && !empty($seller->category_focus);
        $step3Done = !empty($seller->pan);
        $step4Done = $primaryBank !== null && !empty($primaryBank->account_no);
        $step5Done = $storeLocation !== null && $storeLocation->lat != 0 && $storeLocation->lng != 0;
        $step6Done = $documents->has(SellerDocumentType::PAN_CARD->value) && $documents->has(SellerDocumentType::BANK_PROOF->value);
        $step7Done = $seller->isKycSubmitted() || $seller->isApproved();

        $completedCount = 0;
        if ($step1Done) $completedCount++;
        if ($step2Done) $completedCount++;
        if ($step3Done) $completedCount++;
        if ($step4Done) $completedCount++;
        if ($step5Done) $completedCount++;
        if ($step6Done) $completedCount++;
        if ($step7Done) $completedCount++;

        $progressPercentage = (int) round(($completedCount / 7) * 100);

        return [
            'seller' => $seller,
            'user' => $user,
            'primaryBank' => $primaryBank,
            'primaryStore' => $primaryStore,
            'storeLocation' => $storeLocation,
            'documents' => $documents,
            'documentUrls' => $documentUrls,
            'categories' => $categories,
            'currentStep' => $seller->onboarding_step ?: 1,
            'isLocked' => $seller->isEditingLocked(),
            'progressPercentage' => $progressPercentage,
            'stepStatus' => [
                1 => $step1Done,
                2 => $step2Done,
                3 => $step3Done,
                4 => $step4Done,
                5 => $step5Done,
                6 => $step6Done,
                7 => $step7Done,
            ],
        ];
    }

    /**
     * Dispatch OTP to seller's mobile number.
     *
     * @return array{success: bool, message: string}
     */
    public function sendMobileOtp(User $user): array
    {
        if (empty($user->phone)) {
            return ['success' => false, 'message' => 'No mobile number associated with your profile.'];
        }

        return $this->otpService->send($user->phone);
    }

    /**
     * Verify mobile OTP and mark phone_verified_at.
     */
    public function verifyMobileOtp(User $user, string $code): bool
    {
        if (empty($user->phone)) {
            return false;
        }

        $isValid = $this->otpService->verify($user->phone, $code);

        if ($isValid) {
            $user->update(['phone_verified_at' => now()]);

            $seller = $user->seller;
            if ($seller && $seller->onboarding_step < 2) {
                $seller->update(['onboarding_step' => 2]);
            }

            if (function_exists('activity')) {
                activity('seller_onboarding')->performedOn($user)->causedBy($user)->log('Mobile number verified via OTP');
            }
        }

        return $isValid;
    }

    /**
     * Verify email address immediately for onboarding.
     */
    public function markEmailVerified(User $user): void
    {
        if (!$user->isEmailVerified()) {
            $user->update(['email_verified_at' => now()]);
        }

        $seller = $user->seller;
        if ($seller && $seller->onboarding_step < 2) {
            $seller->update(['onboarding_step' => 2]);
        }
    }

    /**
     * Step 2: Save business profile information.
     *
     * @param array<string, mixed> $data
     */
    public function saveStep2Business(Seller $seller, array $data): Seller
    {
        $seller->update([
            'business_name' => $data['business_name'],
            'legal_name' => $data['legal_name'],
            'seller_type' => $data['seller_type'],
            'category_focus' => $data['category_focus'],
            'onboarding_step' => max($seller->onboarding_step ?? 1, 3),
        ]);

        return $seller->fresh();
    }

    /**
     * Step 3: Save PAN and GSTIN (stored encrypted).
     *
     * @param array<string, mixed> $data
     */
    public function saveStep3Tax(Seller $seller, array $data): Seller
    {
        $gstin = !empty($data['is_gst_exempt']) ? null : ($data['gstin'] ?? null);

        $seller->update([
            'pan' => strtoupper(trim((string) $data['pan'])),
            'gstin' => $gstin ? strtoupper(trim((string) $gstin)) : null,
            'onboarding_step' => max($seller->onboarding_step ?? 1, 4),
        ]);

        return $seller->fresh();
    }

    /**
     * Step 4: Save bank account with penny-drop verification test.
     *
     * @param array<string, mixed> $data
     */
    public function saveStep4Bank(Seller $seller, array $data): SellerBankAccount
    {
        return DB::transaction(function () use ($seller, $data) {
            // Run penny-drop test against service
            $verification = $this->bankService->verifyAccount(
                (string) $data['account_no'],
                (string) $data['ifsc'],
                (string) $data['account_holder']
            );

            if (!$verification['success']) {
                throw new InvalidArgumentException($verification['message'] ?? 'Bank account verification failed.');
            }

            // Find or initialize primary account
            $bankAccount = $seller->primaryBankAccount ?: $seller->bankAccounts()->first();

            if ($bankAccount) {
                $bankAccount->update([
                    'account_holder' => $data['account_holder'],
                    'account_no' => $data['account_no'],
                    'ifsc' => strtoupper(trim((string) $data['ifsc'])),
                    'bank_name' => $data['bank_name'],
                    'branch_name' => $data['branch_name'] ?? null,
                    'is_primary' => true,
                    'verified_at' => now(),
                ]);
            } else {
                $bankAccount = $seller->bankAccounts()->create([
                    'account_holder' => $data['account_holder'],
                    'account_no' => $data['account_no'],
                    'ifsc' => strtoupper(trim((string) $data['ifsc'])),
                    'bank_name' => $data['bank_name'],
                    'branch_name' => $data['branch_name'] ?? null,
                    'is_primary' => true,
                    'verified_at' => now(),
                ]);
            }

            $seller->update([
                'onboarding_step' => max($seller->onboarding_step ?? 1, 5),
            ]);

            return $bankAccount;
        });
    }

    /**
     * Step 5: Save physical store/business location with coordinates.
     *
     * @param array<string, mixed> $data
     */
    public function saveStep5Address(Seller $seller, array $data): StoreLocation
    {
        return DB::transaction(function () use ($seller, $data) {
            $store = $seller->stores()->first();

            if (!$store) {
                $baseSlug = Str::slug($seller->business_name ?: 'store');
                $slug = $baseSlug . '-' . $seller->id;

                $store = $seller->stores()->create([
                    'name' => $seller->business_name ?: 'Default Store',
                    'slug' => $slug,
                    'description' => 'Official storefront for ' . ($seller->business_name ?: 'merchant'),
                    'status' => 'draft',
                    'accepts_quick_commerce' => true,
                    'accepts_pickup' => true,
                ]);
            }

            $location = StoreLocation::updateOrCreate(
                ['store_id' => $store->id],
                [
                    'address' => $data['address'],
                    'city' => $data['city'],
                    'state' => $data['state'],
                    'pincode' => $data['pincode'],
                    'lat' => $data['lat'],
                    'lng' => $data['lng'],
                ]
            );

            $seller->update([
                'onboarding_step' => max($seller->onboarding_step ?? 1, 6),
            ]);

            return $location;
        });
    }

    /**
     * Step 6: Store KYC documents in private disk and register records.
     *
     * @param array<string, UploadedFile|null> $files
     * @return array<string, SellerDocument>
     */
    public function saveStep6Documents(Seller $seller, array $files): array
    {
        $typeMapping = [
            'pan_card' => SellerDocumentType::PAN_CARD,
            'gst_certificate' => SellerDocumentType::GST_CERTIFICATE,
            'bank_proof' => SellerDocumentType::BANK_PROOF,
            'address_proof' => SellerDocumentType::ADDRESS_PROOF,
        ];

        $saved = [];

        foreach ($typeMapping as $field => $docType) {
            if (isset($files[$field]) && $files[$field] instanceof UploadedFile) {
                $file = $files[$field];
                $extension = $file->getClientOriginalExtension() ?: 'bin';
                $fileName = Str::uuid()->toString() . '.' . $extension;
                $storagePath = 'seller-documents/' . $seller->id . '/' . $fileName;

                // Save to private disk
                Storage::disk('private')->putFileAs('seller-documents/' . $seller->id, $file, $fileName);

                $doc = SellerDocument::updateOrCreate(
                    [
                        'seller_id' => $seller->id,
                        'type' => $docType,
                    ],
                    [
                        'file_path' => $storagePath,
                        'status' => SellerDocumentStatus::PENDING,
                        'remarks' => null,
                    ]
                );

                $saved[$field] = $doc;
            }
        }

        $seller->update([
            'onboarding_step' => max($seller->onboarding_step ?? 1, 7),
        ]);

        return $saved;
    }

    /**
     * Step 7: Final submission of KYC application.
     */
    public function submitKyc(Seller $seller): void
    {
        // Guard check: ensure essential pieces exist
        if (empty($seller->pan)) {
            throw new InvalidArgumentException('PAN details are required before submitting KYC.');
        }

        $seller->update([
            'status' => SellerStatus::KYC_SUBMITTED,
            'onboarding_step' => 7,
            'submitted_at' => now(),
            'rejection_reason' => null,
        ]);

        if (function_exists('activity')) {
            activity('seller_onboarding')
                ->performedOn($seller)
                ->causedBy($seller->user)
                ->log('Submitted complete KYC onboarding package for admin review');
        }
    }

    /**
     * Re-submit after rejection: unlock application back to PENDING.
     */
    public function resubmitKyc(Seller $seller): void
    {
        if ($seller->status !== SellerStatus::REJECTED) {
            throw new InvalidArgumentException('Only rejected applications can be re-opened for resubmission.');
        }

        $seller->update([
            'status' => SellerStatus::PENDING,
            'onboarding_step' => 6, // send them to review/upload step
        ]);

        if (function_exists('activity')) {
            activity('seller_onboarding')
                ->performedOn($seller)
                ->causedBy($seller->user)
                ->log('Re-opened rejected KYC application for correction');
        }
    }
}
