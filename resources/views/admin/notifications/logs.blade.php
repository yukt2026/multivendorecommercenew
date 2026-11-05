@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Notification Delivery Logs'])

@section('content')
<div class="space-y-6">

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
    </x-page-header>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">Total Logged</div>
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
            <div class="text-xs font-semibold text-amber-600 dark:text-amber-400">Retried</div>
            <div class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ number_format($stats['retried']) }}</div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
        <form method="GET" action="{{ route('admin.notifications.logs.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 items-end">
            <div>
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Channel</label>
                <select name="channel" class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-indigo-500">
                    <option value="all" {{ $selectedChannel === 'all' ? 'selected' : '' }}>All Channels</option>
                    <option value="mail" {{ $selectedChannel === 'mail' ? 'selected' : '' }}>Mail</option>
                    <option value="sms" {{ $selectedChannel === 'sms' ? 'selected' : '' }}>SMS</option>
                    <option value="whatsapp" {{ $selectedChannel === 'whatsapp' ? 'selected' : '' }}>WhatsApp</option>
                    <option value="push" {{ $selectedChannel === 'push' ? 'selected' : '' }}>Push</option>
                    <option value="database" {{ $selectedChannel === 'database' ? 'selected' : '' }}>Database</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Status</label>
                <select name="status" class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-indigo-500">
                    <option value="all" {{ $selectedStatus === 'all' ? 'selected' : '' }}>All Statuses</option>
                    <option value="sent" {{ $selectedStatus === 'sent' ? 'selected' : '' }}>Sent</option>
                    <option value="failed" {{ $selectedStatus === 'failed' ? 'selected' : '' }}>Failed</option>
                    <option value="retried" {{ $selectedStatus === 'retried' ? 'selected' : '' }}>Retried</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Lifecycle Event</label>
                <select name="event" class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-indigo-500">
                    <option value="all" {{ $selectedEvent === 'all' ? 'selected' : '' }}>All Events</option>
                    <option value="otp" {{ $selectedEvent === 'otp' ? 'selected' : '' }}>OTP</option>
                    <option value="order_confirmed" {{ $selectedEvent === 'order_confirmed' ? 'selected' : '' }}>Order Confirmed</option>
                    <option value="payment_confirmed" {{ $selectedEvent === 'payment_confirmed' ? 'selected' : '' }}>Payment Confirmed</option>
                    <option value="order_packing" {{ $selectedEvent === 'order_packing' ? 'selected' : '' }}>Order Packing</option>
                    <option value="shipment_update" {{ $selectedEvent === 'shipment_update' ? 'selected' : '' }}>Shipment Update</option>
                    <option value="out_for_delivery" {{ $selectedEvent === 'out_for_delivery' ? 'selected' : '' }}>Out For Delivery</option>
                    <option value="order_delivered" {{ $selectedEvent === 'order_delivered' ? 'selected' : '' }}>Order Delivered</option>
                    <option value="return_refund" {{ $selectedEvent === 'return_refund' ? 'selected' : '' }}>Return & Refund</option>
                    <option value="seller_new_order" {{ $selectedEvent === 'seller_new_order' ? 'selected' : '' }}>Seller New Order</option>
                    <option value="low_stock_alert" {{ $selectedEvent === 'low_stock_alert' ? 'selected' : '' }}>Low Stock Alert</option>
                    <option value="settlement_paid" {{ $selectedEvent === 'settlement_paid' ? 'selected' : '' }}>Settlement Paid</option>
                    <option value="promotional" {{ $selectedEvent === 'promotional' ? 'selected' : '' }}>Promotional</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Search Recipient/Content</label>
                <input 
                    type="text" 
                    name="search" 
                    value="{{ $search }}" 
                    placeholder="Email, phone, or text..."
                    class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-indigo-500"
                >
            </div>

            <div class="flex items-center space-x-2">
                <button type="submit" class="w-full px-4 py-2 text-xs font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white shadow-sm transition">
                    Filter
                </button>
                <a href="{{ route('admin.notifications.logs.index') }}" class="px-3 py-2 text-xs font-semibold rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 font-semibold uppercase tracking-wider bg-slate-50/50 dark:bg-slate-950/40">
                        <th class="py-3.5 px-4">#ID</th>
                        <th class="py-3.5 px-4">Recipient</th>
                        <th class="py-3.5 px-4">Channel</th>
                        <th class="py-3.5 px-4">Event</th>
                        <th class="py-3.5 px-4">Status</th>
                        <th class="py-3.5 px-4">Content / Error</th>
                        <th class="py-3.5 px-4">Retries</th>
                        <th class="py-3.5 px-4">Timestamp</th>
                        <th class="py-3.5 px-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($logs as $log)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-3.5 px-4 font-mono font-bold text-slate-500">
                                #{{ $log->id }}
                            </td>
                            <td class="py-3.5 px-4 font-semibold text-slate-900 dark:text-white">
                                {{ $log->recipient }}
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase
                                    @if($log->channel === 'mail') bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-400
                                    @elseif($log->channel === 'sms') bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400
                                    @elseif($log->channel === 'whatsapp') bg-green-100 text-green-800 dark:bg-green-950/60 dark:text-green-400
                                    @elseif($log->channel === 'push') bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-400
                                    @else bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400
                                    @endif">
                                    {{ $log->channel }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 font-mono text-[11px] text-slate-600 dark:text-slate-400">
                                {{ $log->event_key }}
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold 
                                    @if($log->status === 'sent') bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400
                                    @elseif($log->status === 'retried') bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400
                                    @else bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-400
                                    @endif">
                                    {{ ucfirst($log->status) }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 max-w-[280px]">
                                <div class="text-slate-700 dark:text-slate-300 truncate" title="{{ $log->content }}">
                                    {{ $log->content }}
                                </div>
                                @if($log->error_message)
                                    <div class="text-[11px] text-rose-500 truncate mt-0.5" title="{{ $log->error_message }}">
                                        ⚠️ {{ $log->error_message }}
                                    </div>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 font-mono text-center">
                                {{ $log->retry_count }}
                            </td>
                            <td class="py-3.5 px-4 text-slate-400 text-[11px] whitespace-nowrap">
                                {{ $log->created_at->format('M d, H:i:s') }}
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                @if($log->isFailed())
                                    <form method="POST" action="{{ route('admin.notifications.logs.retry', $log) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/50 dark:hover:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 transition">
                                            Retry
                                        </button>
                                    </form>
                                @else
                                    <span class="text-slate-300 dark:text-slate-600">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-slate-400">
                                No notification logs found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100 dark:border-slate-800">
            {{ $logs->links() }}
        </div>
    </div>

</div>
@endsection
