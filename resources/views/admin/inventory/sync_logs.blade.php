@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Integration Sync Logs'])

@section('content')
<div class="space-y-6" x-data="{ payloadModal: false, activePayload: '' }">
    <!-- Header -->
    <x-page-header 
        title="Bi-Directional POS &amp; Integration Sync Logs" 
        subtitle="Audit external WooCommerce, Shopify, physical store POS, and CSV catalogue synchronization events."
        :breadcrumbs="['Governance' => route('admin.dashboard'), 'Global Inventory' => route('admin.inventory.index'), 'Sync Logs' => '#']"
    >
        <x-slot name="actions">
            <x-button variant="outline" size="sm" href="{{ route('admin.inventory.index') }}">
                &larr; Global Inventory
            </x-button>
        </x-slot>
    </x-page-header>

    <!-- Filters Bar -->
    <x-card>
        <form method="GET" action="{{ route('admin.inventory.sync_logs') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div>
                <select name="status" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 p-2.5 text-slate-900 dark:text-white">
                    <option value="">All Statuses</option>
                    <option value="success" {{ request('status') === 'success' ? 'selected' : '' }}>Success</option>
                    <option value="partial" {{ request('status') === 'partial' ? 'selected' : '' }}>Partial Sync</option>
                    <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                </select>
            </div>

            <div>
                <select name="entity" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 p-2.5 text-slate-900 dark:text-white">
                    <option value="">All Entities</option>
                    <option value="inventory" {{ request('entity') === 'inventory' ? 'selected' : '' }}>Inventory</option>
                    <option value="products" {{ request('entity') === 'products' ? 'selected' : '' }}>Products</option>
                    <option value="orders" {{ request('entity') === 'orders' ? 'selected' : '' }}>Orders</option>
                </select>
            </div>

            <div>
                <select name="direction" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 p-2.5 text-slate-900 dark:text-white">
                    <option value="">All Directions</option>
                    <option value="inward" {{ request('direction') === 'inward' ? 'selected' : '' }}>Inward (From External)</option>
                    <option value="outward" {{ request('direction') === 'outward' ? 'selected' : '' }}>Outward (To External)</option>
                </select>
            </div>

            <div class="flex items-center space-x-2">
                <button type="submit" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shrink-0">
                    Apply Filters
                </button>
                @if(request()->anyFilled(['status', 'entity', 'direction']))
                    <a href="{{ route('admin.inventory.sync_logs') }}" class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-500 hover:bg-slate-100 shrink-0">
                        &times;
                    </a>
                @endif
            </div>
        </form>
    </x-card>

    <!-- Logs Table -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-950/50 border-b border-slate-200/80 dark:border-slate-800 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                    <tr>
                        <th class="py-4 px-4">Connection / Store</th>
                        <th class="py-4 px-3">Entity</th>
                        <th class="py-4 px-3">Direction</th>
                        <th class="py-4 px-3">Status</th>
                        <th class="py-4 px-4">Log Message</th>
                        <th class="py-4 px-3">Payload</th>
                        <th class="py-4 px-4 text-right">Timestamp</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium text-slate-700 dark:text-slate-300">
                    @forelse($logs as $log)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition">
                            <td class="py-3 px-4 font-bold text-slate-900 dark:text-white">
                                {{ $log->connection?->store?->name ?? 'External Hub Connection' }}
                                <div class="text-[10px] text-slate-400 font-normal">
                                    {{ $log->connection?->store?->seller?->business_name ?? 'Merchant API' }}
                                </div>
                            </td>
                            <td class="py-3 px-3 uppercase text-[10px] font-bold">
                                {{ $log->entity }}
                            </td>
                            <td class="py-3 px-3">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase {{ $log->direction === 'inward' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300' : 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300' }}">
                                    {{ $log->direction }}
                                </span>
                            </td>
                            <td class="py-3 px-3">
                                @if($log->status === 'success')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">Success</span>
                                @elseif($log->status === 'failed')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-300">Failed</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300">Partial</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-slate-600 dark:text-slate-400 max-w-xs truncate" title="{{ $log->message }}">
                                {{ $log->message ?: 'No message recorded' }}
                            </td>
                            <td class="py-3 px-3">
                                @if(!empty($log->payload))
                                    <button 
                                        type="button" 
                                        @click="activePayload = @js(json_encode($log->payload, JSON_PRETTY_PRINT)); payloadModal = true;"
                                        class="px-2 py-1 rounded bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-[10px] font-mono text-indigo-600 font-bold"
                                    >
                                        Inspect JSON
                                    </button>
                                @else
                                    <span class="text-slate-400 text-[10px]">None</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right text-slate-400 font-mono text-[11px] whitespace-nowrap">
                                {{ $log->created_at->format('d M Y, h:i A') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-500">
                                No integration sync logs recorded yet.
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

    <!-- Payload JSON Modal -->
    <div 
        x-show="payloadModal" 
        x-cloak 
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
    >
        <div 
            class="bg-white dark:bg-slate-900 rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4"
            @click.outside="payloadModal = false"
        >
            <div class="flex items-center justify-between border-b pb-3 border-slate-100 dark:border-slate-800">
                <h3 class="font-bold text-sm text-slate-900 dark:text-white">Raw Sync Payload Inspector</h3>
                <button type="button" @click="payloadModal = false" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>
            <pre class="bg-slate-950 text-emerald-400 p-4 rounded-xl text-xs font-mono max-h-96 overflow-y-auto whitespace-pre-wrap" x-text="activePayload"></pre>
            <div class="flex justify-end">
                <x-button variant="outline" size="sm" @click="payloadModal = false">
                    Close
                </x-button>
            </div>
        </div>
    </div>
</div>
@endsection
