<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\Step1VerifyRequest;
use App\Http\Requests\Seller\Step2BusinessRequest;
use App\Http\Requests\Seller\Step3TaxRequest;
use App\Http\Requests\Seller\Step4BankRequest;
use App\Http\Requests\Seller\Step5AddressRequest;
use App\Http\Requests\Seller\Step6DocumentsRequest;
use App\Http\Requests\Seller\SubmitKycRequest;
use App\Models\SellerDocument;
use App\Services\Banking\BankVerificationServiceInterface;
use App\Services\Seller\SellerOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OnboardingController extends Controller
{
    public function __construct(
        protected SellerOnboardingService $onboardingService,
        protected BankVerificationServiceInterface $bankService
    ) {}

    /**
     * Display the 7-step onboarding wizard.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $seller = $user?->seller;

        abort_if(!$seller, 403, 'Seller account profile not found.');

        $state = $this->onboardingService->getOnboardingState($seller, $user);

        // Determine which step is actively selected (defaults to seller's current progress)
        $requestedStep = (int) $request->query('step', $state['currentStep']);
        if ($requestedStep < 1 || $requestedStep > 7) {
            $requestedStep = 1;
        }

        return view('seller.onboarding', array_merge($state, [
            'activeStep' => $requestedStep,
        ]));
    }

    /**
     * Send OTP to seller's registered phone.
     */
    public function sendPhoneOtp(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_if(!$user, 401);

        $result = $this->onboardingService->sendMobileOtp($user);

        return response()->json($result);
    }

    /**
     * Verify phone OTP.
     */
    public function verifyPhoneOtp(Step1VerifyRequest $request): JsonResponse|RedirectResponse
    {
        $user = Auth::user();
        abort_if(!$user, 401);

        $verified = $this->onboardingService->verifyMobileOtp($user, (string) $request->input('otp'));

        if (!$verified) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Invalid or expired OTP code.'], 422);
            }
            return back()->withErrors(['otp' => 'Invalid or expired OTP code.']);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Mobile number successfully verified!']);
        }

        return redirect()->route('seller.onboarding', ['step' => 2])
            ->with('success', 'Mobile phone verified successfully.');
    }

    /**
     * Instant email verification action.
     */
    public function verifyEmail(Request $request): RedirectResponse
    {
        $user = Auth::user();
        abort_if(!$user, 401);

        $this->onboardingService->markEmailVerified($user);

        return redirect()->route('seller.onboarding', ['step' => 2])
            ->with('success', 'Email address marked verified.');
    }

    /**
     * Save Step 2: Business information.
     */
    public function saveStep2(Step2BusinessRequest $request): RedirectResponse
    {
        $seller = Auth::user()->seller;
        $this->onboardingService->saveStep2Business($seller, $request->validated());

        $nextStep = $request->has('save_and_continue') ? 3 : 2;

        return redirect()->route('seller.onboarding', ['step' => $nextStep])
            ->with('success', 'Business information saved.');
    }

    /**
     * Save Step 3: PAN and GST.
     */
    public function saveStep3(Step3TaxRequest $request): RedirectResponse
    {
        $seller = Auth::user()->seller;
        $this->onboardingService->saveStep3Tax($seller, $request->validated());

        $nextStep = $request->has('save_and_continue') ? 4 : 3;

        return redirect()->route('seller.onboarding', ['step' => $nextStep])
            ->with('success', 'Tax identification details saved securely.');
    }

    /**
     * Run penny-drop validation preview via AJAX.
     */
    public function previewBankVerification(Request $request): JsonResponse
    {
        $request->validate([
            'account_no' => ['required', 'string', 'min:8'],
            'ifsc' => ['required', 'string'],
            'account_holder' => ['required', 'string'],
        ]);

        $result = $this->bankService->verifyAccount(
            (string) $request->input('account_no'),
            (string) $request->input('ifsc'),
            (string) $request->input('account_holder')
        );

        return response()->json($result);
    }

    /**
     * Save Step 4: Bank account details.
     */
    public function saveStep4(Step4BankRequest $request): RedirectResponse
    {
        $seller = Auth::user()->seller;

        try {
            $this->onboardingService->saveStep4Bank($seller, $request->validated());
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['ifsc' => $e->getMessage()]);
        }

        $nextStep = $request->has('save_and_continue') ? 5 : 4;

        return redirect()->route('seller.onboarding', ['step' => $nextStep])
            ->with('success', 'Bank account verified & saved.');
    }

    /**
     * Save Step 5: Store physical address and map coordinates.
     */
    public function saveStep5(Step5AddressRequest $request): RedirectResponse
    {
        $seller = Auth::user()->seller;
        $this->onboardingService->saveStep5Address($seller, $request->validated());

        $nextStep = $request->has('save_and_continue') ? 6 : 5;

        return redirect()->route('seller.onboarding', ['step' => $nextStep])
            ->with('success', 'Store location and map pin saved.');
    }

    /**
     * Save Step 6: KYC Document Uploads.
     */
    public function saveStep6(Step6DocumentsRequest $request): RedirectResponse
    {
        $seller = Auth::user()->seller;
        $this->onboardingService->saveStep6Documents($seller, $request->file());

        $nextStep = $request->has('save_and_continue') ? 7 : 6;

        return redirect()->route('seller.onboarding', ['step' => $nextStep])
            ->with('success', 'KYC compliance documents uploaded securely.');
    }

    /**
     * Download or view private KYC document via signed URL.
     */
    public function downloadDocument(Request $request, SellerDocument $document): StreamedResponse
    {
        // Enforce valid signature
        if (!$request->hasValidSignature()) {
            abort(403, 'Document access link has expired or is invalid.');
        }

        $user = Auth::user();

        // Ensure user is authorized: document owner or admin
        $isOwner = $user && $user->seller && $user->seller->id === $document->seller_id;
        $isAdmin = $user && $user->hasAnyRole(['super_admin', 'admin']);

        abort_if(!$isOwner && !$isAdmin, 403, 'Unauthorized access to document.');

        if (!Storage::disk('private')->exists($document->file_path)) {
            abort(404, 'Document file not found on private storage.');
        }

        return Storage::disk('private')->response($document->file_path);
    }

    /**
     * Submit final KYC package for review.
     */
    public function submit(SubmitKycRequest $request): RedirectResponse
    {
        $seller = Auth::user()->seller;

        try {
            $this->onboardingService->submitKyc($seller);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        return redirect()->route('seller.onboarding')
            ->with('success', 'KYC application submitted successfully! Our compliance team will review your account.');
    }

    /**
     * Re-submit KYC application if rejected.
     */
    public function resubmit(Request $request): RedirectResponse
    {
        $seller = Auth::user()->seller;

        try {
            $this->onboardingService->resubmitKyc($seller);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        return redirect()->route('seller.onboarding', ['step' => 6])
            ->with('success', 'Application unlocked for corrections. Please review your documents and resubmit.');
    }
}
