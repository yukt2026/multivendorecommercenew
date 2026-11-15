@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Logistics & Shipping Oversight'])

@section('content')
<div class="space-y-6 max-w-6xl mx-auto">

    <!-- Page Header -->
    <x-page-header 
        title="Logistics & OneStall Cargo Oversight" 
        subtitle="Manage courier fallback priority chain, OneStall Cargo integration credentials, live consignments, and NDR/RTO exceptions."
        :breadcrumbs="['Command Console' => route('admin.dashboard'), 'Shipping & Logistics' => '#']"
    >
        <x-slot name="actions">
            <div class="flex items-center gap-2">
                <form method="POST" action="{{ route('admin.shipping.sync_all') }}">
                    @csrf
                    <x-button variant="outline" size="sm" type="submit">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Sync All Tracking
                    </x-button>
                </form>

                <x-button variant="outline" size="sm" href="{{ route('admin.shipping.failed_queue') }}" class="{{ $metrics['failed_consignments'] > 0 ? 'border-rose-300 text-rose-600' : '' }}">
                    Failed Queue ({{ $metrics['failed_consignments'] }})
                </x-button>
            </div>
        </x-slot>
    </x-page-header>

    <!-- KPI Summary Grid -->
    <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
        <x-stat-card 
            label="Total Consignments" 
            :value="$metrics['total_shipments']"
            color="primary"
        />
        <x-stat-card 
            label="In Transit" 
            :value="$metrics['in_transit']"
            color="sky"
        />
        <x-stat-card 
            label="NDR Exceptions" 
            :value="$metrics['ndr_unresolved']"
            color="rose"
        />
        <x-stat-card 
            label="RTO Total" 
            :value="$metrics['rto_total']"
            color="amber"
        />
        <x-stat-card 
            label="COD Pending" 
            :value="format_currency($metrics['cod_pending_remittance'])"
            color="emerald"
        />
        <x-stat-card 
            label="Failed / Missing AWB" 
            :value="$metrics['failed_consignments']"
            color="rose"
        />
    </div>

    <form method="POST" action="{{ route('admin.shipping.update') }}" class="space-y-6">
        @csrf

        <!-- OneStall Cargo API & Sandbox Card -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider text-[11px]">OneStall Cargo Platform Credentials</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Configuration and authentication credentials for external OneStall Cargo logistics API.</p>
                </div>
                <div class="flex items-center space-x-2">
                    <input type="checkbox" name="cargo_fake" id="cargo_fake_toggle" value="1" {{ $settings['cargo_fake'] ? 'checked' : '' }} class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    <label for="cargo_fake_toggle" class="text-xs font-bold text-indigo-600 dark:text-indigo-400">
                        Enable Fake Mode (Test Responses)
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Cargo API Base URL</label>
                    <input 
                        type="url" 
                        name="cargo_base_url" 
                        value="{{ old('cargo_base_url', $settings['cargo_base_url']) }}" 
                        placeholder="https://api.onestallcargo.internal/v1"
                        class="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 font-mono"
                    >
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Webhook Secret Key</label>
                    <input 
                        type="text" 
                        name="cargo_webhook_secret" 
                        value="{{ old('cargo_webhook_secret', $settings['cargo_webhook_secret']) }}" 
                        placeholder="Webhook HMAC Secret"
                        class="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 font-mono"
                    >
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Cargo API Client Key</label>
                    <input 
                        type="password" 
                        name="cargo_api_key" 
                        value="{{ old('cargo_api_key', $settings['cargo_api_key']) }}" 
                        placeholder="Bearer token or API Key"
                        class="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 font-mono"
                    >
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Cargo API Client Secret</label>
                    <input 
                        type="password" 
                        name="cargo_api_secret" 
                        value="{{ old('cargo_api_secret', $settings['cargo_api_secret']) }}" 
                        placeholder="HMAC Signing Secret"
                        class="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 font-mono"
                    >
                </div>
            </div>

            <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-xl text-[11px] text-slate-500 flex items-center justify-between">
                <span>Webhook Receiver URL: <strong class="font-mono text-slate-800 dark:text-slate-200">{{ url('/api/v1/cargo/webhook') }}</strong></span>
                <span class="text-xs">Signature: <strong class="font-mono">X-Cargo-Signature</strong></span>
            </div>
        </div>

        <!-- Courier Priority Chain Card -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider text-[11px]">Courier Fallback Priority Chain</h3>
                    <p class="text-xs text-slate-500 mt-0.5">The automated fulfilment dispatcher evaluates and assigns orders to couriers in this exact priority sequence.</p>
                </div>
            </div>

            <div class="space-y-3 pt-2" id="courier-priority-list">
                @php
                    $availableCouriers = [
                        'onestall_cargo' => ['name' => 'OneStall Cargo (Internal Fleet)', 'desc' => 'Native quick commerce & hyper-local express delivery fleet'],
                        'delhivery' => ['name' => 'Delhivery Express', 'desc' => 'Surface & Air national multimodal network with reverse logistics'],
                        'bluedart' => ['name' => 'Blue Dart Aviation', 'desc' => 'High-priority premium air cargo for tier-1 & tier-2 destinations'],
                        'shadowfax' => ['name' => 'Shadowfax On-Demand', 'desc' => 'Last-mile 2-wheeler intra-city and instant rider network'],
                    ];
                    $currentPriorities = $settings['courier_priorities'] ?? array_keys($availableCouriers);
                @endphp

                @foreach($currentPriorities as $index => $courierKey)
                    @php $courierInfo = $availableCouriers[$courierKey] ?? ['name' => ucfirst($courierKey), 'desc' => 'Courier partner']; @endphp
                    <div class="courier-row flex items-center justify-between p-3.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 rounded-xl transition" data-key="{{ $courierKey }}">
                        <div class="flex items-center space-x-3">
                            <span class="courier-badge w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-400 text-xs font-bold flex items-center justify-center">
                                {{ $index + 1 }}
                            </span>
                            <div>
                                <div class="text-xs font-bold text-slate-900 dark:text-white">{{ $courierInfo['name'] }}</div>
                                <div class="text-[10px] text-slate-500">{{ $courierInfo['desc'] }}</div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button type="button" class="btn-move-up p-1.5 text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-slate-200 dark:hover:bg-slate-700 rounded transition" title="Move Up">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                            </button>
                            <button type="button" class="btn-move-down p-1.5 text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-slate-200 dark:hover:bg-slate-700 rounded transition" title="Move Down">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <input type="hidden" name="courier_priorities[]" value="{{ $courierKey }}">
                            <span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400 text-[10px] font-bold rounded">
                                Active
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Weight & Parcel Thresholds -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider text-[11px]">Weight, Distance &amp; Tariffs</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Default Package Weight (kg) *</label>
                    <input 
                        type="number" 
                        step="0.01" 
                        name="default_weight_kg" 
                        value="{{ old('default_weight_kg', $settings['default_weight_kg']) }}" 
                        required 
                        class="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                    >
                    <p class="text-[10px] text-slate-400 mt-1">Applied for freight tariff rating when SKU weight is unspecified.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Standard Base Shipping Fee (₹) *</label>
                    <input 
                        type="number" 
                        step="0.01" 
                        name="standard_base_fee" 
                        value="{{ old('standard_base_fee', $settings['standard_base_fee']) }}" 
                        required 
                        class="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                    >
                    <p class="text-[10px] text-slate-400 mt-1">Default flat rate per fulfilment group under standard delivery.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Free Delivery Cart Threshold (₹) *</label>
                    <input 
                        type="number" 
                        step="0.01" 
                        name="free_shipping_threshold" 
                        value="{{ old('free_shipping_threshold', $settings['free_shipping_threshold']) }}" 
                        required 
                        class="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                    >
                    <p class="text-[10px] text-slate-400 mt-1">Orders above this order value receive waiver on standard shipping.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Quick Commerce Max Radius (km) *</label>
                    <input 
                        type="number" 
                        step="0.1" 
                        name="quick_max_radius_km" 
                        value="{{ old('quick_max_radius_km', $settings['quick_max_radius_km']) }}" 
                        required 
                        class="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                    >
                    <p class="text-[10px] text-slate-400 mt-1">Maximum geo-distance between customer and dark-store for 15-45m delivery.</p>
                </div>
            </div>
        </div>

        <!-- COD & Risk Parameters -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider text-[11px]">Risk Mitigation &amp; RTO Policies</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Cash on Delivery (COD) Max Order Limit (₹) *</label>
                    <input 
                        type="number" 
                        step="1" 
                        name="cod_max_limit" 
                        value="{{ old('cod_max_limit', $settings['cod_max_limit']) }}" 
                        required 
                        class="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                    >
                    <p class="text-[10px] text-slate-400 mt-1">Orders exceeding this total value require digital prepaid settlement.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Max RTO Delivery Attempts *</label>
                    <input 
                        type="number" 
                        min="1" 
                        max="5" 
                        name="rto_max_attempts" 
                        value="{{ old('rto_max_attempts', $settings['rto_max_attempts']) }}" 
                        required 
                        class="w-full text-xs rounded-lg border border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                    >
                    <p class="text-[10px] text-slate-400 mt-1">Attempt limit before courier initiates Return to Origin sequence.</p>
                </div>
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <button 
                type="submit" 
                class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm transition"
            >
                Save Shipping Configurations
            </button>
        </div>
    </form>

    <!-- Recent Consignments Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider text-[11px]">Recent Cargo Consignments</h3>
                <p class="text-xs text-slate-500 mt-0.5">Live tracking status and carrier booking records for latest consignments.</p>
            </div>
            <a 
                href="{{ route('admin.shipping.failed_queue') }}" 
                class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center space-x-1"
            >
                <span>View Failed Queue ({{ $metrics['failed_consignments'] }})</span>
                <span>&rarr;</span>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Shipment #</th>
                        <th class="px-6 py-3.5">Order</th>
                        <th class="px-6 py-3.5">Merchant</th>
                        <th class="px-6 py-3.5">Courier &amp; AWB</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5">Created At</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($recentShipments as $shp)
                    @php
                        $fo = $shp->fulfilmentOrder;
                        $order = $fo?->order;
                        $stVal = is_object($shp->status) ? $shp->status->value : (string) $shp->status;
                    @endphp
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        <td class="px-6 py-4 font-mono font-bold text-slate-900 dark:text-white">
                            {{ $shp->shipment_number }}
                        </td>
                        <td class="px-6 py-4">
                            @if($order)
                                <a href="{{ route('admin.orders.show', $order) }}" class="font-mono font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                                    {{ $order->order_number }}
                                </a>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-slate-800 dark:text-slate-200">
                            {{ $fo?->seller?->business_name ?? $fo?->seller?->legal_name ?? 'OneStall Direct' }}
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-semibold text-slate-900 dark:text-white">{{ $shp->courier ?: 'OneStall Cargo' }}</div>
                            <div class="font-mono text-[10px] text-slate-400 mt-0.5">
                                {{ $shp->awb ? 'AWB: ' . $shp->awb : 'Missing AWB' }}
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold border
                                {{ in_array($stVal, ['delivered']) ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-400' : '' }}
                                {{ in_array($stVal, ['in_transit', 'out_for_delivery', 'picked_up']) ? 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/60 dark:text-sky-400' : '' }}
                                {{ in_array($stVal, ['manifest_created', 'pending']) ? 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-400' : '' }}
                                {{ in_array($stVal, ['failed', 'cancelled']) ? 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/60 dark:text-rose-400' : '' }}
                            ">
                                {{ ucfirst(str_replace('_', ' ', $stVal)) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-slate-500 text-[11px]">
                            {{ $shp->created_at?->format('d M Y, h:i A') }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            @if(!$shp->awb || $stVal === 'failed')
                                <form method="POST" action="{{ route('admin.shipping.retry_shipment', $shp) }}" class="inline-block">
                                    @csrf
                                    <button type="submit" class="px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 text-[11px] font-semibold rounded-lg transition">
                                        Retry Booking
                                    </button>
                                </form>
                            @elseif($shp->label_url)
                                <a href="{{ $shp->label_url }}" target="_blank" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300 text-[11px] font-semibold rounded-lg transition inline-flex items-center space-x-1">
                                    <span>Shipping Label</span>
                                </a>
                            @else
                                <span class="text-slate-400 text-xs">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-slate-400">
                            No shipments created yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const list = document.getElementById('courier-priority-list');
    if (!list) return;

    function updateBadges() {
        list.querySelectorAll('.courier-row').forEach((row, idx) => {
            const badge = row.querySelector('.courier-badge');
            if (badge) badge.textContent = idx + 1;
        });
    }

    list.addEventListener('click', function (e) {
        const upBtn = e.target.closest('.btn-move-up');
        const downBtn = e.target.closest('.btn-move-down');
        const row = e.target.closest('.courier-row');
        if (!row) return;

        if (upBtn && row.previousElementSibling) {
            list.insertBefore(row, row.previousElementSibling);
            updateBadges();
        } else if (downBtn && row.nextElementSibling) {
            list.insertBefore(row.nextElementSibling, row);
            updateBadges();
        }
    });
});
</script>
@endsection
