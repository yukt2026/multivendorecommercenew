@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'System Audit Logs'])

@section('content')
<div class="space-y-6">

    <!-- Page Header -->
    <x-page-header 
        title="Security &amp; Operational Audit Trail" 
        subtitle="Immutable ledger recording administrator actions, system mutations, state transitions, and security events."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Audit Logs' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                {{ $metrics['total_logs'] }} Events
            </span>
        </x-slot>

        <x-slot name="actions">
            <a 
                href="{{ route('admin.audit-logs.export', request()->query()) }}" 
                class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition"
            >
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Export Audit CSV</span>
            </a>
        </x-slot>
    </x-page-header>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-slate-500">All-Time Audit Records</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ number_format($metrics['total_logs']) }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-indigo-600 dark:text-indigo-400">Events Recorded Today</div>
            <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ number_format($metrics['today_logs']) }}</div>
        </div>
    </div>

    <!-- Filters Strip -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
        <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="flex flex-wrap items-center gap-3">
            <div class="relative flex-1 min-w-[220px]">
                <input 
                    type="text" 
                    name="search" 
                    value="{{ request('search') }}" 
                    placeholder="Search action descriptions..." 
                    class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white pl-8 pr-3 py-2 focus:ring-1 focus:ring-indigo-500"
                >
                <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            <select name="log_name" class="text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white py-2 px-3">
                <option value="all">All Modules</option>
                @foreach($logNames as $ln)
                    @if($ln)
                        <option value="{{ $ln }}" {{ request('log_name') === $ln ? 'selected' : '' }}>{{ ucfirst($ln) }}</option>
                    @endif
                @endforeach
            </select>

            <select name="event" class="text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white py-2 px-3">
                <option value="all">All Event Types</option>
                @foreach($events as $ev)
                    @if($ev)
                        <option value="{{ $ev }}" {{ request('event') === $ev ? 'selected' : '' }}>{{ ucfirst($ev) }}</option>
                    @endif
                @endforeach
            </select>

            <input 
                type="date" 
                name="date" 
                value="{{ request('date') }}" 
                class="text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white py-2 px-3"
            >

            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow-sm transition">
                Filter
            </button>
            <a href="{{ route('admin.audit-logs.index') }}" class="px-3 py-2 text-xs text-slate-500 hover:text-slate-800 dark:hover:text-slate-200">
                Reset
            </a>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden" x-data="{ expanded: null }">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Timestamp</th>
                        <th class="px-6 py-3.5">Actor (Causer)</th>
                        <th class="px-6 py-3.5">Module / Category</th>
                        <th class="px-6 py-3.5">Event</th>
                        <th class="px-6 py-3.5">Action Description</th>
                        <th class="px-6 py-3.5 text-right">Properties</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-mono">
                    @forelse($logs as $log)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        <td class="px-6 py-4 text-slate-500 text-[11px] whitespace-nowrap">
                            {{ $log->created_at->format('Y-m-d H:i:s') }}
                        </td>
                        <td class="px-6 py-4 font-sans">
                            @if($log->causer)
                                <div class="font-bold text-slate-900 dark:text-white text-xs">{{ $log->causer->name }}</div>
                                <div class="text-[10px] text-slate-400 font-mono">{{ $log->causer->email }}</div>
                            @else
                                <span class="text-slate-400 text-xs italic font-sans">System Process</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                {{ $log->log_name ?: 'system' }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 uppercase">
                                {{ $log->event ?: 'action' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 font-sans text-xs text-slate-800 dark:text-slate-200 max-w-md">
                            {{ $log->description }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            @if($log->properties && $log->properties->isNotEmpty())
                                <button 
                                    @click="expanded = expanded === {{ $log->id }} ? null : {{ $log->id }}"
                                    class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded text-[10px] font-mono transition"
                                >
                                    <span x-text="expanded === {{ $log->id }} ? 'Hide JSON' : 'Inspect JSON'"></span>
                                </button>
                            @else
                                <span class="text-slate-400 text-[10px]">—</span>
                            @endif
                        </td>
                    </tr>
                    @if($log->properties && $log->properties->isNotEmpty())
                    <tr x-show="expanded === {{ $log->id }}" x-cloak class="bg-slate-50/80 dark:bg-slate-800/60">
                        <td colspan="6" class="px-6 py-3">
                            <pre class="text-[10px] bg-slate-900 text-emerald-400 p-3 rounded-lg overflow-x-auto font-mono max-h-60">{{ json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </td>
                    </tr>
                    @endif
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400 font-sans">
                            No audit log records found for the applied filter.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-200 dark:border-slate-800">
            {{ $logs->links() }}
        </div>
    </div>

</div>
@endsection
