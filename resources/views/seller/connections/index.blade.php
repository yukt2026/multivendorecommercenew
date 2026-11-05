@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'Connect My Shop Wizard'])

@section('content')
<div class="space-y-8">
    <!-- Header -->
    <x-page-header 
        title="Connect My Shop" 
        subtitle="Omnichannel integration hub: connect physical stores, WooCommerce, Shopify, Custom REST APIs, or bulk CSV catalogs."
        :breadcrumbs="['Seller Studio' => route('seller.dashboard'), 'Connect My Shop' => route('seller.connections.index')]"
    >
        <x-slot name="badge">
            <x-badge variant="info" dot size="sm">
                Active Store: {{ $activeStore ? $activeStore->name : 'All Outlets' }}
            </x-badge>
        </x-slot>
    </x-page-header>

    <!-- Channels Grid (6 Cards) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- 1. Physical Retail Store -->
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 flex flex-col justify-between shadow-xs hover:border-indigo-400 dark:hover:border-indigo-600 transition">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800 flex items-center justify-center text-indigo-600 dark:text-indigo-400 font-bold text-xl">
                        🏪
                    </div>
                    @if($activeStore)
                        <x-badge variant="success" size="sm" dot>Connected</x-badge>
                    @else
                        <x-badge variant="neutral" size="sm">Not Configured</x-badge>
                    @endif
                </div>

                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Physical Store / Outlet</h3>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                        Connect retail outlets with GPS coordinates, operating hours, delivery radius, and localized shelf inventory.
                    </p>
                </div>

                <div class="pt-2 text-xs text-slate-600 dark:text-slate-400 space-y-1">
                    <div class="flex items-center space-x-1.5">
                        <span class="text-emerald-500">✓</span>
                        <span>Interactive map pin picker &amp; GPS</span>
                    </div>
                    <div class="flex items-center space-x-1.5">
                        <span class="text-emerald-500">✓</span>
                        <span>10-30 min Quick Commerce radius</span>
                    </div>
                    <div class="flex items-center space-x-1.5">
                        <span class="text-emerald-500">✓</span>
                        <span>Dedicated warehouse/shelf inventory</span>
                    </div>
                </div>
            </div>

            <div class="pt-6">
                <a href="{{ route('seller.stores.create') }}" class="w-full inline-flex items-center justify-center px-4 py-2 text-xs font-bold rounded-xl bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:hover:bg-indigo-900 text-indigo-700 dark:text-indigo-300 transition">
                    {{ $activeStore ? 'Manage Physical Outlets &rarr;' : 'Add Physical Store &rarr;' }}
                </a>
            </div>
        </div>

        <!-- 2. CSV / Excel Catalog Feed -->
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 flex flex-col justify-between shadow-xs hover:border-emerald-400 dark:hover:border-emerald-600 transition">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 flex items-center justify-center text-emerald-600 dark:text-emerald-400 font-bold text-xl">
                        📊
                    </div>
                    <x-badge variant="neutral" size="sm">Template Ready</x-badge>
                </div>

                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">CSV / Excel Bulk Import</h3>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                        Download spreadsheet template, upload product rows, preview column mappings, and queue bulk catalog creation.
                    </p>
                </div>

                <div class="pt-2 text-xs text-slate-600 dark:text-slate-400 space-y-1">
                    <div class="flex items-center space-x-1.5">
                        <span class="text-emerald-500">✓</span>
                        <span>Auto-detects custom column names</span>
                    </div>
                    <div class="flex items-center space-x-1.5">
                        <span class="text-emerald-500">✓</span>
                        <span>Dry-run preview with error alerts</span>
                    </div>
                    <div class="flex items-center space-x-1.5">
                        <span class="text-emerald-500">✓</span>
                        <span>Queued background processing</span>
                    </div>
                </div>
            </div>

            <div class="pt-6">
                <a href="{{ route('seller.connections.csv') }}" class="w-full inline-flex items-center justify-center px-4 py-2 text-xs font-bold rounded-xl bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/60 dark:hover:bg-emerald-900 text-emerald-700 dark:text-emerald-300 transition">
                    Open CSV Feed Wizard &rarr;
                </a>
            </div>
        </div>

        <!-- 3. WooCommerce Store -->
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 flex flex-col justify-between shadow-xs hover:border-purple-400 dark:hover:border-purple-600 transition">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="w-12 h-12 rounded-2xl bg-purple-50 dark:bg-purple-950/60 border border-purple-200 dark:border-purple-800 flex items-center justify-center text-purple-600 dark:text-purple-400 font-bold text-xl">
                        🛍️
                    </div>
                    @if(!empty($connections['woocommerce']))
                        <x-badge variant="success" size="sm" dot>Connected</x-badge>
                    @else
                        <x-badge variant="neutral" size="sm">Available</x-badge>
                    @endif
                </div>

                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">WooCommerce Integration</h3>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                        Connect WordPress / WooCommerce via REST API. Two-way product catalog, inventory sync, and real-time webhook updates.
                    </p>
                </div>

                <div class="pt-2 text-xs text-slate-600 dark:text-slate-400 space-y-1">
                    <div class="flex items-center space-x-1.5">
                        <span class="text-emerald-500">✓</span>
                        <span>Encrypted Consumer Key &amp; Secret</span>
                    </div>
                    <div class="flex items-center space-x-1.5">
                        <span class="text-emerald-500">✓</span>
                        <span>Instant connection health test</span>
                    </div>
                    <div class="flex items-center space-x-1.5">
                        <span class="text-emerald-500">✓</span>
                        <span>Two-way stock &amp; order webhooks</span>
                    </div>
                </div>
            </div>

            <div class="pt-6">
                <a href="{{ route('seller.connections.woocommerce') }}" class="w-full inline-flex items-center justify-center px-4 py-2 text-xs font-bold rounded-xl bg-purple-50 hover:bg-purple-100 dark:bg-purple-950/60 dark:hover:bg-purple-900 text-purple-700 dark:text-purple-300 transition">
                    {{ !empty($connections['woocommerce']) ? 'Manage WooCommerce &rarr;' : 'Connect WooCommerce &rarr;' }}
                </a>
            </div>
        </div>

        <!-- 4. Shopify Store -->
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 flex flex-col justify-between shadow-xs hover:border-emerald-400 dark:hover:border-emerald-600 transition">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 flex items-center justify-center text-emerald-600 dark:text-emerald-400 font-bold text-xl">
                        🛒
                    </div>
                    @if(!empty($connections['shopify']))
                        <x-badge variant="success" size="sm" dot>Connected</x-badge>
                    @else
                        <x-badge variant="neutral" size="sm">Available</x-badge>
                    @endif
                </div>

                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Shopify Storefront</h3>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                        Connect Shopify stores via Admin API access tokens. Synchronize products, variant pricing, and stock balances.
                    </p>
                </div>

                <div class="pt-2 text-xs text-slate-600 dark:text-slate-400 space-y-1">
                    <div class="flex items-center space-x-1.5">
                        <span class="text-emerald-500">✓</span>
                        <span>Shopify Admin API token auth</span>
                    </div>
                    <div class="flex items-center space-x-1.5">
                        <span class="text-emerald-500">✓</span>
                        <span>Automated multi-variant import</span>
                    </div>
                    <div class="flex items-center space-x-1.5">
                        <span class="text-emerald-500">✓</span>
                        <span>Shopify webhook event listener</span>
                    </div>
                </div>
            </div>

            <div class="pt-6">
                <a href="{{ route('seller.connections.shopify') }}" class="w-full inline-flex items-center justify-center px-4 py-2 text-xs font-bold rounded-xl bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/60 dark:hover:bg-emerald-900 text-emerald-700 dark:text-emerald-300 transition">
                    {{ !empty($connections['shopify']) ? 'Manage Shopify &rarr;' : 'Connect Shopify &rarr;' }}
                </a>
            </div>
        </div>

        <!-- 5. Custom Website API -->
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 flex flex-col justify-between shadow-xs hover:border-amber-400 dark:hover:border-amber-600 transition">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800 flex items-center justify-center text-amber-600 dark:text-amber-400 font-bold text-xl">
                        ⚡
                    </div>
                    @if(!empty($connections['custom_api']))
                        <x-badge variant="success" size="sm" dot>Live API Key</x-badge>
                    @else
                        <x-badge variant="neutral" size="sm">Available</x-badge>
                    @endif
                </div>

                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Custom Website REST API</h3>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                        Integrate ERPs, custom web apps, or POS systems using scoped API keys, HMAC signatures, webhooks, and rate-limited endpoints.
                    </p>
                </div>

                <div class="pt-2 text-xs text-slate-600 dark:text-slate-400 space-y-1">
                    <div class="flex items-center space-x-1.5">
                        <span class="text-emerald-500">✓</span>
                        <span>Hashed credentials &amp; granular scopes</span>
                    </div>
                    <div class="flex items-center space-x-1.5">
                        <span class="text-emerald-500">✓</span>
                        <span>Endpoints: /products, /inventory, /orders</span>
                    </div>
                    <div class="flex items-center space-x-1.5">
                        <span class="text-emerald-500">✓</span>
                        <span>Outgoing event webhooks</span>
                    </div>
                </div>
            </div>

            <div class="pt-6">
                <a href="{{ route('seller.connections.custom_api') }}" class="w-full inline-flex items-center justify-center px-4 py-2 text-xs font-bold rounded-xl bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/60 dark:hover:bg-amber-900 text-amber-700 dark:text-amber-300 transition">
                    {{ !empty($connections['custom_api']) ? 'API Keys & Docs &rarr;' : 'Generate API Key &rarr;' }}
                </a>
            </div>
        </div>

        <!-- 6. Manual Entry -->
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 flex flex-col justify-between shadow-xs hover:border-slate-400 dark:hover:border-slate-600 transition">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-300 font-bold text-xl">
                        ✍️
                    </div>
                    <x-badge variant="neutral" size="sm">Direct Input</x-badge>
                </div>

                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Manual Storefront Entry</h3>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                        Add individual products, upload photos, set pricing, and manage local stock adjustments manually via the digital catalog.
                    </p>
                </div>

                <div class="pt-2 text-xs text-slate-600 dark:text-slate-400 space-y-1">
                    <div class="flex items-center space-x-1.5">
                        <span class="text-emerald-500">✓</span>
                        <span>Rich media &amp; multi-variant generator</span>
                    </div>
                    <div class="flex items-center space-x-1.5">
                        <span class="text-emerald-500">✓</span>
                        <span>Instant SKU barcode assignments</span>
                    </div>
                    <div class="flex items-center space-x-1.5">
                        <span class="text-emerald-500">✓</span>
                        <span>Zero tech setup required</span>
                    </div>
                </div>
            </div>

            <div class="pt-6">
                <a href="{{ route('seller.products.create') }}" class="w-full inline-flex items-center justify-center px-4 py-2 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition">
                    Add Product Manually &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- Recent Integration Sync Audit Log -->
    <x-card title="Recent Integration Sync Audit Activity" :padded="false">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        <th class="py-3 px-4">Channel</th>
                        <th class="py-3 px-4">Entity</th>
                        <th class="py-3 px-4">Direction</th>
                        <th class="py-3 px-4">Message</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Timestamp</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                    @forelse($recentSyncs as $log)
                        <tr>
                            <td class="py-2.5 px-4 font-bold text-slate-900 dark:text-white capitalize">
                                {{ str_replace('_', ' ', $log->connection->type->value ?? 'Channel') }}
                            </td>
                            <td class="py-2.5 px-4 font-mono font-bold text-slate-700 dark:text-slate-300">
                                {{ $log->entity }}
                            </td>
                            <td class="py-2.5 px-4">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase {{ $log->direction === 'inward' ? 'bg-sky-50 dark:bg-sky-950 text-sky-600 dark:text-sky-400' : 'bg-purple-50 dark:bg-purple-950 text-purple-600 dark:text-purple-400' }}">
                                    {{ $log->direction === 'inward' ? '⬇ Inward' : '⬆ Outward' }}
                                </span>
                            </td>
                            <td class="py-2.5 px-4 text-slate-600 dark:text-slate-400 max-w-sm truncate">
                                {{ $log->message }}
                            </td>
                            <td class="py-2.5 px-4">
                                <x-badge :variant="$log->status === 'success' ? 'success' : 'danger'" size="sm" dot>
                                    {{ ucfirst($log->status) }}
                                </x-badge>
                            </td>
                            <td class="py-2.5 px-4 text-slate-400 text-[11px]">
                                {{ $log->created_at->diffForHumans() }}
                            </td>
                            <td class="py-2.5 px-4 text-right">
                                @if($log->status === 'failed')
                                    <form method="POST" action="{{ route('seller.connections.sync_logs.retry', $log) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="px-2 py-0.5 text-[10px] font-bold rounded bg-amber-500/10 text-amber-600 hover:bg-amber-500/20 border border-amber-500/30 transition">
                                            Retry
                                        </button>
                                    </form>
                                @else
                                    <span class="text-slate-400 text-[10px]">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-6 text-center text-slate-400">
                                No synchronization activity recorded yet. Connect a channel above to begin syncing.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
@endsection
