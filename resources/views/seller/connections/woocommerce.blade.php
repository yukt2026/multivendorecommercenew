@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'WooCommerce Integration'])

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="WooCommerce Two-Way Sync" 
        subtitle="Connect your WordPress WooCommerce store for catalog import, two-way stock synchronization, and instant order webhooks."
        :breadcrumbs="['Seller Studio' => route('seller.dashboard'), 'Connect My Shop' => route('seller.connections.index'), 'WooCommerce' => '#']"
    >
        <x-slot name="actions">
            @if($connection)
                <div class="flex items-center space-x-2">
                    <form method="POST" action="{{ route('seller.connections.woocommerce.test') }}" class="inline">
                        @csrf
                        <button type="submit" class="px-3 py-1.5 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                            Test Connection
                        </button>
                    </form>
                    <form method="POST" action="{{ route('seller.connections.woocommerce.sync') }}" class="inline">
                        @csrf
                        <button type="submit" class="px-3 py-1.5 text-xs font-bold rounded-xl bg-purple-600 hover:bg-purple-700 text-white transition flex items-center space-x-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            <span>Sync Products Now</span>
                        </button>
                    </form>
                </div>
            @endif
        </x-slot>
    </x-page-header>

    <!-- Connection Setup Form -->
    <x-card title="WooCommerce REST API Credentials">
        <form method="POST" action="{{ route('seller.connections.woocommerce.connect') }}" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Target Physical Store / Hub <span class="text-rose-500">*</span></label>
                    <select name="store_id" required class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white font-semibold">
                        @foreach($stores as $st)
                            <option value="{{ $st->id }}" {{ $activeStore && $activeStore->id === $st->id ? 'selected' : '' }}>
                                {{ $st->name }} ({{ $st->location?->city ?? 'Central Hub' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">WooCommerce Store URL <span class="text-rose-500">*</span></label>
                    <input type="url" name="store_url" value="{{ old('store_url', $connection?->config['store_url'] ?? '') }}" placeholder="https://mybrandstore.com" required class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white font-mono" />
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Consumer Key (ck_...) <span class="text-rose-500">*</span></label>
                    <input type="text" name="consumer_key" value="{{ old('consumer_key', $connection?->config['consumer_key'] ?? '') }}" placeholder="ck_xxxxxxxxxxxxxxxxxxxxxxxx" required class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white font-mono" />
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Consumer Secret (cs_...) <span class="text-rose-500">*</span></label>
                    <input type="password" name="consumer_secret" value="{{ old('consumer_secret', $connection?->config['consumer_secret'] ?? '') }}" placeholder="cs_xxxxxxxxxxxxxxxxxxxxxxxx" required class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white font-mono" />
                </div>
            </div>

            <div class="pt-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <span class="text-xs text-slate-500">Credentials are securely encrypted in database using AES-256-GCM.</span>
                <x-button variant="primary" size="sm" type="submit">Save &amp; Connect WooCommerce</x-button>
            </div>
        </form>
    </x-card>

    @if($connection)
        <!-- Webhook Details Card -->
        <x-card title="Real-Time Inward Webhooks">
            <div class="space-y-3 text-xs">
                <p class="text-slate-600 dark:text-slate-400">
                    Paste this Webhook Delivery URL into your WordPress admin (<code class="bg-slate-100 dark:bg-slate-800 px-1 py-0.5 rounded font-mono">WooCommerce &rarr; Settings &rarr; Advanced &rarr; Webhooks</code>) for instant two-way stock updates:
                </p>
                <div class="flex items-center space-x-2">
                    <input type="text" readonly value="{{ $webhookUrl }}" class="flex-1 text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3 py-2 font-mono text-slate-900 dark:text-white" />
                    <button type="button" onclick="navigator.clipboard.writeText('{{ $webhookUrl }}'); alert('Webhook URL copied!');" class="px-3 py-2 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800">
                        Copy URL
                    </button>
                </div>
            </div>
        </x-card>

        <!-- Sync Logs Card -->
        <x-card title="WooCommerce Synchronization Logs" :padded="false">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            <th class="py-3 px-4">Entity</th>
                            <th class="py-3 px-4">Direction</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Message</th>
                            <th class="py-3 px-4">Timestamp</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                        @forelse($syncLogs as $log)
                            <tr>
                                <td class="py-2.5 px-4 font-mono font-bold">{{ $log->entity }}</td>
                                <td class="py-2.5 px-4">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase {{ $log->direction === 'inward' ? 'bg-sky-50 dark:bg-sky-950 text-sky-600' : 'bg-purple-50 dark:bg-purple-950 text-purple-600' }}">
                                        {{ $log->direction === 'inward' ? '⬇ Inward' : '⬆ Outward' }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-4">
                                    <x-badge :variant="$log->status === 'success' ? 'success' : 'danger'" size="sm" dot>
                                        {{ ucfirst($log->status) }}
                                    </x-badge>
                                </td>
                                <td class="py-2.5 px-4 text-slate-600 dark:text-slate-400 max-w-sm truncate">
                                    {{ $log->message }}
                                </td>
                                <td class="py-2.5 px-4 text-slate-400 text-[11px]">
                                    {{ $log->created_at->diffForHumans() }}
                                </td>
                                <td class="py-2.5 px-4 text-right">
                                    @if($log->status === 'failed')
                                        <form method="POST" action="{{ route('seller.connections.sync_logs.retry', $log) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="px-2 py-0.5 text-[10px] font-bold rounded bg-amber-500/10 text-amber-600 border border-amber-500/30">
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
                                <td colspan="6" class="py-6 text-center text-slate-400">
                                    No synchronization operations recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($syncLogs instanceof \Illuminate\Pagination\LengthAwarePaginator && $syncLogs->hasPages())
                <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                    {{ $syncLogs->links() }}
                </div>
            @endif
        </x-card>
    @endif
</div>
@endsection
