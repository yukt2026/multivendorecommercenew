@extends('layouts.storefront', ['title' => 'Request Return - Order #' . $order->order_number])

@section('content')
<div class="min-h-screen bg-slate-50 py-10">
    <div class="max-w-3xl mx-auto px-4 sm:px-6">

        <!-- Breadcrumb / Header -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('orders.show', $order->order_number) }}" class="inline-flex items-center text-xs font-bold text-indigo-600 hover:text-indigo-700 transition mb-2">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back to Order #{{ $order->order_number }}
                </a>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Request Item Return</h1>
                <p class="text-xs text-slate-500 mt-1">Submit your return request with reasons and verification photos.</p>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-900 border border-amber-300">
                Window: {{ $eligibility['window_days'] ?? 7 }} Days
            </span>
        </div>

        @if(!$eligibility['allowed'])
            <!-- Ineligibility Warning -->
            <div class="bg-rose-50 border border-rose-200 rounded-3xl p-6 text-center space-y-3 shadow-xs">
                <div class="w-12 h-12 rounded-2xl bg-rose-100 text-rose-600 flex items-center justify-center mx-auto text-xl font-bold">
                    ⚠️
                </div>
                <h3 class="text-base font-bold text-rose-900">Item Not Eligible for Return</h3>
                <p class="text-xs text-rose-700 max-w-md mx-auto">{{ $eligibility['reason'] }}</p>
                <div class="pt-2">
                    <a href="{{ route('orders.show', $order->order_number) }}" class="inline-block px-5 py-2.5 bg-white text-slate-800 text-xs font-bold rounded-xl border border-slate-300 hover:bg-slate-50 transition">
                        Return to Order
                    </a>
                </div>
            </div>
        @else

            <!-- Main Return Form -->
            <form 
                action="{{ route('orders.return.item', [$order->order_number, $item->id]) }}" 
                method="POST" 
                enctype="multipart/form-data" 
                class="space-y-6"
                x-data="{
                    refundMode: 'gateway',
                    reason: '',
                    previewImages: [],
                    handleFiles(event) {
                        const files = event.target.files;
                        this.previewImages = [];
                        for (let i = 0; i < files.length && i < 5; i++) {
                            const reader = new FileReader();
                            reader.onload = (e) => { this.previewImages.push(e.target.result); };
                            reader.readAsDataURL(files[i]);
                        }
                    }
                }"
            >
                @csrf

                <!-- Product Card Summary -->
                <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-xs flex items-center space-x-4">
                    <div class="w-16 h-16 rounded-2xl bg-slate-100 border border-slate-200 overflow-hidden shrink-0 flex items-center justify-center text-xl font-bold text-slate-400">
                        📦
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-[10px] uppercase font-bold text-indigo-600 tracking-wider">
                            {{ $item->fulfilmentOrder?->seller?->business_name ?? 'OneStall Direct' }}
                        </div>
                        <h2 class="text-sm font-black text-slate-900 truncate mt-0.5">{{ $item->title }}</h2>
                        <div class="text-xs text-slate-500 mt-0.5">
                            Qty: <strong class="text-slate-900">{{ $item->qty }}</strong> &bull; 
                            Paid: <strong class="text-slate-900">{{ format_currency((float)($item->total ?? ($item->price * $item->qty))) }}</strong>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <span class="text-xs font-bold text-emerald-600 block">Eligible for Return</span>
                        <span class="text-[10px] text-slate-400">Valid until {{ $eligibility['expires_at'] ? $eligibility['expires_at']->format('d M Y') : 'N/A' }}</span>
                    </div>
                </div>

                <!-- Return Reason & Description -->
                <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 space-y-6 shadow-xs">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Return Reason &amp; Details</h3>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Reason for Return *</label>
                        <select 
                            name="reason" 
                            x-model="reason" 
                            required 
                            class="w-full text-xs font-semibold p-3 rounded-xl border border-slate-200 bg-white focus:ring-2 focus:ring-indigo-500"
                        >
                            <option value="">-- Please select a return reason --</option>
                            <option value="Damaged in transit / Broken item">Damaged in transit / Broken item</option>
                            <option value="Wrong item or variant delivered">Wrong item or variant delivered</option>
                            <option value="Defective / Not functioning properly">Defective / Not functioning properly</option>
                            <option value="Item not matching pictures or description">Item not matching pictures or description</option>
                            <option value="Missing accessories or parts">Missing accessories or parts</option>
                            <option value="Quality not satisfactory">Quality not satisfactory</option>
                            <option value="Size or fit issue">Size or fit issue</option>
                            <option value="Other reason">Other reason</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Detailed Description / Problem Notes</label>
                        <textarea 
                            name="customer_notes" 
                            rows="4" 
                            placeholder="Please provide additional details about the issue to expedite inspection and pickup approval..." 
                            class="w-full text-xs font-medium p-3 rounded-xl border border-slate-200 bg-white focus:ring-2 focus:ring-indigo-500 leading-relaxed"
                        ></textarea>
                    </div>

                    <!-- Photo Upload Zone -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">
                            Upload Verification Photos (Up to 5 images)
                        </label>
                        <div class="border-2 border-dashed border-slate-300 rounded-2xl p-6 text-center hover:bg-slate-50 transition cursor-pointer relative">
                            <input 
                                type="file" 
                                name="photos[]" 
                                multiple 
                                accept="image/*" 
                                @change="handleFiles" 
                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                            >
                            <div class="space-y-1">
                                <div class="text-2xl text-slate-400">📸</div>
                                <div class="text-xs font-bold text-slate-700">Click or drag &amp; drop photos here</div>
                                <div class="text-[10px] text-slate-400">PNG, JPG, WebP up to 5MB each. Showing tags, defects, or box condition.</div>
                            </div>
                        </div>

                        <!-- Previews -->
                        <div class="grid grid-cols-5 gap-2 mt-3" x-show="previewImages.length > 0">
                            <template x-for="(img, idx) in previewImages" :key="idx">
                                <div class="h-16 rounded-xl border border-slate-200 overflow-hidden bg-slate-100">
                                    <img :src="img" class="w-full h-full object-cover">
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Refund Settlement Preference -->
                <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 space-y-4 shadow-xs">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Refund Settlement Method</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label 
                            class="p-4 rounded-2xl border cursor-pointer transition flex items-start space-x-3"
                            :class="refundMode === 'gateway' ? 'border-indigo-600 bg-indigo-50/50' : 'border-slate-200 hover:bg-slate-50'"
                        >
                            <input type="radio" name="refund_mode" value="gateway" x-model="refundMode" class="mt-0.5 text-indigo-600 focus:ring-indigo-500">
                            <div>
                                <div class="text-xs font-black text-slate-900">Original Payment Method</div>
                                <div class="text-[10px] text-slate-500 mt-0.5">Credited back to your bank / card / UPI in 3-5 business days upon QC pass.</div>
                            </div>
                        </label>

                        <label 
                            class="p-4 rounded-2xl border cursor-pointer transition flex items-start space-x-3"
                            :class="refundMode === 'wallet' ? 'border-indigo-600 bg-indigo-50/50' : 'border-slate-200 hover:bg-slate-50'"
                        >
                            <input type="radio" name="refund_mode" value="wallet" x-model="refundMode" class="mt-0.5 text-indigo-600 focus:ring-indigo-500">
                            <div>
                                <div class="text-xs font-black text-slate-900 flex items-center space-x-1.5">
                                    <span>OneStall Wallet Credit</span>
                                    <span class="px-1.5 py-0.5 text-[9px] font-bold bg-emerald-100 text-emerald-800 rounded">Instant</span>
                                </div>
                                <div class="text-[10px] text-slate-500 mt-0.5">Instant credit usable immediately on any future purchases without checkout friction.</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Submit Action -->
                <div class="flex items-center justify-between pt-2">
                    <a href="{{ route('orders.show', $order->order_number) }}" class="text-xs font-bold text-slate-500 hover:text-slate-800 transition">
                        Cancel
                    </a>
                    <button 
                        type="submit" 
                        class="px-8 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black rounded-2xl shadow-sm transition"
                    >
                        Submit Return Request &rarr;
                    </button>
                </div>
            </form>
        @endif

    </div>
</div>
@endsection
