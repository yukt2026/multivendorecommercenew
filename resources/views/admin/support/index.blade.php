@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Support Desk'])

@section('content')
<div class="space-y-6">

    <!-- Page Header -->
    <x-page-header 
        title="Customer &amp; Seller Support Tickets" 
        subtitle="Manage customer escalations, seller disputes, delivery claims, and resolution threads."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Support' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                {{ $metrics['open_tickets'] }} Pending
            </span>
        </x-slot>

        <x-slot name="actions">
            <a 
                href="{{ route('admin.support.export') }}" 
                class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition"
            >
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Export Tickets CSV</span>
            </a>
        </x-slot>
    </x-page-header>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Total Inquiries</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $metrics['total_tickets'] }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-rose-600 dark:text-rose-400">Open Tickets</div>
            <div class="text-2xl font-bold text-rose-600 dark:text-rose-400 mt-1">{{ $metrics['open_tickets'] }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-amber-600 dark:text-amber-400">In Progress</div>
            <div class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ $metrics['in_progress'] }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Resolved</div>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ $metrics['resolved'] }}</div>
        </div>
    </div>

    <!-- Filters Strip -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm flex flex-col md:flex-row gap-4 justify-between items-center">
        <form method="GET" action="{{ route('admin.support.index') }}" class="flex flex-wrap items-center gap-2 w-full">
            <div class="relative flex-1 min-w-[200px]">
                <input 
                    type="text" 
                    name="search" 
                    value="{{ request('search') }}" 
                    placeholder="Search ticket #, customer, subject..." 
                    class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white pl-8 pr-3 py-2 focus:ring-1 focus:ring-indigo-500"
                >
                <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            <select name="status" class="text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white py-2 px-3">
                <option value="all">All Statuses</option>
                <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Open</option>
                <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
            </select>

            <select name="priority" class="text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white py-2 px-3">
                <option value="all">All Priorities</option>
                <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>Low</option>
                <option value="medium" {{ request('priority') === 'medium' ? 'selected' : '' }}>Medium</option>
                <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>High</option>
                <option value="urgent" {{ request('priority') === 'urgent' ? 'selected' : '' }}>Urgent</option>
            </select>

            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow-sm transition">
                Filter
            </button>
            <a href="{{ route('admin.support.index') }}" class="px-3 py-2 text-xs text-slate-500 hover:text-slate-800 dark:hover:text-slate-200">
                Reset
            </a>
        </form>
    </div>

    <!-- Tickets Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Ticket # &amp; Subject</th>
                        <th class="px-6 py-3.5">Customer / Initiator</th>
                        <th class="px-6 py-3.5">Linked Order</th>
                        <th class="px-6 py-3.5">Priority</th>
                        <th class="px-6 py-3.5">Assigned Agent</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($tickets as $t)
                    @php
                        $stVal = is_object($t->status) ? $t->status->value : $t->status;
                        $prVal = is_object($t->priority) ? $t->priority->value : $t->priority;
                    @endphp
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        <td class="px-6 py-4">
                            <div class="font-mono font-bold text-indigo-600 dark:text-indigo-400">#{{ $t->ticket_number }}</div>
                            <div class="font-bold text-slate-900 dark:text-white mt-0.5 max-w-sm truncate">{{ $t->subject }}</div>
                            <div class="text-[10px] text-slate-400">{{ $t->created_at->diffForHumans() }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-slate-900 dark:text-white">{{ $t->user?->name ?? 'Guest User' }}</div>
                            <div class="text-[10px] text-slate-400">{{ $t->user?->email }}</div>
                            @if($t->seller)
                                <div class="text-[10px] text-indigo-500 font-medium mt-0.5">Seller: {{ $t->seller->business_name }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($t->order)
                                <a href="{{ route('admin.orders.show', $t->order) }}" class="font-mono text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                                    {{ $t->order->order_number }}
                                </a>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider
                                {{ $prVal === 'urgent' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-400' : '' }}
                                {{ $prVal === 'high' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400' : '' }}
                                {{ $prVal === 'medium' ? 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-400' : '' }}
                                {{ $prVal === 'low' ? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400' : '' }}
                            ">
                                {{ $prVal }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <form method="POST" action="{{ route('admin.support.assign', $t) }}">
                                @csrf
                                <select 
                                    name="assigned_to" 
                                    onchange="this.form.submit()" 
                                    class="text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white py-1 px-2"
                                >
                                    <option value="">Unassigned</option>
                                    @foreach($staff as $st)
                                        <option value="{{ $st->id }}" {{ $t->assigned_to == $st->id ? 'selected' : '' }}>
                                            {{ $st->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold border
                                {{ $stVal === 'open' ? 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/60 dark:text-rose-400 dark:border-rose-800' : '' }}
                                {{ $stVal === 'in_progress' ? 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-400 dark:border-amber-800' : '' }}
                                {{ $stVal === 'resolved' ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-400 dark:border-emerald-800' : '' }}
                                {{ $stVal === 'closed' ? 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700' : '' }}
                            ">
                                {{ ucfirst(str_replace('_', ' ', $stVal)) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a 
                                href="{{ route('admin.support.show', $t) }}" 
                                class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 dark:bg-indigo-950/60 dark:hover:bg-indigo-900/60 dark:text-indigo-400 rounded-lg font-semibold text-xs transition"
                            >
                                Open Thread &rarr;
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                            No support tickets found matching criteria.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-200 dark:border-slate-800">
            {{ $tickets->links() }}
        </div>
    </div>

</div>
@endsection
