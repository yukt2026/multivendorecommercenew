@extends('layouts.rider', ['title' => 'Rider Support & Safety - OneStall'])

@section('content')
<div class="space-y-4">

    <!-- Header -->
    <div class="space-y-1">
        <h1 class="text-base font-black text-slate-900">Rider Support &amp; Safety</h1>
        <p class="text-xs text-slate-500">24/7 Helpline, Dark Store Hub contact, and delivery guidelines.</p>
    </div>

    <!-- Emergency SOS Hero Card -->
    <div class="bg-rose-600 text-white rounded-3xl p-5 shadow-lg space-y-3">
        <div class="flex items-center justify-between">
            <span class="text-xs font-black uppercase tracking-wider text-rose-200">Emergency &amp; Accident SOS</span>
            <span class="text-2xl">🚨</span>
        </div>
        <div>
            <h2 class="text-lg font-black leading-tight">Emergency Roadside Helpline</h2>
            <p class="text-xs text-rose-100 mt-1">
                For accidents, medical emergencies, or safety incidents during active trips.
            </p>
        </div>
        <a 
            href="tel:+918001234567" 
            class="block w-full py-3 bg-white text-rose-700 rounded-2xl font-black text-xs text-center shadow-md hover:bg-rose-50 transition"
        >
            📞 Call Emergency Hotline (+91 800 123 4567)
        </a>
    </div>

    <!-- Dark Store Hub Manager Contact -->
    <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-xs space-y-3">
        <div class="flex items-center space-x-2 text-xs font-bold text-slate-800 uppercase tracking-wider">
            <span>🏢</span>
            <span>Local Dark Store Operations Hub</span>
        </div>
        <p class="text-xs text-slate-500 leading-relaxed">
            Contact your dispatch hub manager for parcel packaging queries, COD remittance, or vehicle issues.
        </p>
        <div class="grid grid-cols-2 gap-2 text-xs">
            <a href="tel:+919876543200" class="py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-xl font-bold text-center transition">
                📞 Call Hub Manager
            </a>
            <a href="mailto:rider-support@onestall.com" class="py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-xl font-bold text-center transition">
                ✉️ Email Support
            </a>
        </div>
    </div>

    <!-- Operating Guidelines & FAQ -->
    <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-xs space-y-3">
        <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">
            Delivery Partner Guidelines
        </h3>

        <div class="space-y-2.5 text-xs">
            <div class="p-3 rounded-2xl bg-slate-50 border border-slate-100 space-y-1">
                <h4 class="font-bold text-slate-900">What if customer doesn't answer the phone?</h4>
                <p class="text-[11px] text-slate-500 leading-relaxed">
                    Ring doorbell and attempt at least 2 phone calls. Wait at least 5 minutes before reporting the delivery as failed via the app.
                </p>
            </div>

            <div class="p-3 rounded-2xl bg-slate-50 border border-slate-100 space-y-1">
                <h4 class="font-bold text-slate-900">How do I remit Cash on Delivery (COD)?</h4>
                <p class="text-[11px] text-slate-500 leading-relaxed">
                    All cash collected from COD packages must be handed over to your hub cashier at the end of your shift or whenever your cash-in-hand approaches ₹5,000.
                </p>
            </div>

            <div class="p-3 rounded-2xl bg-slate-50 border border-slate-100 space-y-1">
                <h4 class="font-bold text-slate-900">How is my trip payout calculated?</h4>
                <p class="text-[11px] text-slate-500 leading-relaxed">
                    Each trip earns a base pickup fee of ₹35 + ₹10 per km beyond 3 km + 100% of customer tips, credited immediately to your wallet upon OTP verification.
                </p>
            </div>
        </div>
    </div>

</div>
@endsection
