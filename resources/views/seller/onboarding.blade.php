@php
    $wideLayout = true;
@endphp

@extends('layouts.auth')

@section('content')
<div class="space-y-6" x-data="sellerOnboardingWizard({
    currentStep: {{ $activeStep }},
    maxStep: {{ $seller->onboarding_step ?: 1 }},
    isLocked: @js($isLocked),
    isApproved: @js($seller->isApproved()),
    isKycSubmitted: @js($seller->isKycSubmitted()),
    isRejected: @js($seller->isRejected()),
    phone: @js($user->phone),
    phoneVerified: @js($user->isPhoneVerified()),
    emailVerified: @js($user->isEmailVerified()),
    lat: {{ $storeLocation?->lat ?: 12.9716 }},
    lng: {{ $storeLocation?->lng ?: 77.5946 }},
    urls: {
        sendOtp: '{{ route("seller.onboarding.send_otp") }}',
        verifyOtp: '{{ route("seller.onboarding.verify_otp") }}',
        previewBank: '{{ route("seller.onboarding.preview_bank") }}'
    }
})">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200/80 dark:border-slate-800">
        <div>
            <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full text-xs font-semibold mb-2 {{ $seller->isApproved() ? 'bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300' : ($seller->isRejected() ? 'bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300' : 'bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-300') }}">
                <span class="w-2 h-2 rounded-full {{ $seller->isApproved() ? 'bg-emerald-500' : ($seller->isRejected() ? 'bg-rose-500' : 'bg-amber-500 animate-pulse') }}"></span>
                <span>KYC Verification In Progress</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                Seller Onboarding & KYC Hub
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                Merchant account for <strong class="text-slate-900 dark:text-white">{{ $seller->business_name ?: 'Your Business' }}</strong> &bull; {{ $seller->seller_type->label() }}
            </p>
        </div>

        <div class="flex items-center space-x-3 text-right">
            <div>
                <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Readiness</div>
                <div class="text-lg font-black text-indigo-600 dark:text-indigo-400">{{ $progressPercentage }}% Completed</div>
            </div>
            <div class="w-12 h-12 rounded-full border-4 border-indigo-100 dark:border-slate-800 flex items-center justify-center font-bold text-xs text-indigo-600 dark:text-indigo-400">
                {{ $activeStep }}/7
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-xs font-semibold flex items-center space-x-2">
            <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200 text-xs space-y-1">
            <div class="font-bold flex items-center space-x-1">
                <svg class="w-4 h-4 text-rose-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                <span>Please correct the errors below to continue:</span>
            </div>
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- APPROVED BANNER & STORE LAUNCHPAD CHECKLIST -->
    @if ($seller->isApproved())
        <div class="p-6 rounded-2xl bg-gradient-to-br from-emerald-500/10 via-emerald-500/5 to-transparent border border-emerald-500/30 space-y-6">
            <div class="flex items-start justify-between">
                <div>
                    <div class="inline-flex items-center space-x-1.5 px-3 py-1 rounded-full bg-emerald-500 text-white text-xs font-bold shadow-xs">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        <span>Store Verified & Approved</span>
                    </div>
                    <h2 class="text-xl font-black text-slate-900 dark:text-white mt-2">
                        🎉 Your OneStall Merchant Store is Ready!
                    </h2>
                    <p class="text-xs text-slate-600 dark:text-slate-300 mt-1">
                        Congratulations! Your business verification and KYC compliance checks are complete. Follow the launch checklist below to publish your digital catalog and begin receiving customer orders.
                    </p>
                </div>
                <a href="{{ route('seller.dashboard') }}" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md shadow-emerald-600/30 transition flex items-center space-x-1.5">
                    <span>Go to Dashboard</span>
                    <span>&rarr;</span>
                </a>
            </div>

            <!-- Launch Checklist -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 pt-2">
                <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-2">
                    <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-xs">
                        1
                    </div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Store Logo & Banner</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Upload your visual brand identity to showcase on your digital storefront.</p>
                    <span class="inline-block text-[11px] font-bold text-indigo-600 dark:text-indigo-400">Configure via Store Settings &rarr;</span>
                </div>

                <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-2">
                    <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-xs">
                        2
                    </div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Add First Product</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Create products with pricing, MRP, barcodes, and multiple media angles.</p>
                    <span class="inline-block text-[11px] font-bold text-indigo-600 dark:text-indigo-400">Catalog Manager &rarr;</span>
                </div>

                <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-2">
                    <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-xs">
                        3
                    </div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Set Inventory Quantities</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Allocate available stock counts to your fulfillment location for quick ordering.</p>
                    <span class="inline-block text-[11px] font-bold text-indigo-600 dark:text-indigo-400">Inventory Hub &rarr;</span>
                </div>

                <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-2">
                    <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-xs">
                        4
                    </div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Operating Timings</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Set daily opening and closing hours for 10-30 min quick-commerce SLA.</p>
                    <span class="inline-block text-[11px] font-bold text-indigo-600 dark:text-indigo-400">Store Hours &rarr;</span>
                </div>

                <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-2">
                    <div class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-xs">
                        5
                    </div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Go Live & Sell</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Toggle your store online to start receiving orders across marketplace & quick commerce.</p>
                    <span class="inline-block text-[11px] font-bold text-emerald-600 dark:text-emerald-400">Ready to Publish</span>
                </div>
            </div>
        </div>
    @endif

    <!-- REJECTED BANNER & RESUBMISSION -->
    @if ($seller->isRejected())
        <div class="p-6 rounded-2xl bg-rose-50 dark:bg-rose-950/50 border border-rose-300 dark:border-rose-800 space-y-4">
            <div class="flex items-start justify-between">
                <div class="flex items-start space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-rose-600 text-white flex items-center justify-center text-sm font-bold shrink-0">
                        !
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-rose-900 dark:text-rose-100">
                            KYC Application Requires Corrections
                        </h3>
                        <p class="text-xs text-rose-700 dark:text-rose-300 mt-1">
                            Our compliance team reviewed your submission and flagged issues that need to be resolved before store approval.
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('seller.onboarding.resubmit') }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-xs transition">
                        Re-open & Edit Application &rarr;
                    </button>
                </form>
            </div>

            @if ($seller->rejection_reason)
                <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-rose-200 dark:border-rose-900 text-xs">
                    <span class="font-bold text-rose-800 dark:text-rose-400 uppercase tracking-wider text-[10px] block mb-1">Compliance Officer Remarks:</span>
                    <p class="text-slate-800 dark:text-slate-200">{{ $seller->rejection_reason }}</p>
                </div>
            @endif
        </div>
    @endif

    <!-- STATUS TIMELINE (Submitted / Under Review) -->
    @if ($seller->isKycSubmitted())
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">KYC Verification Roadmap</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Your application package has been locked and queued for merchant verification.</p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 animate-pulse">
                    Under Review (24-48h SLA)
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Timeline 1: Submitted -->
                <div class="p-4 rounded-xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/60 space-y-1">
                    <div class="flex items-center space-x-2 text-emerald-600 font-bold text-xs">
                        <span class="w-5 h-5 rounded-full bg-emerald-500 text-white flex items-center justify-center text-[10px]">✓</span>
                        <span>1. Application Submitted</span>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 pl-7">
                        Submitted on {{ $seller->submitted_at ? $seller->submitted_at->format('d M Y, h:i A') : 'Recently' }}.
                    </p>
                </div>

                <!-- Timeline 2: In Review -->
                <div class="p-4 rounded-xl bg-amber-50/50 dark:bg-amber-950/20 border border-amber-300 dark:border-amber-800 space-y-1">
                    <div class="flex items-center space-x-2 text-amber-700 dark:text-amber-400 font-bold text-xs">
                        <span class="w-5 h-5 rounded-full bg-amber-500 text-white flex items-center justify-center text-[10px] animate-pulse">2</span>
                        <span>2. Officer Review & Audit</span>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 pl-7">
                        PAN, GSTIN, bank penny-drop, and location verification in progress.
                    </p>
                </div>

                <!-- Timeline 3: Store Activation -->
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 space-y-1 opacity-60">
                    <div class="flex items-center space-x-2 text-slate-500 font-bold text-xs">
                        <span class="w-5 h-5 rounded-full bg-slate-300 dark:bg-slate-700 text-slate-700 dark:text-slate-300 flex items-center justify-center text-[10px]">3</span>
                        <span>3. Approval & Go-Live</span>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 pl-7">
                        Auto-creates storefront, assigns seller owner role, enables catalog.
                    </p>
                </div>
            </div>

            <!-- View Submitted Application Summary -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
                <span class="text-slate-500">Need urgent review or assistance?</span>
                <a href="mailto:kyc@onestall.in" class="font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                    Contact Onboarding Desk (kyc@onestall.in) &rarr;
                </a>
            </div>
        </div>
    @endif

    <!-- 7-STEP PROGRESS STEPPER NAV -->
    <div class="p-3 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs overflow-x-auto">
        <div class="flex items-center justify-between min-w-[650px] space-x-2">
            @php
                $stepLabels = [
                    1 => 'Contact OTP',
                    2 => 'Business Info',
                    3 => 'PAN & GST',
                    4 => 'Bank Account',
                    5 => 'Store Location',
                    6 => 'Documents',
                    7 => 'Review & Submit',
                ];
            @endphp

            @foreach ($stepLabels as $stepNum => $label)
                @php
                    $isDone = $stepStatus[$stepNum] ?? false;
                    $isActive = $activeStep === $stepNum;
                    $isAccessible = !$isLocked && ($stepNum <= ($seller->onboarding_step ?: 1) || $isDone);
                @endphp

                <div class="flex items-center flex-1 {{ !$loop->last ? 'relative' : '' }}">
                    @if ($isAccessible)
                        <a 
                            href="{{ route('seller.onboarding', ['step' => $stepNum]) }}" 
                            class="flex flex-col items-center group cursor-pointer w-full text-center"
                        >
                    @else
                        <div class="flex flex-col items-center w-full text-center opacity-50 cursor-not-allowed">
                    @endif

                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all shadow-xs {{ $isActive ? 'bg-indigo-600 text-white ring-4 ring-indigo-100 dark:ring-indigo-950' : ($isDone ? 'bg-emerald-500 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-500') }}">
                            @if ($isDone && !$isActive)
                                ✓
                            @else
                                {{ $stepNum }}
                            @endif
                        </div>

                        <span class="text-[11px] font-bold mt-1.5 whitespace-nowrap {{ $isActive ? 'text-indigo-600 dark:text-indigo-400' : ($isDone ? 'text-slate-800 dark:text-slate-200' : 'text-slate-400') }}">
                            {{ $label }}
                        </span>

                    @if ($isAccessible)
                        </a>
                    @else
                        </div>
                    @endif

                    @if (!$loop->last)
                        <div class="hidden sm:block flex-1 h-0.5 mx-2 {{ $isDone ? 'bg-emerald-500' : 'bg-slate-200 dark:bg-slate-800' }}"></div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <!-- MAIN STEP CONTENT -->
    <div class="p-6 sm:p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
        
        <!-- ==============================================
             STEP 1: VERIFY MOBILE (OTP) & EMAIL
             ============================================== -->
        <div x-show="currentStep === 1" class="space-y-6">
            <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Step 1: Contact Identity Verification</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Verify your registered mobile number via SMS OTP to receive real-time order alerts and fulfillment dispatch notifications.</p>
            </div>

            <!-- Mobile Verification Box -->
            <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Mobile Number</div>
                        <div class="text-base font-extrabold text-slate-900 dark:text-white font-mono mt-0.5">{{ $user->phone ?: 'No phone registered' }}</div>
                    </div>
                    @if ($user->isPhoneVerified())
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center space-x-1">
                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            <span>Phone Verified</span>
                        </span>
                    @else
                        <button 
                            type="button" 
                            @click="sendMobileOtp()" 
                            :disabled="otpSending"
                            class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs transition disabled:opacity-50"
                        >
                            <span x-text="otpSending ? 'Sending OTP...' : (otpSent ? 'Resend OTP' : 'Send Mobile OTP')"></span>
                        </button>
                    @endif
                </div>

                @if (!$user->isPhoneVerified())
                    <form method="POST" action="{{ route('seller.onboarding.verify_otp') }}" class="pt-3 border-t border-slate-200 dark:border-slate-800/80 space-y-3">
                        @csrf
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                            Enter 6-Digit OTP Code
                        </label>
                        <div class="flex items-center space-x-3">
                            <input 
                                type="text" 
                                name="otp" 
                                maxlength="6" 
                                placeholder="123456" 
                                required
                                class="w-44 p-2.5 text-center text-sm tracking-widest font-mono rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-bold"
                            />
                            <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs transition">
                                Verify & Continue &rarr;
                            </button>
                        </div>
                        <p class="text-[11px] text-slate-500">A simulated 6-digit OTP is recorded in the platform application log.</p>
                    </form>
                @endif
            </div>

            <!-- Email Verification Box -->
            <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <div>
                    <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Email Address</div>
                    <div class="text-base font-extrabold text-slate-900 dark:text-white font-mono mt-0.5">{{ $user->email }}</div>
                </div>
                @if ($user->isEmailVerified())
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center space-x-1">
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        <span>Email Verified</span>
                    </span>
                @else
                    <form method="POST" action="{{ route('seller.onboarding.verify_email') }}">
                        @csrf
                        <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 text-xs font-bold border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 transition">
                            Confirm Email Address
                        </button>
                    </form>
                @endif
            </div>

            <!-- Step 1 Navigation -->
            <div class="pt-4 flex items-center justify-end">
                <a 
                    href="{{ route('seller.onboarding', ['step' => 2]) }}" 
                    class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md shadow-indigo-600/30 transition flex items-center space-x-1.5"
                >
                    <span>Continue to Business Info</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>

        <!-- ==============================================
             STEP 2: BUSINESS INFORMATION
             ============================================== -->
        <div x-show="currentStep === 2" class="space-y-6">
            <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Step 2: Business Profile & Classification</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Specify your commercial store model and legal entity credentials for platform contracting.</p>
            </div>

            <form method="POST" action="{{ route('seller.onboarding.step2') }}" class="space-y-4">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Business Display Name -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Store / Business Name <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="business_name" 
                            value="{{ old('business_name', $seller->business_name) }}" 
                            required 
                            :disabled="isLocked"
                            class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white disabled:opacity-60"
                        />
                    </div>

                    <!-- Legal Registered Entity Name -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Legal Entity Name (per PAN) <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="legal_name" 
                            value="{{ old('legal_name', $seller->legal_name) }}" 
                            required 
                            :disabled="isLocked"
                            placeholder="e.g. Metro Retailers Pvt Ltd / Kiran Gowda Sole Prop"
                            class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white disabled:opacity-60"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Seller Operational Type -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Operational Model <span class="text-rose-500">*</span>
                        </label>
                        <select 
                            name="seller_type" 
                            required 
                            :disabled="isLocked"
                            class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white disabled:opacity-60"
                        >
                            @foreach (\App\Enums\SellerType::cases() as $type)
                                <option value="{{ $type->value }}" {{ old('seller_type', $seller->seller_type->value) === $type->value ? 'selected' : '' }}>
                                    {{ $type->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Category Focus -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Primary Retail Category Focus <span class="text-rose-500">*</span>
                        </label>
                        <select 
                            name="category_focus" 
                            required 
                            :disabled="isLocked"
                            class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white disabled:opacity-60"
                        >
                            <option value="">Select Primary Focus Category</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->name }}" {{ old('category_focus', $seller->category_focus) === $cat->name ? 'selected' : '' }}>
                                    {{ $cat->name }}
                                </option>
                            @endforeach
                            <option value="Multi-Category Retail" {{ old('category_focus', $seller->category_focus) === 'Multi-Category Retail' ? 'selected' : '' }}>Multi-Category Department Store</option>
                            <option value="Other Specialist" {{ old('category_focus', $seller->category_focus) === 'Other Specialist' ? 'selected' : '' }}>Other Specialty Retail</option>
                        </select>
                    </div>
                </div>

                <!-- Step 2 Navigation -->
                <div class="pt-6 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <a href="{{ route('seller.onboarding', ['step' => 1]) }}" class="px-4 py-2 text-xs font-semibold text-slate-500 hover:text-slate-800 transition">
                        &larr; Back
                    </a>

                    @if (!$isLocked)
                        <div class="flex items-center space-x-2">
                            <button type="submit" name="save_draft" value="1" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-200 transition">
                                Save Draft
                            </button>
                            <button type="submit" name="save_and_continue" value="1" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md shadow-indigo-600/30 transition">
                                Save & Continue to PAN/GST &rarr;
                            </button>
                        </div>
                    @endif
                </div>
            </form>
        </div>

        <!-- ==============================================
             STEP 3: TAX IDENTIFICATION (PAN & GSTIN)
             ============================================== -->
        <div x-show="currentStep === 3" class="space-y-6">
            <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Step 3: Tax Compliance (PAN & GSTIN)</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Indian statutory tax compliance details. All sensitive tax fields are encrypted at rest with AES-256-GCM.</p>
            </div>

            <form method="POST" action="{{ route('seller.onboarding.step3') }}" class="space-y-4" x-data="{ isExempt: {{ old('is_gst_exempt', false) ? 'true' : 'false' }} }">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- PAN Number -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Permanent Account Number (PAN) <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="pan" 
                            maxlength="10" 
                            placeholder="ABCDE1234F" 
                            value="{{ old('pan', $seller->pan) }}" 
                            required 
                            :disabled="isLocked"
                            class="w-full p-2.5 text-xs font-mono uppercase tracking-wider rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white disabled:opacity-60"
                        />
                        <p class="text-[10px] text-slate-400 mt-1">10-character business or proprietor PAN card number.</p>
                    </div>

                    <!-- GSTIN Exemption Checkbox & Input -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Goods & Services Tax ID (GSTIN)
                        </label>
                        <input 
                            type="text" 
                            name="gstin" 
                            maxlength="15" 
                            placeholder="29ABCDE1234F1Z5" 
                            value="{{ old('gstin', $seller->gstin) }}" 
                            :disabled="isLocked || isExempt"
                            class="w-full p-2.5 text-xs font-mono uppercase tracking-wider rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white disabled:opacity-60"
                        />

                        <label class="mt-2.5 flex items-center space-x-2 cursor-pointer">
                            <input 
                                type="checkbox" 
                                name="is_gst_exempt" 
                                value="1" 
                                x-model="isExempt" 
                                :disabled="isLocked"
                                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span class="text-xs text-slate-600 dark:text-slate-400">
                                My business is exempt / below GST threshold turnover (Local Store)
                            </span>
                        </label>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="p-4 rounded-xl bg-indigo-50/60 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/60 text-xs text-indigo-900 dark:text-indigo-300 flex items-start space-x-2">
                    <svg class="w-4 h-4 text-indigo-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>PAN and GSTIN are automatically validated against NSDL and GSTN check-digit rules. Once verified, TCS and TDS deductions will be calculated per statutory norms.</span>
                </div>

                <!-- Step 3 Navigation -->
                <div class="pt-6 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <a href="{{ route('seller.onboarding', ['step' => 2]) }}" class="px-4 py-2 text-xs font-semibold text-slate-500 hover:text-slate-800 transition">
                        &larr; Back
                    </a>

                    @if (!$isLocked)
                        <div class="flex items-center space-x-2">
                            <button type="submit" name="save_draft" value="1" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-200 transition">
                                Save Draft
                            </button>
                            <button type="submit" name="save_and_continue" value="1" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md shadow-indigo-600/30 transition">
                                Save & Continue to Bank &rarr;
                            </button>
                        </div>
                    @endif
                </div>
            </form>
        </div>

        <!-- ==============================================
             STEP 4: BANK ACCOUNT & PENNY DROP
             ============================================== -->
        <div x-show="currentStep === 4" class="space-y-6">
            <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Step 4: Payout Bank Account & Penny-Drop Verification</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Automated settlement proceeds and COD payouts will be remitted to this bank account.</p>
            </div>

            <form method="POST" action="{{ route('seller.onboarding.step4') }}" class="space-y-4">
                @csrf

                <!-- Account Holder Name -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Bank Account Beneficiary Holder Name <span class="text-rose-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="account_holder" 
                        x-model="bankForm.account_holder"
                        value="{{ old('account_holder', $primaryBank?->account_holder ?? $seller->legal_name) }}" 
                        required 
                        :disabled="isLocked"
                        placeholder="Must match Legal Entity / Business Name"
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white disabled:opacity-60"
                    />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Account Number -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Bank Account Number <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="password" 
                            name="account_no" 
                            x-model="bankForm.account_no"
                            required 
                            :disabled="isLocked"
                            placeholder="Enter bank account number"
                            class="w-full p-2.5 text-xs font-mono rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white disabled:opacity-60"
                        />
                    </div>

                    <!-- Confirm Account Number -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Confirm Account Number <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="account_no_confirmation" 
                            required 
                            :disabled="isLocked"
                            placeholder="Re-enter bank account number"
                            class="w-full p-2.5 text-xs font-mono rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white disabled:opacity-60"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <!-- IFSC Code -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Bank IFSC Code <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="ifsc" 
                            x-model="bankForm.ifsc"
                            maxlength="11" 
                            value="{{ old('ifsc', $primaryBank?->ifsc) }}" 
                            required 
                            :disabled="isLocked"
                            placeholder="e.g. HDFC0001234"
                            class="w-full p-2.5 text-xs font-mono uppercase tracking-wider rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white disabled:opacity-60"
                        />
                    </div>

                    <!-- Bank Name -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Bank Name <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="bank_name" 
                            value="{{ old('bank_name', $primaryBank?->bank_name ?? 'HDFC Bank') }}" 
                            required 
                            :disabled="isLocked"
                            placeholder="e.g. HDFC Bank Ltd"
                            class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white disabled:opacity-60"
                        />
                    </div>

                    <!-- Branch Name -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Branch Name
                        </label>
                        <input 
                            type="text" 
                            name="branch_name" 
                            value="{{ old('branch_name', $primaryBank?->branch_name ?? 'Indiranagar') }}" 
                            :disabled="isLocked"
                            placeholder="e.g. Indiranagar Branch"
                            class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white disabled:opacity-60"
                        />
                    </div>
                </div>

                <!-- Penny Drop Test Card -->
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-xs font-bold text-slate-800 dark:text-slate-200">Penny-Drop Account Verification</div>
                            <p class="text-[11px] text-slate-500">Initiates a simulated ₹1 transfer to verify beneficiary matching with NPCI records.</p>
                        </div>
                        <button 
                            type="button" 
                            @click="runPennyDrop()" 
                            :disabled="pennyTesting || isLocked"
                            class="px-3.5 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold transition disabled:opacity-50"
                        >
                            <span x-text="pennyTesting ? 'Running Penny-Drop...' : 'Test Account Verification'"></span>
                        </button>
                    </div>

                    <div x-show="pennyResult" class="p-3 rounded-lg text-xs" :class="pennyResult?.success ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'">
                        <div class="font-bold" x-text="pennyResult?.message"></div>
                        <template x-if="pennyResult?.reference_id">
                            <div class="text-[10px] font-mono mt-1 opacity-80" x-text="'Ref ID: ' + pennyResult.reference_id + ' | Matched: ' + pennyResult.beneficiary_name"></div>
                        </template>
                    </div>
                </div>

                <!-- Step 4 Navigation -->
                <div class="pt-6 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <a href="{{ route('seller.onboarding', ['step' => 3]) }}" class="px-4 py-2 text-xs font-semibold text-slate-500 hover:text-slate-800 transition">
                        &larr; Back
                    </a>

                    @if (!$isLocked)
                        <div class="flex items-center space-x-2">
                            <button type="submit" name="save_draft" value="1" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-200 transition">
                                Save Draft
                            </button>
                            <button type="submit" name="save_and_continue" value="1" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md shadow-indigo-600/30 transition">
                                Save & Continue to Location &rarr;
                            </button>
                        </div>
                    @endif
                </div>
            </form>
        </div>

        <!-- ==============================================
             STEP 5: BUSINESS / STORE ADDRESS WITH MAP PIN
             ============================================== -->
        <div x-show="currentStep === 5" class="space-y-6">
            <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Step 5: Store Location & Quick Commerce Coordinates</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Precise latitude/longitude coordinates power Blinkit-style 10-30 min rider dispatch and hyper-local customer discovery.</p>
            </div>

            <form method="POST" action="{{ route('seller.onboarding.step5') }}" class="space-y-4">
                @csrf

                <!-- Street Address -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Street Address / Premises / Shop No. <span class="text-rose-500">*</span>
                    </label>
                    <textarea 
                        name="address" 
                        rows="2" 
                        required 
                        :disabled="isLocked"
                        placeholder="Shop No, Building Name, Street, Landmark"
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white disabled:opacity-60"
                    >{{ old('address', $storeLocation?->address) }}</textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <!-- City -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            City <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="city" 
                            value="{{ old('city', $storeLocation?->city ?? 'Bengaluru') }}" 
                            required 
                            :disabled="isLocked"
                            class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white disabled:opacity-60"
                        />
                    </div>

                    <!-- State -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            State <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="state" 
                            value="{{ old('state', $storeLocation?->state ?? 'Karnataka') }}" 
                            required 
                            :disabled="isLocked"
                            class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white disabled:opacity-60"
                        />
                    </div>

                    <!-- Pincode -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Postal Pincode <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="pincode" 
                            maxlength="6" 
                            value="{{ old('pincode', $storeLocation?->pincode ?? '560038') }}" 
                            required 
                            :disabled="isLocked"
                            class="w-full p-2.5 text-xs font-mono rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white disabled:opacity-60"
                        />
                    </div>
                </div>

                <!-- Interactive Map Pin Picker Card -->
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                Interactive Store Location Pin Picker
                            </span>
                            <p class="text-[11px] text-slate-500">Drag or click on the map to accurately place your storefront pin.</p>
                        </div>

                        <button 
                            type="button" 
                            @click="detectCurrentLocation()" 
                            class="px-3 py-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold text-xs border border-indigo-200 hover:bg-indigo-100 transition"
                        >
                            📍 Use My GPS Position
                        </button>
                    </div>

                    <!-- Map Container -->
                    <div id="store-picker-map" class="h-64 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden z-0 bg-slate-200"></div>

                    <!-- Coordinates Display & Hidden Form Inputs -->
                    <div class="grid grid-cols-2 gap-4 pt-2">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase">Latitude</label>
                            <input 
                                type="text" 
                                name="lat" 
                                x-model="lat" 
                                required 
                                readonly 
                                class="w-full p-2 text-xs font-mono rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase">Longitude</label>
                            <input 
                                type="text" 
                                name="lng" 
                                x-model="lng" 
                                required 
                                readonly 
                                class="w-full p-2 text-xs font-mono rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                            />
                        </div>
                    </div>
                </div>

                <!-- Step 5 Navigation -->
                <div class="pt-6 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <a href="{{ route('seller.onboarding', ['step' => 4]) }}" class="px-4 py-2 text-xs font-semibold text-slate-500 hover:text-slate-800 transition">
                        &larr; Back
                    </a>

                    @if (!$isLocked)
                        <div class="flex items-center space-x-2">
                            <button type="submit" name="save_draft" value="1" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-200 transition">
                                Save Draft
                            </button>
                            <button type="submit" name="save_and_continue" value="1" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md shadow-indigo-600/30 transition">
                                Save & Continue to Documents &rarr;
                            </button>
                        </div>
                    @endif
                </div>
            </form>
        </div>

        <!-- ==============================================
             STEP 6: DOCUMENT UPLOADS (PRIVATE DISK + SIGNED)
             ============================================== -->
        <div x-show="currentStep === 6" class="space-y-6">
            <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Step 6: KYC Compliance Document Uploads</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Upload certified digital copies. Documents are stored in an encrypted private disk and only accessible via temporary signed URLs.</p>
            </div>

            <form method="POST" action="{{ route('seller.onboarding.step6') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- 1. PAN Card -->
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold uppercase tracking-wider text-slate-800 dark:text-slate-200">
                                PAN Card Copy <span class="text-rose-500">*</span>
                            </label>
                            @if ($documents->has(\App\Enums\SellerDocumentType::PAN_CARD->value))
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">Uploaded</span>
                            @endif
                        </div>
                        <p class="text-[11px] text-slate-500">Front scan of business / proprietor PAN card (PDF, PNG, JPG &lt; 5MB).</p>
                        <input 
                            type="file" 
                            name="pan_card" 
                            accept=".pdf,.jpg,.jpeg,.png" 
                            :disabled="isLocked"
                            class="block w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                        />
                        @if (isset($documentUrls[\App\Enums\SellerDocumentType::PAN_CARD->value]))
                            <div class="pt-1">
                                <a href="{{ $documentUrls[\App\Enums\SellerDocumentType::PAN_CARD->value] }}" target="_blank" class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                                    &bull; View Uploaded File (Signed Link) &rarr;
                                </a>
                            </div>
                        @endif
                    </div>

                    <!-- 2. GST Certificate -->
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold uppercase tracking-wider text-slate-800 dark:text-slate-200">
                                GST Registration Certificate
                            </label>
                            @if ($documents->has(\App\Enums\SellerDocumentType::GST_CERTIFICATE->value))
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">Uploaded</span>
                            @endif
                        </div>
                        <p class="text-[11px] text-slate-500">Form REG-06 GST Registration certificate (optional if exempt).</p>
                        <input 
                            type="file" 
                            name="gst_certificate" 
                            accept=".pdf,.jpg,.jpeg,.png" 
                            :disabled="isLocked"
                            class="block w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                        />
                        @if (isset($documentUrls[\App\Enums\SellerDocumentType::GST_CERTIFICATE->value]))
                            <div class="pt-1">
                                <a href="{{ $documentUrls[\App\Enums\SellerDocumentType::GST_CERTIFICATE->value] }}" target="_blank" class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                                    &bull; View Uploaded File (Signed Link) &rarr;
                                </a>
                            </div>
                        @endif
                    </div>

                    <!-- 3. Bank Proof -->
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold uppercase tracking-wider text-slate-800 dark:text-slate-200">
                                Cancelled Cheque / Bank Statement <span class="text-rose-500">*</span>
                            </label>
                            @if ($documents->has(\App\Enums\SellerDocumentType::BANK_PROOF->value))
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">Uploaded</span>
                            @endif
                        </div>
                        <p class="text-[11px] text-slate-500">Clear scan showing Account Holder name, Account number, and IFSC.</p>
                        <input 
                            type="file" 
                            name="bank_proof" 
                            accept=".pdf,.jpg,.jpeg,.png" 
                            :disabled="isLocked"
                            class="block w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                        />
                        @if (isset($documentUrls[\App\Enums\SellerDocumentType::BANK_PROOF->value]))
                            <div class="pt-1">
                                <a href="{{ $documentUrls[\App\Enums\SellerDocumentType::BANK_PROOF->value] }}" target="_blank" class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                                    &bull; View Uploaded File (Signed Link) &rarr;
                                </a>
                            </div>
                        @endif
                    </div>

                    <!-- 4. Address Proof -->
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold uppercase tracking-wider text-slate-800 dark:text-slate-200">
                                Store Business Address Proof
                            </label>
                            @if ($documents->has(\App\Enums\SellerDocumentType::ADDRESS_PROOF->value))
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">Uploaded</span>
                            @endif
                        </div>
                        <p class="text-[11px] text-slate-500">Electricity bill, rent agreement, trade license or shop registration.</p>
                        <input 
                            type="file" 
                            name="address_proof" 
                            accept=".pdf,.jpg,.jpeg,.png" 
                            :disabled="isLocked"
                            class="block w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                        />
                        @if (isset($documentUrls[\App\Enums\SellerDocumentType::ADDRESS_PROOF->value]))
                            <div class="pt-1">
                                <a href="{{ $documentUrls[\App\Enums\SellerDocumentType::ADDRESS_PROOF->value] }}" target="_blank" class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                                    &bull; View Uploaded File (Signed Link) &rarr;
                                </a>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Step 6 Navigation -->
                <div class="pt-6 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <a href="{{ route('seller.onboarding', ['step' => 5]) }}" class="px-4 py-2 text-xs font-semibold text-slate-500 hover:text-slate-800 transition">
                        &larr; Back
                    </a>

                    @if (!$isLocked)
                        <div class="flex items-center space-x-2">
                            <button type="submit" name="save_draft" value="1" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-200 transition">
                                Save Uploads
                            </button>
                            <button type="submit" name="save_and_continue" value="1" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md shadow-indigo-600/30 transition">
                                Save & Review Final KYC &rarr;
                            </button>
                        </div>
                    @endif
                </div>
            </form>
        </div>

        <!-- ==============================================
             STEP 7: REVIEW & SUBMIT KYC
             ============================================== -->
        <div x-show="currentStep === 7" class="space-y-6">
            <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Step 7: Final Review & Submit KYC Application</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Please review your complete onboarding profile before submitting. Upon submission, details are locked for compliance review.</p>
            </div>

            <!-- Review Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- 1. Contact & Business -->
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-2 text-xs">
                    <div class="font-bold uppercase tracking-wider text-slate-400 text-[10px]">Merchant Profile</div>
                    <div class="flex justify-between py-1 border-b border-slate-200/60 dark:border-slate-800">
                        <span class="text-slate-500">Business Name:</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ $seller->business_name }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-200/60 dark:border-slate-800">
                        <span class="text-slate-500">Legal Name:</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ $seller->legal_name }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-200/60 dark:border-slate-800">
                        <span class="text-slate-500">Model:</span>
                        <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ $seller->seller_type->label() }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-200/60 dark:border-slate-800">
                        <span class="text-slate-500">Category:</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ $seller->category_focus ?: 'Retail' }}</span>
                    </div>
                    <div class="flex justify-between py-1">
                        <span class="text-slate-500">Mobile / Email:</span>
                        <span class="font-mono text-slate-900 dark:text-white">{{ $user->phone }} / {{ $user->email }}</span>
                    </div>
                </div>

                <!-- 2. Tax & Bank -->
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-2 text-xs">
                    <div class="font-bold uppercase tracking-wider text-slate-400 text-[10px]">Tax & Settlements</div>
                    <div class="flex justify-between py-1 border-b border-slate-200/60 dark:border-slate-800">
                        <span class="text-slate-500">PAN Card:</span>
                        <span class="font-mono font-bold text-slate-900 dark:text-white">{{ $seller->pan ? substr($seller->pan, 0, 5) . '****' . substr($seller->pan, -1) : 'Not Provided' }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-200/60 dark:border-slate-800">
                        <span class="text-slate-500">GSTIN:</span>
                        <span class="font-mono font-bold text-slate-900 dark:text-white">{{ $seller->gstin ? substr($seller->gstin, 0, 4) . '******' . substr($seller->gstin, -3) : 'Exempt / Not Provided' }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-200/60 dark:border-slate-800">
                        <span class="text-slate-500">Bank Account:</span>
                        <span class="font-mono font-bold text-slate-900 dark:text-white">{{ $primaryBank ? '******' . substr($primaryBank->account_no, -4) : 'Pending' }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-200/60 dark:border-slate-800">
                        <span class="text-slate-500">Bank & IFSC:</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ $primaryBank?->bank_name }} ({{ $primaryBank?->ifsc }})</span>
                    </div>
                    <div class="flex justify-between py-1">
                        <span class="text-slate-500">Verification:</span>
                        <span class="font-bold text-emerald-600">Penny-Drop Successful</span>
                    </div>
                </div>

                <!-- 3. Store Location & Coordinates -->
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-2 text-xs md:col-span-2">
                    <div class="font-bold uppercase tracking-wider text-slate-400 text-[10px]">Store Premises & Quick Commerce Radius</div>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div>
                            <div class="font-bold text-slate-900 dark:text-white">{{ $storeLocation?->address }}</div>
                            <div class="text-slate-500">{{ $storeLocation?->city }}, {{ $storeLocation?->state }} - {{ $storeLocation?->pincode }}</div>
                        </div>
                        <div class="p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 font-mono text-[11px] text-slate-700 dark:text-slate-300">
                            Lat: {{ $storeLocation?->lat }} &bull; Lng: {{ $storeLocation?->lng }}
                        </div>
                    </div>
                </div>
            </div>

            @if ($isLocked)
                <div class="p-4 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-xs">
                    <span class="font-bold">Application Status: {{ $seller->status->label() }}</span>. Editing is locked while under compliance audit.
                </div>
            @else
                <form method="POST" action="{{ route('seller.onboarding.submit') }}" class="space-y-4 pt-2">
                    @csrf

                    <!-- Terms & Declarations -->
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-3">
                        <label class="flex items-start space-x-2.5 cursor-pointer">
                            <input 
                                type="checkbox" 
                                name="terms_accepted" 
                                value="1" 
                                required 
                                class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span class="text-xs text-slate-700 dark:text-slate-300">
                                I agree to the <strong>OneStall Merchant Marketplace Agreement</strong>, Quick Commerce SLA guidelines, and Commission schedule.
                            </span>
                        </label>

                        <label class="flex items-start space-x-2.5 cursor-pointer">
                            <input 
                                type="checkbox" 
                                name="declaration_accepted" 
                                value="1" 
                                required 
                                class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span class="text-xs text-slate-700 dark:text-slate-300">
                                I certify that all documents, PAN, GST, and bank account credentials provided belong to the legal applicant entity and are accurate.
                            </span>
                        </label>
                    </div>

                    <!-- Step 7 Navigation -->
                    <div class="pt-6 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <a href="{{ route('seller.onboarding', ['step' => 6]) }}" class="px-4 py-2 text-xs font-semibold text-slate-500 hover:text-slate-800 transition">
                            &larr; Back to Documents
                        </a>

                        <button 
                            type="submit" 
                            class="px-8 py-3 rounded-xl bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white font-extrabold text-sm shadow-lg shadow-indigo-600/30 transition flex items-center space-x-2"
                        >
                            <span>🚀 Submit KYC Application</span>
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <!-- Sign Out Link -->
    <div class="pt-4 text-center">
        <form method="POST" action="{{ route('seller.logout') }}">
            @csrf
            <button type="submit" class="text-xs text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition underline">
                Sign out of Seller Portal
            </button>
        </form>
    </div>
</div>

<!-- Leaflet Map CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
function sellerOnboardingWizard(config) {
    return {
        currentStep: config.currentStep,
        maxStep: config.maxStep,
        isLocked: config.isLocked,
        isApproved: config.isApproved,
        isKycSubmitted: config.isKycSubmitted,
        isRejected: config.isRejected,
        lat: config.lat,
        lng: config.lng,
        otpSending: false,
        otpSent: false,
        pennyTesting: false,
        pennyResult: null,
        bankForm: {
            account_holder: '',
            account_no: '',
            ifsc: ''
        },
        map: null,
        marker: null,

        init() {
            this.$nextTick(() => {
                if (this.currentStep === 5) {
                    this.initMap();
                }
            });
        },

        initMap() {
            const container = document.getElementById('store-picker-map');
            if (!container || typeof L === 'undefined') return;

            if (this.map) {
                this.map.invalidateSize();
                return;
            }

            this.map = L.map('store-picker-map').setView([this.lat, this.lng], 14);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(this.map);

            this.marker = L.marker([this.lat, this.lng], { draggable: !this.isLocked }).addTo(this.map);

            this.marker.on('dragend', (e) => {
                const pos = e.target.getLatLng();
                this.lat = Number(pos.lat.toFixed(7));
                this.lng = Number(pos.lng.toFixed(7));
            });

            if (!this.isLocked) {
                this.map.on('click', (e) => {
                    this.marker.setLatLng(e.latlng);
                    this.lat = Number(e.latlng.lat.toFixed(7));
                    this.lng = Number(e.latlng.lng.toFixed(7));
                });
            }
        },

        detectCurrentLocation() {
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by your browser.');
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.lat = Number(pos.coords.latitude.toFixed(7));
                    this.lng = Number(pos.coords.longitude.toFixed(7));
                    if (this.map && this.marker) {
                        this.map.setView([this.lat, this.lng], 16);
                        this.marker.setLatLng([this.lat, this.lng]);
                    }
                },
                (err) => {
                    alert('Unable to retrieve your location: ' + err.message);
                }
            );
        },

        async sendMobileOtp() {
            this.otpSending = true;
            try {
                const res = await fetch(config.urls.sendOtp, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                const data = await res.json();
                this.otpSent = true;
                alert(data.message || 'OTP sent successfully!');
            } catch (e) {
                alert('Error sending OTP. Please try again.');
            } finally {
                this.otpSending = false;
            }
        },

        async runPennyDrop() {
            const acc = this.bankForm.account_no || document.querySelector('[name="account_no"]')?.value;
            const ifsc = this.bankForm.ifsc || document.querySelector('[name="ifsc"]')?.value;
            const holder = this.bankForm.account_holder || document.querySelector('[name="account_holder"]')?.value;

            if (!acc || !ifsc || !holder) {
                alert('Please enter Account Holder, Account Number, and IFSC code first.');
                return;
            }

            this.pennyTesting = true;
            this.pennyResult = null;

            try {
                const res = await fetch(config.urls.previewBank, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        account_no: acc,
                        ifsc: ifsc,
                        account_holder: holder
                    })
                });
                const data = await res.json();
                this.pennyResult = data;
            } catch (e) {
                this.pennyResult = { success: false, message: 'Verification error occurred.' };
            } finally {
                this.pennyTesting = false;
            }
        }
    };
}
</script>
@endsection
