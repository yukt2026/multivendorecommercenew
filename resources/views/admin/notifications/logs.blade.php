@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Notification Delivery Logs'])

@section('content')
<div class="space-y-6" x-data="{ inspectedLog: null }">

    <!-- Page Header -->
    <x-page-header 
        title="Notification Audit & Delivery Logs" 
        subtitle="End-to-end telemetry across Email, SMS, WhatsApp, and Push drivers with failure retry."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Notification Logs' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                {{ number_format($stats['total']) }} Events Logged
            </span>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center space-x-2">
                @if($stats['failed'] > 0)
                <form method="POST" action="{{ route('admin.notifications.logs.retry_all') }}" onsubmit="return confirm('Retry all pending failed notifications (up to 50)?');">
                    @csrf
                    <button 
                        type="submit" 
                        class="px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:hover:bg-rose-900/60 dark:text-rose-400 border border-rose-200 dark:border-rose-800 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition"
                    >
                        <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <span>Retry All Failed ({{ $stats['failed'] }})</span>
                    </button>
                </form>
                @endif

                <a 
                    href="{{ route('admin.notifications.logs.export', request()->query()) }}" 
                    class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition"
                >
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Export Telemetry CSV</span>
                </a>
            </div>
        </x-slot>
    </x-page-header>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">Total Logged Dispatches</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ number_format($stats['total']) }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
            <div class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">Delivered / Sent</div>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ number_format($stats['sent']) }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
            <div class="text-xs font-semibold text-rose-600 dark:text-rose-400">Delivery Failures</div>
            <div class="text-2xl font-bold text-rose-600 dark:text-rose-400 mt-1">{{ number_format($stats['failed']) }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
            <div class="text-xs font-semibold text-amber-600 dark:text-amber-400">Retried &amp; Recovered</div>
            <div class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ number_format($stats['retried']) }}</div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
        <form method="GET" action="{{ route('admin.notifications.logs.index') }}" class="flex flex-wrap items-center gap-3">
            <div class="relative flex-1 min-w-[220px]">
                <input 
                    type="text" 
                    name="search" 
                    value="{{ $search }}" 
                    placeholder="Search recipient, content, error, event..." 
                    class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white pl-8 pr-3 py-2 focus:ring-1 focus:ring-indigo-500"
                >
                <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            <select name="channel" class="text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white py-2 px-3">
                <option value="all" {{ $selectedChannel === 'all' ? 'selected' : '' }}>All Channels</option>
                <option value="mail" {{ $selectedChannel === 'mail' ? 'selected' : '' }}>Mail</option>
                <option value="sms" {{ $selectedChannel === 'sms' ? 'selected' : '' }}>SMS</option>
                <option value="whatsapp" {{ $selectedChannel === 'whatsapp' ? 'selected' : '' }}>WhatsApp</option>
                <option value="push" {{ $selectedChannel === 'push' ? 'selected' : '' }}>Push</option>
                <option value="database" {{ $selectedChannel === 'database' ? 'selected' : '' }}>Database</option>
            </select>

            <select name="status" class="text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white py-2 px-3">
                <option value="all" {{ $selectedStatus === 'all' ? 'selected' : '' }}>All Statuses</option>
                <option value="sent" {{ $selectedStatus === 'sent' ? 'selected' : '' }}>Sent Only</option>
                <option value="failed" {{ $selectedStatus === 'failed' ? 'selected' : '' }}>Failed Only</option>
                <option value="retried" {{ $selectedStatus === 'retried' ? 'selected' : '' }}>Retried Only</option>
            </select>

            <select name="event" class="text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white py-2 px-3">
                <option value="all" {{ $selectedEvent === 'all' ? 'selected' : '' }}>All Events</option>
                <option value="otp" {{ $selectedEvent === 'otp' ? 'selected' : '' }}>OTP</option>
                <option value="order_confirmed" {{ $selectedEvent === 'order_confirmed' ? 'selected' : '' }}>Order Confirmed</option>
                <option value="payment_confirmed" {{ $selectedEvent === 'payment_confirmed' ? 'selected' : '' }}>Payment Confirmed</option>
                <option value="order_packing" {{ $selectedEvent === 'order_packing' ? 'selected' : '' }}>Order Packing</option>
                <option value="shipment_update" {{ $selectedEvent === 'shipment_update' ? 'selected' : '' }}>Shipment Update</option>
                <option value="out_for_delivery" {{ $selectedEvent === 'out_for_delivery' ? 'selected' : '' }}>Out For Delivery</option>
                <option value="order_delivered" {{ $selectedEvent === 'order_delivered' ? 'selected' : '' }}>Order Delivered</option>
                <option value="return_refund" {{ $selectedEvent === 'return_refund' ? 'selected' : '' }}>Return &amp; Refund</option>
                <option value="seller_new_order" {{ $selectedEvent === 'seller_new_order' ? 'selected' : '' }}>Seller New Order</option>
                <option value="low_stock_alert" {{ $selectedEvent === 'low_stock_alert' ? 'selected' : '' }}>Low Stock Alert</option>
                <option value="settlement_paid" {{ $selectedEvent === 'settlement_paid' ? 'selected' : '' }}>Settlement Paid</option>
                <option value="promotional" {{ $selectedEvent === 'promotional' ? 'selected' : '' }}>Promotional</option>
            </select>

            <input 
                type="date" 
                name="date" 
                value="{{ $selectedDate }}" 
                class="text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white py-2 px-3"
                title="Filter by Date"
            >

            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow-sm transition">
                Filter
            </button>
            <a href="{{ route('admin.notifications.logs.index') }}" class="px-3 py-2 text-xs text-slate-500 hover:text-slate-800 dark:hover:text-slate-200">
                Reset
            </a>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 uppercase text-[10px] tracking-wider bg-slate-50 dark:bg-slate-800/60 font-semibold">
                        <th class="py-3.5 px-6">#ID</th>
                        <th class="py-3.5 px-6">Recipient</th>
                        <th class="py-3.5 px-6">Channel</th>
                        <th class="py-3.5 px-6">Event</th>
                        <th class="py-3.5 px-6">Status</th>
                        <th class="py-3.5 px-6">Subject / Content Preview</th>
                        <th class="py-3.5 px-6 text-center">Retries</th>
                        <th class="py-3.5 px-6">Timestamp</th>
                        <th class="py-3.5 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($logs as $log)
                        @php
                            $channelBadge = match($log->channel) {
                                'mail' => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/60 dark:text-blue-400',
                                'sms' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-400',
                                'whatsapp' => 'bg-green-50 text-green-700 border-green-200 dark:bg-green-950/60 dark:text-green-400',
                                'push' => 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/60 dark:text-purple-400',
                                default => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-400',
                            };

                            $statusBadge = match($log->status) {
                                'sent' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-400',
                                'retried' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-400',
                                default => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/60 dark:text-rose-400',
                            };
                            $dotColor = match($log->status) {
                                'sent' => 'bg-emerald-500',
                                'retried' => 'bg-amber-500',
                                default => 'bg-rose-500',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-4 px-6 font-mono font-bold text-slate-500">
                                #{{ $log->id }}
                            </td>
                            <td class="py-4 px-6 font-semibold text-slate-900 dark:text-white">
                                {{ $log->recipient }}
                            </td>
                            <td class="py-4 px-6">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border {{ $channelBadge }}">
                                    {{ $log->channel }}
                                </span>
                            </td>
                            <td class="py-4 px-6 font-mono text-[11px] text-slate-600 dark:text-slate-400">
                                {{ $log->event_key }}
                            </td>
                            <td class="py-4 px-6">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border {{ $statusBadge }}">
                                    <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $dotColor }}"></span>
                                    {{ ucfirst($log->status) }}
                                </span>
                            </td>
                            <td class="py-4 px-6 max-w-[280px]">
                                @if($log->subject)
                                    <div class="font-bold text-slate-800 dark:text-slate-200 truncate" title="{{ $log->subject }}">
                                        {{ $log->subject }}
                                    </div>
                                @endif
                                <div class="text-slate-600 dark:text-slate-400 truncate text-[11px]" title="{{ $log->content }}">
                                    {{ $log->content }}
                                </div>
                                @if($log->error_message)
                                    <div class="text-[10px] text-rose-600 dark:text-rose-400 truncate mt-0.5 font-mono" title="{{ $log->error_message }}">
                                        Error: {{ $log->error_message }}
                                    </div>
                                @endif
                            </td>
                            <td class="py-4 px-6 font-mono text-center">
                                <span class="px-2 py-0.5 rounded-full {{ $log->retry_count > 0 ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400 font-bold' : 'text-slate-400' }}">
                                    {{ $log->retry_count }}
                                </span>
                            </td>
                            <td class="py-4 px-6 text-slate-500 font-mono text-[11px] whitespace-nowrap">
                                <div>{{ $log->created_at?->format('Y-m-d H:i:s') ?? 'N/A' }}</div>
                                <div class="text-[10px] text-slate-400 font-sans">{{ $log->created_at?->diffForHumans() }}</div>
                            </td>
                            <td class="py-4 px-6 text-right">
                                <div class="flex items-center justify-end space-x-2">
                                    <button 
                                        type="button" 
                                        @click="inspectedLog = @js($log)"
                                        class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition"
                                        title="Inspect Details"
                                    >
                                        Inspect
                                    </button>

                                    @if($log->isFailed())
                                        <form method="POST" action="{{ route('admin.notifications.logs.retry', $log) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/50 dark:hover:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 transition" title="Retry Dispatch">
                                                Retry
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-slate-400">
                                No notification telemetry logs found matching criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100 dark:border-slate-800">
            <x-pagination :paginator="$logs" />
        </div>
    </div>

    <!-- Inspector Modal (Alpine.js) -->
    <div 
        x-show="inspectedLog !== null" 
        x-cloak 
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="modal-title" 
        role="dialog" 
        aria-modal="true"
    >
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="inspectedLog !== null" @click="inspectedLog = null" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

            <div 
                x-show="inspectedLog !== null" 
                class="inline-block align-bottom bg-white dark:bg-slate-900 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full border border-slate-200 dark:border-slate-800"
            >
                <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Notification Dispatch Telemetry</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Log ID: <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400" x-text="`#${inspectedLog?.id}`"></span>
                        </p>
                    </div>
                    <button type="button" @click="inspectedLog = null" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs">
                    <div class="grid grid-cols-2 gap-3 bg-slate-50 dark:bg-slate-800/40 p-3 rounded-xl border border-slate-200 dark:border-slate-700">
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase font-bold">Recipient</span>
                            <span class="font-bold text-slate-900 dark:text-white" x-text="inspectedLog?.recipient"></span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase font-bold">Channel</span>
                            <span class="font-mono font-bold uppercase text-indigo-600 dark:text-indigo-400" x-text="inspectedLog?.channel"></span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase font-bold">Lifecycle Event</span>
                            <span class="font-mono text-slate-700 dark:text-slate-300" x-text="inspectedLog?.event_key"></span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px] uppercase font-bold">Delivery Status</span>
                            <span class="font-bold uppercase" :class="inspectedLog?.status === 'sent' ? 'text-emerald-600' : 'text-rose-600'" x-text="inspectedLog?.status"></span>
                        </div>
                    </div>

                    <template x-if="inspectedLog?.subject">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Subject Header</label>
                            <div class="p-2.5 bg-slate-100 dark:bg-slate-800 rounded-lg text-slate-900 dark:text-white font-semibold" x-text="inspectedLog?.subject"></div>
                        </div>
                    </template>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-[11px] font-bold text-slate-500 uppercase">Message Content Payload</label>
                            <button 
                                type="button" 
                                @click="navigator.clipboard.writeText(inspectedLog?.content || '')"
                                class="text-[10px] text-indigo-600 dark:text-indigo-400 hover:underline"
                            >
                                Copy Content
                            </button>
                        </div>
                        <pre class="p-3 bg-slate-950 text-slate-200 rounded-xl font-mono text-[11px] overflow-x-auto max-h-48 whitespace-pre-wrap" x-text="inspectedLog?.content"></pre>
                    </div>

                    <template x-if="inspectedLog?.error_message">
                        <div class="p-3 bg-rose-50 dark:bg-rose-950/40 rounded-xl border border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-300 space-y-1">
                            <div class="font-bold flex items-center space-x-1">
                                <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Gateway Transmission Error</span>
                            </div>
                            <div class="font-mono text-[11px] break-words" x-text="inspectedLog?.error_message"></div>
                        </div>
                    </template>
                </div>

                <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-800 flex justify-end">
                    <button type="button" @click="inspectedLog = null" class="px-4 py-2 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
