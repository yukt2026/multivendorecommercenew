@extends('layouts.rider')

@section('content')
<div class="space-y-4" x-data="{ uploadModal: false, activeType: '', activeLabel: '', docNumber: '' }">

    <!-- Header Card -->
    <div class="bg-slate-900 text-white rounded-3xl p-5 shadow-lg relative overflow-hidden">
        <div class="absolute -right-6 -bottom-6 w-28 h-28 bg-orange-500/10 rounded-full blur-2xl"></div>
        <div class="relative z-10 space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-orange-400 bg-orange-500/10 px-2.5 py-1 rounded-full border border-orange-500/20">
                    KYC &amp; Compliance Portal
                </span>
                <span class="text-xs font-mono font-bold text-slate-400">
                    Partner ID #{{ $rider->id }}
                </span>
            </div>

            <h1 class="text-lg font-black text-white">Document Verification</h1>
            <p class="text-xs text-slate-300">
                Upload your valid driving license, vehicle documents and ID proof to get activated for order assignments.
            </p>
        </div>
    </div>

    <!-- Overall KYC Status Alert -->
    @if($rider->approval_status === 'approved')
        <div class="bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-2xl p-4 flex items-start space-x-3">
            <div class="w-8 h-8 rounded-xl bg-emerald-500 text-white flex items-center justify-center font-bold text-base shrink-0">
                ✓
            </div>
            <div class="text-xs">
                <div class="font-extrabold text-emerald-900 dark:text-emerald-300">Account Approved &amp; Operational</div>
                <div class="text-emerald-700 dark:text-emerald-400 mt-0.5">
                    Your KYC verification is complete. You can toggle duty online in the header and receive orders.
                </div>
            </div>
        </div>
    @elseif($rider->approval_status === 'rejected')
        <div class="bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-2xl p-4 flex items-start space-x-3">
            <div class="w-8 h-8 rounded-xl bg-rose-500 text-white flex items-center justify-center font-bold text-base shrink-0">
                ✕
            </div>
            <div class="text-xs">
                <div class="font-extrabold text-rose-900 dark:text-rose-300">KYC Verification Rejected</div>
                <div class="text-rose-700 dark:text-rose-400 mt-0.5">
                    {{ $rider->rejection_reason ?: 'One or more submitted documents did not meet requirements. Please check remarks below and re-upload.' }}
                </div>
            </div>
        </div>
    @elseif($rider->approval_status === 'under_review')
        <div class="bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-2xl p-4 flex items-start space-x-3">
            <div class="w-8 h-8 rounded-xl bg-amber-500 text-white flex items-center justify-center font-bold text-base shrink-0">
                ⏳
            </div>
            <div class="text-xs">
                <div class="font-extrabold text-amber-900 dark:text-amber-300">Documents Under Review</div>
                <div class="text-amber-700 dark:text-amber-400 mt-0.5">
                    Your documents have been submitted to our operations team. Verification usually takes 2-4 business hours.
                </div>
            </div>
        </div>
    @else
        <div class="bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 rounded-2xl p-4 flex items-start space-x-3">
            <div class="w-8 h-8 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-base shrink-0">
                📄
            </div>
            <div class="text-xs">
                <div class="font-extrabold text-indigo-900 dark:text-indigo-300">Action Required: Upload Mandatory KYC</div>
                <div class="text-indigo-700 dark:text-indigo-400 mt-0.5">
                    Upload your Driving License, Vehicle RC, and Aadhaar/Govt ID below to initiate admin review.
                </div>
            </div>
        </div>
    @endif

    <!-- Document Slots List -->
    <div class="space-y-3">
        <div class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 px-1">
            Required &amp; Supporting Documents
        </div>

        @foreach($documentTypes as $typeKey => $typeLabel)
            @php
                $doc = $uploadedDocs->get($typeKey);
                $isMandatory = in_array($typeKey, [
                    \App\Models\DeliveryDocument::TYPE_DRIVING_LICENSE,
                    \App\Models\DeliveryDocument::TYPE_VEHICLE_RC,
                    \App\Models\DeliveryDocument::TYPE_AADHAAR,
                ]);
            @endphp

            <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-3">
                <div class="flex items-start justify-between">
                    <div class="space-y-1">
                        <div class="flex items-center space-x-2">
                            <span class="text-sm font-bold text-slate-900 dark:text-white">{{ $typeLabel }}</span>
                            @if($isMandatory)
                                <span class="text-[9px] font-black uppercase px-2 py-0.5 bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-400 rounded-md">
                                    Mandatory
                                </span>
                            @else
                                <span class="text-[9px] font-black uppercase px-2 py-0.5 bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 rounded-md">
                                    Optional
                                </span>
                            @endif
                        </div>

                        @if($doc)
                            <div class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">
                                @if($doc->document_number)
                                    Doc #: {{ $doc->document_number }} &bull;
                                @endif
                                Uploaded {{ $doc->updated_at->diffForHumans() }}
                            </div>
                        @else
                            <div class="text-[11px] text-slate-400">
                                Not uploaded yet
                            </div>
                        @endif
                    </div>

                    <!-- Status Pill -->
                    <div>
                        @if(!$doc)
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                Missing
                            </span>
                        @elseif($doc->isApproved())
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                ✓ Verified
                            </span>
                        @elseif($doc->isRejected())
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-400 border border-rose-200 dark:border-rose-800">
                                ✕ Rejected
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                                ⏳ Pending Review
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Rejection Remarks if any -->
                @if($doc && $doc->isRejected() && $doc->rejection_reason)
                    <div class="bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 rounded-xl p-2.5 text-xs text-rose-700 dark:text-rose-300">
                        <span class="font-bold">Rejection Reason:</span> {{ $doc->rejection_reason }}
                    </div>
                @endif

                <!-- Actions -->
                <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <div>
                        @if($doc)
                            <a 
                                href="{{ route('rider.kyc.view', $doc) }}" 
                                target="_blank"
                                class="inline-flex items-center text-xs font-bold text-orange-600 dark:text-orange-400 hover:underline space-x-1"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <span>Preview Document</span>
                            </a>
                        @endif
                    </div>

                    <div class="flex items-center space-x-2">
                        @if($doc && !$doc->isApproved())
                            <form method="POST" action="{{ route('rider.kyc.delete', $doc) }}" onsubmit="return confirm('Delete this uploaded document?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-2.5 py-1 text-xs font-bold text-rose-500 hover:text-rose-700 dark:hover:text-rose-400">
                                    Delete
                                </button>
                            </form>
                        @endif

                        @if(!$doc || !$doc->isApproved())
                            <button 
                                type="button" 
                                @click="uploadModal = true; activeType = '{{ $typeKey }}'; activeLabel = '{{ $typeLabel }}'; docNumber = '{{ $doc?->document_number ?? '' }}'"
                                class="px-3.5 py-1.5 bg-slate-900 hover:bg-slate-800 text-white dark:bg-orange-600 dark:hover:bg-orange-500 text-xs font-bold rounded-xl transition cursor-pointer"
                            >
                                {{ $doc ? 'Replace Document' : '+ Upload Document' }}
                            </button>
                        @else
                            <span class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400">
                                Locked (Verified)
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Upload Document Modal -->
    <div 
        x-show="uploadModal" 
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-xs"
        @keydown.escape.window="uploadModal = false"
    >
        <div 
            class="bg-white dark:bg-slate-900 rounded-3xl p-6 max-w-sm w-full space-y-4 shadow-2xl border border-slate-200 dark:border-slate-800"
            @click.outside="uploadModal = false"
        >
            <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
                <div>
                    <h3 class="text-sm font-extrabold text-slate-900 dark:text-white" x-text="'Upload ' + activeLabel"></h3>
                    <p class="text-[11px] text-slate-400">JPEG, PNG or PDF (Max 5MB)</p>
                </div>
                <button type="button" @click="uploadModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
            </div>

            <form action="{{ route('rider.kyc.upload') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <input type="hidden" name="document_type" :value="activeType">

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                        Document / License Number (Optional)
                    </label>
                    <input 
                        type="text" 
                        name="document_number" 
                        x-model="docNumber"
                        placeholder="e.g. DL-1420110012345"
                        class="w-full text-xs font-medium p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-orange-500 uppercase"
                    >
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                        Select Document File <span class="text-rose-500">*</span>
                    </label>
                    <input 
                        type="file" 
                        name="document_file" 
                        required 
                        accept="image/jpeg,image/png,image/webp,application/pdf"
                        class="w-full text-xs font-medium file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100 text-slate-600 dark:text-slate-400"
                    >
                </div>

                <div class="flex items-center justify-end space-x-2 pt-2">
                    <button 
                        type="button" 
                        @click="uploadModal = false"
                        class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl"
                    >
                        Cancel
                    </button>
                    <button 
                        type="submit"
                        class="px-4 py-2 bg-orange-600 hover:bg-orange-500 text-white text-xs font-bold rounded-xl shadow-md cursor-pointer"
                    >
                        Upload &amp; Submit
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
