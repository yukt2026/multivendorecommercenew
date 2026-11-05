@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Notification Templates Management'])

@section('content')
<div class="space-y-6" x-data="{ editingTemplate: null }">

    <!-- Page Header -->
    <x-page-header 
        title="Notification Templates" 
        subtitle="Configure dynamic content, message copy, and placeholder variables across Email, SMS, WhatsApp, and Push."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Notification Templates' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                {{ $templates->total() }} Templates
            </span>
        </x-slot>
    </x-page-header>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
        <form method="GET" action="{{ route('admin.notifications.templates.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-center">
            <div>
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Filter by Channel</label>
                <select name="channel" onchange="this.form.submit()" class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-indigo-500">
                    <option value="all" {{ $selectedChannel === 'all' ? 'selected' : '' }}>All Channels</option>
                    <option value="mail" {{ $selectedChannel === 'mail' ? 'selected' : '' }}>Email (Mail)</option>
                    <option value="sms" {{ $selectedChannel === 'sms' ? 'selected' : '' }}>SMS</option>
                    <option value="whatsapp" {{ $selectedChannel === 'whatsapp' ? 'selected' : '' }}>WhatsApp</option>
                    <option value="push" {{ $selectedChannel === 'push' ? 'selected' : '' }}>Push Notification</option>
                    <option value="database" {{ $selectedChannel === 'database' ? 'selected' : '' }}>In-App Database</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Filter by Lifecycle Event</label>
                <select name="event" onchange="this.form.submit()" class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-indigo-500">
                    <option value="all" {{ $selectedEvent === 'all' ? 'selected' : '' }}>All Events</option>
                    <option value="otp" {{ $selectedEvent === 'otp' ? 'selected' : '' }}>OTP Verification</option>
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
                    <option value="promotional" {{ $selectedEvent === 'promotional' ? 'selected' : '' }}>Promotional Announcement</option>
                </select>
            </div>

            <div class="flex items-end space-x-2 pt-5">
                <a href="{{ route('admin.notifications.templates.index') }}" class="px-4 py-2 text-xs font-semibold rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition">
                    Reset Filters
                </a>
            </div>
        </form>
    </div>

    <!-- Templates Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 font-semibold uppercase tracking-wider bg-slate-50/50 dark:bg-slate-950/40">
                        <th class="py-3.5 px-4">Event Key</th>
                        <th class="py-3.5 px-4">Channel</th>
                        <th class="py-3.5 px-4">Subject / Title</th>
                        <th class="py-3.5 px-4">Message Template Body</th>
                        <th class="py-3.5 px-4">Available Variables</th>
                        <th class="py-3.5 px-4">Status</th>
                        <th class="py-3.5 px-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($templates as $tpl)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-4 px-4 font-mono font-bold text-slate-900 dark:text-white">
                                {{ $tpl->event_key }}
                            </td>
                            <td class="py-4 px-4">
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold 
                                    @if($tpl->channel === 'mail') bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-400
                                    @elseif($tpl->channel === 'sms') bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400
                                    @elseif($tpl->channel === 'whatsapp') bg-green-100 text-green-800 dark:bg-green-950/60 dark:text-green-400
                                    @elseif($tpl->channel === 'push') bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-400
                                    @else bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400
                                    @endif">
                                    {{ strtoupper($tpl->channel) }}
                                </span>
                            </td>
                            <td class="py-4 px-4 font-semibold text-slate-700 dark:text-slate-300 max-w-[200px] truncate">
                                {{ $tpl->subject ?? '-' }}
                            </td>
                            <td class="py-4 px-4 text-slate-600 dark:text-slate-400 max-w-[280px] truncate" title="{{ $tpl->body }}">
                                {{ $tpl->body }}
                            </td>
                            <td class="py-4 px-4">
                                <div class="flex flex-wrap gap-1 max-w-[220px]">
                                    @if(is_array($tpl->supported_variables))
                                        @foreach($tpl->supported_variables as $v)
                                            <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-[10px] font-mono text-slate-600 dark:text-slate-400">
                                                {{ '{' . $v . '}' }}
                                            </span>
                                        @endforeach
                                    @endif
                                </div>
                            </td>
                            <td class="py-4 px-4">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $tpl->is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-400' }}">
                                    {{ $tpl->is_active ? 'Active' : 'Disabled' }}
                                </span>
                            </td>
                            <td class="py-4 px-4 text-right">
                                <button 
                                    type="button" 
                                    @click="editingTemplate = {{ json_encode($tpl) }}"
                                    class="px-3 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/50 dark:hover:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 font-semibold transition"
                                >
                                    Edit
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                No templates match the selected filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100 dark:border-slate-800">
            {{ $templates->links() }}
        </div>
    </div>

    <!-- Edit Modal -->
    <div 
        x-show="editingTemplate !== null" 
        class="fixed inset-0 z-50 overflow-y-auto"
        style="display: none;"
    >
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="editingTemplate = null"></div>

        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-2xl bg-white dark:bg-slate-900 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-800 p-6 space-y-5">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Edit Notification Template</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Event: <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400" x-text="editingTemplate?.event_key"></span> | Channel: <span class="font-bold uppercase text-slate-700 dark:text-slate-300" x-text="editingTemplate?.channel"></span>
                        </p>
                    </div>
                    <button @click="editingTemplate = null" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form :action="`/admin/notifications/templates/${editingTemplate?.id}`" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <template x-if="editingTemplate?.channel === 'mail' || editingTemplate?.channel === 'push' || editingTemplate?.channel === 'database'">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Subject / Title</label>
                            <input 
                                type="text" 
                                name="subject" 
                                :value="editingTemplate?.subject" 
                                class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-indigo-500"
                            >
                        </div>
                    </template>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Message Body</label>
                        <textarea 
                            name="body" 
                            rows="5" 
                            x-text="editingTemplate?.body"
                            class="w-full text-xs font-mono rounded-xl border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white focus:ring-indigo-500"
                            required
                        ></textarea>
                    </div>

                    <!-- Available Placeholders Reference -->
                    <div class="bg-slate-50 dark:bg-slate-950/60 rounded-xl p-3 border border-slate-100 dark:border-slate-800">
                        <div class="text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1.5">Supported Dynamic Variables:</div>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="v in editingTemplate?.supported_variables" :key="v">
                                <span class="px-2 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/50 text-[11px] font-mono font-semibold text-indigo-700 dark:text-indigo-300" x-text="`{${v}}`"></span>
                            </template>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2 pt-2">
                        <input 
                            type="checkbox" 
                            id="tpl_is_active" 
                            name="is_active" 
                            value="1" 
                            :checked="editingTemplate?.is_active"
                            class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-700"
                        >
                        <label for="tpl_is_active" class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Active (Disable to pause dispatches for this template)
                        </label>
                    </div>

                    <div class="flex justify-end space-x-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="editingTemplate = null" class="px-4 py-2 text-xs font-semibold rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 text-xs font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white shadow-sm transition">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
