@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Ticket #' . $ticket->ticket_number])

@section('content')
<div class="space-y-6 max-w-6xl mx-auto">

    <!-- Page Header -->
    <x-page-header 
        :title="'Ticket #' . $ticket->ticket_number . ': ' . $ticket->subject" 
        subtitle="Customer resolution thread, escalation logs, and internal staff notes."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Support Tickets' => route('admin.support.index'), '#' . $ticket->ticket_number => '#']"
    >
        <x-slot name="badge">
            @php
                $stVal = is_object($ticket->status) ? $ticket->status->value : $ticket->status;
            @endphp
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border
                {{ $stVal === 'open' ? 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/60 dark:text-rose-400' : '' }}
                {{ $stVal === 'in_progress' ? 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-400' : '' }}
                {{ $stVal === 'resolved' ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-400' : '' }}
                {{ $stVal === 'closed' ? 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-400' : '' }}
            ">
                {{ ucfirst(str_replace('_', ' ', $stVal)) }}
            </span>
        </x-slot>

        <x-slot name="actions">
            <a 
                href="{{ route('admin.support.index') }}" 
                class="px-4 py-2 border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold transition"
            >
                Back to Support Desk
            </a>
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Main Messages Thread -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Message History Container -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-6">
                <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Conversation Timeline</h3>
                </div>

                <div class="space-y-4">
                    @forelse($ticket->messages as $msg)
                        <div class="p-4 rounded-xl border {{ $msg->is_internal ? 'bg-amber-50/60 dark:bg-amber-950/20 border-amber-200 dark:border-amber-900/40' : ($msg->user_id === auth()->id() ? 'bg-indigo-50/40 dark:bg-indigo-950/20 border-indigo-100 dark:border-indigo-900/30' : 'bg-slate-50 dark:bg-slate-800/40 border-slate-200 dark:border-slate-700') }}">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center space-x-2">
                                    <span class="font-bold text-xs text-slate-900 dark:text-white">{{ $msg->user?->name ?? 'User' }}</span>
                                    @if($msg->user?->hasRole(['super_admin', 'admin', 'support_admin']))
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-400 uppercase">Staff</span>
                                    @endif
                                    @if($msg->is_internal)
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-200 text-amber-900 dark:bg-amber-900 dark:text-amber-200 uppercase">Internal Note</span>
                                    @endif
                                </div>
                                <span class="text-[10px] text-slate-400">{{ $msg->created_at->format('d M Y, h:i A') }}</span>
                            </div>
                            <div class="text-xs text-slate-700 dark:text-slate-300 whitespace-pre-wrap leading-relaxed">
                                {{ $msg->message }}
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-xs text-slate-400">
                            No messages posted yet.
                        </div>
                    @endforelse
                </div>

                <!-- Reply Composer -->
                <form method="POST" action="{{ route('admin.support.reply', $ticket) }}" class="space-y-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Post Response or Note</label>
                        <textarea 
                            name="message" 
                            rows="4" 
                            required 
                            placeholder="Type your reply to the customer or internal staff note..." 
                            class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-3 focus:ring-1 focus:ring-indigo-500"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" name="is_internal" value="1" class="rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                            <span class="text-xs font-medium text-amber-700 dark:text-amber-400">Post as private internal note (not visible to customer)</span>
                        </label>

                        <button 
                            type="submit" 
                            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow-sm transition"
                        >
                            Send Reply
                        </button>
                    </div>
                </form>
            </div>

        </div>

        <!-- Sidebar / Meta Details & Status Control -->
        <div class="space-y-6">

            <!-- Status & Assignment Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Ticket Governance</h3>

                <!-- Status Update -->
                <form method="POST" action="{{ route('admin.support.status', $ticket) }}" class="space-y-2">
                    @csrf
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Ticket Status</label>
                    <div class="flex space-x-2">
                        <select name="status" class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2">
                            <option value="open" {{ $stVal === 'open' ? 'selected' : '' }}>Open</option>
                            <option value="in_progress" {{ $stVal === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                            <option value="resolved" {{ $stVal === 'resolved' ? 'selected' : '' }}>Resolved</option>
                            <option value="closed" {{ $stVal === 'closed' ? 'selected' : '' }}>Closed</option>
                        </select>
                        <button type="submit" class="px-3 py-2 bg-slate-900 hover:bg-slate-800 text-white dark:bg-slate-100 dark:hover:bg-white dark:text-slate-900 rounded-lg text-xs font-semibold transition">
                            Update
                        </button>
                    </div>
                </form>

                <!-- Assignee -->
                <form method="POST" action="{{ route('admin.support.assign', $ticket) }}" class="space-y-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    @csrf
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Assigned Agent</label>
                    <div class="flex space-x-2">
                        <select name="assigned_to" class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2">
                            <option value="">Unassigned</option>
                            @foreach($staff as $st)
                                <option value="{{ $st->id }}" {{ $ticket->assigned_to == $st->id ? 'selected' : '' }}>
                                    {{ $st->name }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="px-3 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold transition">
                            Assign
                        </button>
                    </div>
                </form>
            </div>

            <!-- Customer & Order Information -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Customer &amp; Context</h3>

                <div>
                    <div class="text-[10px] text-slate-400 uppercase">Customer</div>
                    <div class="text-xs font-bold text-slate-900 dark:text-white mt-0.5">{{ $ticket->user?->name ?? 'Guest User' }}</div>
                    <div class="text-xs text-slate-500">{{ $ticket->user?->email }}</div>
                    @if($ticket->user?->phone)
                        <div class="text-xs text-slate-500">{{ $ticket->user->phone }}</div>
                    @endif
                </div>

                @if($ticket->order)
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800">
                    <div class="text-[10px] text-slate-400 uppercase">Referenced Order</div>
                    <a href="{{ route('admin.orders.show', $ticket->order) }}" class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline block mt-0.5">
                        {{ $ticket->order->order_number }}
                    </a>
                    <div class="text-xs text-slate-500 mt-0.5">
                        Total: {{ format_currency((float)$ticket->order->total) }} &bull; {{ ucfirst($ticket->order->status) }}
                    </div>
                </div>
                @endif

                @if($ticket->seller)
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800">
                    <div class="text-[10px] text-slate-400 uppercase">Seller / Merchant</div>
                    <div class="text-xs font-bold text-slate-900 dark:text-white mt-0.5">{{ $ticket->seller->business_name }}</div>
                </div>
                @endif

                <div class="pt-3 border-t border-slate-100 dark:border-slate-800">
                    <div class="text-[10px] text-slate-400 uppercase">Ticket Priority</div>
                    @php $prVal = is_object($ticket->priority) ? $ticket->priority->value : $ticket->priority; @endphp
                    <div class="text-xs font-bold mt-0.5 uppercase {{ $prVal === 'urgent' ? 'text-rose-600' : 'text-slate-700 dark:text-slate-300' }}">
                        {{ $prVal }} Priority
                    </div>
                </div>
            </div>

        </div>

    </div>

</div>
@endsection
