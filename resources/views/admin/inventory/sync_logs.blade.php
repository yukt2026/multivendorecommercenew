@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Integration Sync Logs'])

@section('content')
<div class="space-y-6" x-data="{ 
    payloadModal: false, 
    activePayload: '', 
    activeTitle: '',
    activeMeta: '',
    copied: false,
    pruneModal: false,
    openPayload(title, meta, payload) {
        this.activeTitle = title;
        this.activeMeta = meta;
        try {
            if (typeof payload === 'string') {
                this.activePayload = JSON.stringify(JSON.parse(payload), null, 2);
            } else if (payload && typeof payload === 'object') {
                this.activePayload = JSON.stringify(payload, null, 2);
            } else {
                this.activePayload = 'No payload recorded.';
            }
        } catch (e) {
            this.activePayload = String(payload);
        }
        this.copied = false;
        this.payloadModal = true;
    },
    copyPayload() {
        navigator.clipboard.writeText(this.activePayload);
        this.copied = true;
        setTimeout(() => this.copied = false, 2000);
    }
}">
    <!-- Header -->
    <x-page-header 
        title="Bi-Directional POS & Integration Sync Logs" 
        subtitle="Audit external WooCommerce, Shopify, physical store POS, and catalogue synchronization events."
        :breadcrumbs="['Governance' => route('admin.dashboard'), 'Global Inventory' => route('admin.inventory.index'), 'Sync Logs' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                {{ number_format($metrics['total_logs']) }} Sync Events
            </span>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center space-x-2">
                <a href="{{ route('admin.inventory.sync_logs.export', request()->query()) }}" class="px-3.5 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-semibold flex items-center space-x-1.5 transition">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Export CSV</span>
                </a>

                <button type="button" @click="pruneModal = true" class="px-3.5 py-2 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60 rounded-xl text-xs font-semibold flex items-center space-x-1.5 transition">
                    <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    <span>Prune Old Logs</span>
                </button>

                <x-button variant="outline" size="sm" href="{{ route('admin.inventory.index') }}">
                    &larr; Global Inventory
                </x-button>
            </div>
        </x-slot>
    </x-page-header>

    <!-- Operational KPI Metrics Summary -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-2xs">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Syncs</div>
            <div class="text-2xl font-black font-mono text-slate-900 dark:text-white mt-1">{{ number_format($metrics['total_logs']) }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">All time logged events</div>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-2xs">
            <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Successful</div>
            <div class="text-2xl font-black font-mono text-emerald-600 dark:text-emerald-400 mt-1">{{ number_format($metrics['successful_syncs']) }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">Healthy execution</div>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-2xs">
            <div class="text-[11px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400">Failed / Errors</div>
            <div class="text-2xl font-black font-mono text-rose-600 dark:text-rose-400 mt-1">{{ number_format($metrics['failed_syncs']) }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">Exceptions / Network drop</div>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-2xs">
            <div class="text-[11px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">Partial Syncs</div>
            <div class="text-2xl font-black font-mono text-amber-600 dark:text-amber-400 mt-1">{{ number_format($metrics['partial_syncs']) }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">Items partially processed</div>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-2xs">
            <div class="text-[11px] font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Past 24 Hours</div>
            <div class="text-2xl font-black font-mono text-indigo-600 dark:text-indigo-400 mt-1">{{ number_format($metrics['last_24h_syncs']) }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">Recent sync velocity</div>
        </div>
    </div>

    <!-- Filters Bar -->
    <x-card padding="p-4">
        <form method="GET" action="{{ route('admin.inventory.sync_logs') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
            <!-- Search -->
            <div class="lg:col-span-2">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1">Search Store / Seller / Log Message</label>
                <div class="relative">
                    <input 
                        type="text" 
                        name="search" 
                        value="{{ request('search') }}" 
                        placeholder="e.g. Apex Store, WooCommerce, SKU-101..." 
                        class="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/60 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            <!-- Status Filter -->
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1">Status</label>
                <select name="status" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 py-2 px-2.5 text-slate-900 dark:text-white">
                    <option value="">All Statuses</option>
                    <option value="success" {{ request('status') === 'success' ? 'selected' : '' }}>Success</option>
                    <option value="partial" {{ request('status') === 'partial' ? 'selected' : '' }}>Partial Sync</option>
                    <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                </select>
            </div>

            <!-- Entity Filter -->
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1">Entity</label>
                <select name="entity" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 py-2 px-2.5 text-slate-900 dark:text-white">
                    <option value="">All Entities</option>
                    <option value="inventory" {{ request('entity') === 'inventory' ? 'selected' : '' }}>Inventory</option>
                    <option value="products" {{ request('entity') === 'products' ? 'selected' : '' }}>Products</option>
                    <option value="orders" {{ request('entity') === 'orders' ? 'selected' : '' }}>Orders</option>
                </select>
            </div>

            <!-- Direction Filter -->
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1">Direction</label>
                <select name="direction" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 py-2 px-2.5 text-slate-900 dark:text-white">
                    <option value="">All Directions</option>
                    <option value="inward" {{ request('direction') === 'inward' ? 'selected' : '' }}>Inward (From External)</option>
                    <option value="outward" {{ request('direction') === 'outward' ? 'selected' : '' }}>Outward (To External)</option>
                </select>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center space-x-2">
                <button type="submit" class="flex-1 py-2 px-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs transition">
                    Filter
                </button>
                @if(request()->anyFilled(['search', 'status', 'entity', 'direction', 'connection_id', 'date_from', 'date_to']))
                    <a href="{{ route('admin.inventory.sync_logs') }}" class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition" title="Reset Filters">
                        &times;
                    </a>
                @endif
            </div>
        </form>
    </x-card>

    <!-- Logs Table -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-2xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-950/50 border-b border-slate-200/80 dark:border-slate-800 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                    <tr>
                        <th class="py-3.5 px-4">Connection / Store</th>
                        <th class="py-3.5 px-3">Entity</th>
                        <th class="py-3.5 px-3">Direction</th>
                        <th class="py-3.5 px-3">Status</th>
                        <th class="py-3.5 px-4">Log Message</th>
                        <th class="py-3.5 px-3 text-center">Payload</th>
                        <th class="py-3.5 px-4 text-right">Timestamp</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium text-slate-700 dark:text-slate-300">
                    @forelse($logs as $log)
                        @php
                            $conn = $log->connection;
                            $store = $conn?->store;
                            $seller = $store?->seller;
                            $sellerName = $seller?->business_name ?? $seller?->legal_name ?? 'Merchant API';
                            $titleText = "Log #{$log->id} • " . ($conn?->name ?? 'Direct Sync');
                            $metaText = strtoupper($log->entity) . ' • ' . strtoupper($log->direction) . ' • ' . strtoupper($log->status) . ' • ' . $log->created_at->format('d M Y, h:i A');
                        @endphp
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition">
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-900 dark:text-white">
                                    {{ $conn?->name ?? 'External Hub Connection' }}
                                </div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400 font-normal">
                                    {{ $store?->name ?? 'Platform Store' }} &bull; <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $sellerName }}</span>
                                </div>
                            </td>
                            <td class="py-3.5 px-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                    {{ $log->entity }}
                                </span>
                            </td>
                            <td class="py-3.5 px-3">
                                @if($log->direction === 'inward')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 border border-indigo-200/60 dark:border-indigo-800">
                                        &darr; Inward
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-purple-50 text-purple-700 dark:bg-purple-950/70 dark:text-purple-300 border border-purple-200/60 dark:border-purple-800">
                                        &uarr; Outward
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-3">
                                @if($log->status === 'success')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1"></span>
                                        Success
                                    </span>
                                @elseif($log->status === 'failed')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400 border border-rose-200 dark:border-rose-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500 mr-1"></span>
                                        Failed
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-1"></span>
                                        Partial
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-slate-600 dark:text-slate-300 max-w-xs truncate" title="{{ $log->message }}">
                                {{ $log->message ?: 'No message recorded' }}
                            </td>
                            <td class="py-3.5 px-3 text-center">
                                @if(!empty($log->payload))
                                    <button 
                                        type="button" 
                                        @click="openPayload(@js($titleText), @js($metaText), @js($log->payload))"
                                        class="px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:hover:bg-indigo-900 text-[10px] font-mono text-indigo-700 dark:text-indigo-300 font-bold border border-indigo-200/60 dark:border-indigo-800 transition cursor-pointer"
                                    >
                                        Inspect JSON
                                    </button>
                                @else
                                    <span class="text-slate-400 text-[10px]">None</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right text-slate-400 font-mono text-[11px] whitespace-nowrap">
                                {{ $log->created_at->format('d M Y, h:i A') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-500 dark:text-slate-400">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="w-8 h-8 text-slate-300 dark:text-slate-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                    <span class="text-xs font-semibold">No integration sync logs found</span>
                                    <span class="text-[11px] text-slate-400 mt-0.5">Logs appear when external POS, WooCommerce, or inventory synchronization runs.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div class="p-4 border-t border-slate-200/80 dark:border-slate-800">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

    <!-- Payload JSON Inspector Modal -->
    <div 
        x-show="payloadModal" 
        x-cloak 
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-4"
    >
        <div 
            class="bg-white dark:bg-slate-900 rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4 max-h-[90vh] flex flex-col"
            @click.outside="payloadModal = false"
        >
            <div class="flex items-center justify-between border-b pb-3 border-slate-100 dark:border-slate-800 shrink-0">
                <div>
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white" x-text="activeTitle"></h3>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 font-mono mt-0.5" x-text="activeMeta"></p>
                </div>
                <button type="button" @click="payloadModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg">&times;</button>
            </div>

            <div class="relative flex-1 min-h-0 overflow-hidden rounded-xl border border-slate-800">
                <button 
                    type="button" 
                    @click="copyPayload()" 
                    class="absolute top-3 right-3 px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition"
                >
                    <span x-text="copied ? 'Copied!' : 'Copy JSON'"></span>
                </button>
                <pre class="bg-slate-950 text-emerald-400 p-4 text-xs font-mono h-full overflow-y-auto whitespace-pre-wrap selection:bg-indigo-500 selection:text-white" x-text="activePayload"></pre>
            </div>

            <div class="flex items-center justify-between pt-2 shrink-0">
                <span class="text-[11px] text-slate-400">JSON formatted structure</span>
                <x-button variant="outline" size="sm" @click="payloadModal = false">
                    Close Inspector
                </x-button>
            </div>
        </div>
    </div>

    <!-- Prune Old Logs Modal -->
    <div 
        x-show="pruneModal" 
        x-cloak 
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-4"
    >
        <div 
            class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4"
            @click.outside="pruneModal = false"
        >
            <div class="flex items-center justify-between border-b pb-3 border-slate-100 dark:border-slate-800">
                <h3 class="font-bold text-sm text-slate-900 dark:text-white">Prune Historical Sync Logs</h3>
                <button type="button" @click="pruneModal = false" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                Clean up accumulated log history to optimize database size. Recent sync events will be preserved based on the retention window selected below.
            </p>

            <form method="POST" action="{{ route('admin.inventory.sync_logs.prune') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Retention Window
                    </label>
                    <select name="days" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 p-2.5 text-slate-900 dark:text-white">
                        <option value="30">Delete logs older than 30 days</option>
                        <option value="60">Delete logs older than 60 days</option>
                        <option value="90">Delete logs older than 90 days</option>
                        <option value="7">Delete logs older than 7 days</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Status Filter to Prune
                    </label>
                    <select name="status" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 p-2.5 text-slate-900 dark:text-white">
                        <option value="all">Prune all logs regardless of status</option>
                        <option value="success">Prune only successful syncs (keep failures)</option>
                    </select>
                </div>

                <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <x-button variant="outline" size="sm" type="button" @click="pruneModal = false">
                        Cancel
                    </x-button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs transition">
                        Confirm &amp; Prune
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
