@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Notification Templates Management'])

@section('content')
<div class="space-y-6" x-data="templateManager()">

    <!-- Page Header -->
    <x-page-header 
        title="Notification Templates" 
        subtitle="Configure dynamic content, message copy, and placeholder variables across Email, SMS, WhatsApp, and Push."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Notification Templates' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                {{ number_format($metrics['total_templates']) }} Templates
            </span>
        </x-slot>
    </x-page-header>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-slate-500">Configured Templates</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ number_format($metrics['total_templates']) }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Active Dispatches</div>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ number_format($metrics['active_templates']) }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-blue-600 dark:text-blue-400">Email (Mail) Channels</div>
            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">{{ number_format($metrics['mail_templates']) }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-purple-600 dark:text-purple-400">Instant (SMS/WhatsApp/Push)</div>
            <div class="text-2xl font-bold text-purple-600 dark:text-purple-400 mt-1">{{ number_format($metrics['instant_templates']) }}</div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
        <form method="GET" action="{{ route('admin.notifications.templates.index') }}" class="flex flex-wrap items-center gap-3">
            <div class="relative flex-1 min-w-[220px]">
                <input 
                    type="text" 
                    name="search" 
                    value="{{ $search }}" 
                    placeholder="Search event, subject, or message text..." 
                    class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white pl-8 pr-3 py-2 focus:ring-1 focus:ring-indigo-500"
                >
                <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            <select name="channel" class="text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white py-2 px-3">
                <option value="all" {{ $selectedChannel === 'all' ? 'selected' : '' }}>All Channels</option>
                <option value="mail" {{ $selectedChannel === 'mail' ? 'selected' : '' }}>Email (Mail)</option>
                <option value="sms" {{ $selectedChannel === 'sms' ? 'selected' : '' }}>SMS</option>
                <option value="whatsapp" {{ $selectedChannel === 'whatsapp' ? 'selected' : '' }}>WhatsApp</option>
                <option value="push" {{ $selectedChannel === 'push' ? 'selected' : '' }}>Push Notification</option>
                <option value="database" {{ $selectedChannel === 'database' ? 'selected' : '' }}>In-App Database</option>
            </select>

            <select name="event" class="text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white py-2 px-3">
                <option value="all" {{ $selectedEvent === 'all' ? 'selected' : '' }}>All Events</option>
                <option value="otp" {{ $selectedEvent === 'otp' ? 'selected' : '' }}>OTP Verification</option>
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
                <option value="promotional" {{ $selectedEvent === 'promotional' ? 'selected' : '' }}>Promotional Announcement</option>
            </select>

            <select name="status" class="text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white py-2 px-3">
                <option value="all" {{ $selectedStatus === 'all' ? 'selected' : '' }}>All Statuses</option>
                <option value="active" {{ $selectedStatus === 'active' ? 'selected' : '' }}>Active Only</option>
                <option value="disabled" {{ $selectedStatus === 'disabled' ? 'selected' : '' }}>Disabled Only</option>
            </select>

            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow-sm transition">
                Filter
            </button>
            <a href="{{ route('admin.notifications.templates.index') }}" class="px-3 py-2 text-xs text-slate-500 hover:text-slate-800 dark:hover:text-slate-200">
                Reset
            </a>
        </form>
    </div>

    <!-- Templates Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 uppercase text-[10px] tracking-wider bg-slate-50 dark:bg-slate-800/60 font-semibold">
                        <th class="py-3.5 px-6">Event Key</th>
                        <th class="py-3.5 px-6">Channel</th>
                        <th class="py-3.5 px-6">Subject / Title</th>
                        <th class="py-3.5 px-6">Message Template Body</th>
                        <th class="py-3.5 px-6">Available Variables</th>
                        <th class="py-3.5 px-6">Status</th>
                        <th class="py-3.5 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($templates as $tpl)
                        @php
                            $channelBadge = match($tpl->channel) {
                                'mail' => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/60 dark:text-blue-400',
                                'sms' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-400',
                                'whatsapp' => 'bg-green-50 text-green-700 border-green-200 dark:bg-green-950/60 dark:text-green-400',
                                'push' => 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/60 dark:text-purple-400',
                                default => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-400',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-4 px-6 font-mono font-bold text-slate-900 dark:text-white">
                                {{ $tpl->event_key }}
                            </td>
                            <td class="py-4 px-6">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border {{ $channelBadge }}">
                                    {{ $tpl->channel }}
                                </span>
                            </td>
                            <td class="py-4 px-6 font-semibold text-slate-800 dark:text-slate-200 max-w-[200px] truncate">
                                {{ $tpl->subject ?? '—' }}
                            </td>
                            <td class="py-4 px-6 text-slate-600 dark:text-slate-400 max-w-[280px] truncate" title="{{ $tpl->body }}">
                                {{ $tpl->body }}
                            </td>
                            <td class="py-4 px-6">
                                <div class="flex flex-wrap gap-1 max-w-[220px]">
                                    @if(!empty($tpl->supported_variables))
                                        @foreach(array_slice($tpl->supported_variables, 0, 3) as $v)
                                            <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-[10px] font-mono text-slate-700 dark:text-slate-300">
                                                {{ '{' . $v . '}' }}
                                            </span>
                                        @endforeach
                                        @if(count($tpl->supported_variables) > 3)
                                            <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-[10px] font-mono text-slate-500">
                                                +{{ count($tpl->supported_variables) - 3 }} more
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-slate-400 text-[10px] font-mono">—</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-4 px-6">
                                <form method="POST" action="{{ route('admin.notifications.templates.toggle', $tpl) }}" class="inline">
                                    @csrf
                                    <button 
                                        type="submit" 
                                        class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border transition cursor-pointer {{ $tpl->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-400 hover:bg-emerald-100' : 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/60 dark:text-rose-400 hover:bg-rose-100' }}"
                                        title="Click to toggle status"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $tpl->is_active ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                                        {{ $tpl->is_active ? 'Active' : 'Disabled' }}
                                    </button>
                                </form>
                            </td>
                            <td class="py-4 px-6 text-right">
                                <button 
                                    type="button" 
                                    @click="openEditModal(@js($tpl))"
                                    class="px-3 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/50 dark:hover:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 font-semibold text-xs transition"
                                >
                                    Edit &amp; Preview
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                No notification templates match the selected filter criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100 dark:border-slate-800">
            <x-pagination :paginator="$templates" />
        </div>
    </div>

    <!-- Edit & Live Preview Modal -->
    <div 
        x-show="editingTemplate !== null" 
        x-cloak 
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="modal-title" 
        role="dialog" 
        aria-modal="true"
    >
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="editingTemplate !== null" @click="closeModal()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

            <div 
                x-show="editingTemplate !== null" 
                class="inline-block align-bottom bg-white dark:bg-slate-900 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-slate-200 dark:border-slate-800"
            >
                <form :action="formUrl" method="POST" class="space-y-0">
                    @csrf
                    <input type="hidden" name="_method" value="PUT">

                    <!-- Header -->
                    <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">Edit Notification Template</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Event: <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400" x-text="form.event_key"></span> &bull; Channel: <span class="font-bold uppercase text-slate-700 dark:text-slate-300" x-text="form.channel"></span>
                            </p>
                        </div>
                        <button type="button" @click="closeModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-6 space-y-4">
                        <template x-if="form.channel === 'mail' || form.channel === 'push' || form.channel === 'database'">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Subject / Title</label>
                                <input 
                                    type="text" 
                                    name="subject" 
                                    x-model="form.subject"
                                    placeholder="Notification subject..."
                                    class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                                >
                            </div>
                        </template>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Message Body Template *</label>
                            <textarea 
                                name="body" 
                                rows="4" 
                                x-model="form.body"
                                class="w-full text-xs font-mono rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                                required
                            ></textarea>
                        </div>

                        <!-- Available Placeholders Reference with Click to Insert -->
                        <div class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-3 border border-slate-200 dark:border-slate-700">
                            <div class="flex items-center justify-between text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-2">
                                <span>Supported Variables (Click to Insert):</span>
                                <span class="text-[10px] text-slate-400 font-sans font-normal">Appends variable tag into message body</span>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="v in availableVariables" :key="v">
                                    <button 
                                        type="button" 
                                        @click="insertVariable(v)" 
                                        class="px-2 py-0.5 rounded bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:hover:bg-indigo-900 text-[10px] font-mono font-semibold text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 transition cursor-pointer"
                                        x-text="`{${v}}`"
                                        title="Click to insert"
                                    ></button>
                                </template>
                            </div>
                        </div>

                        <!-- Live Rendered Preview -->
                        <div class="bg-indigo-50/50 dark:bg-indigo-950/30 rounded-xl p-3 border border-indigo-100 dark:border-indigo-900/60 space-y-1.5">
                            <div class="flex items-center justify-between text-[11px] font-bold text-indigo-700 dark:text-indigo-300">
                                <span class="flex items-center space-x-1">
                                    <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <span>Live Preview (with Sample Customer Context):</span>
                                </span>
                                <span class="text-[10px] font-mono uppercase text-indigo-500" x-text="form.channel"></span>
                            </div>
                            <template x-if="form.subject">
                                <div class="text-xs font-bold text-slate-800 dark:text-slate-200 pb-1 border-b border-indigo-100 dark:border-indigo-900/50" x-text="renderText(form.subject)"></div>
                            </template>
                            <div class="text-xs text-slate-700 dark:text-slate-300 font-sans leading-relaxed whitespace-pre-line" x-text="renderText(form.body)"></div>
                        </div>

                        <div class="flex items-center space-x-2 pt-1">
                            <input 
                                type="checkbox" 
                                id="tpl_is_active" 
                                name="is_active" 
                                value="1" 
                                x-model="form.is_active"
                                class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-700"
                            >
                            <label for="tpl_is_active" class="text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
                                Active Dispatch (Uncheck to pause dispatches for this channel template)
                            </label>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-800 flex justify-end space-x-2">
                        <button type="button" @click="closeModal()" class="px-4 py-2 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow-sm transition">
                            Save Template Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
function templateManager() {
    return {
        editingTemplate: null,
        formUrl: '',
        form: {
            id: null,
            event_key: '',
            channel: '',
            subject: '',
            body: '',
            is_active: true
        },
        availableVariables: [],
        sampleVariables: @js(\App\Models\NotificationTemplate::sampleVariables()),
        openEditModal(tpl) {
            this.editingTemplate = tpl;
            this.formUrl = `/admin/notifications/templates/${tpl.id}`;
            this.form = {
                id: tpl.id,
                event_key: tpl.event_key,
                channel: tpl.channel,
                subject: tpl.subject || '',
                body: tpl.body || '',
                is_active: Boolean(tpl.is_active)
            };
            this.availableVariables = Array.isArray(tpl.supported_variables) ? tpl.supported_variables : [];
        },
        closeModal() {
            this.editingTemplate = null;
        },
        insertVariable(v) {
            this.form.body = (this.form.body ? this.form.body + ' ' : '') + '{' + v + '}';
        },
        renderText(templateStr) {
            if (!templateStr) return '';
            let out = templateStr;
            for (const [key, val] of Object.entries(this.sampleVariables)) {
                out = out.replaceAll('{' + key + '}', val);
            }
            return out;
        }
    }
}
</script>
@endsection
