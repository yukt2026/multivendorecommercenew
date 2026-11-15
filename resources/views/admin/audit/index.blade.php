@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'System Audit Logs'])

@section('content')
<div class="space-y-6" x-data="{ expanded: null, purgeOpen: false }">

    <!-- Page Header -->
    <x-page-header 
        title="Security & Operational Audit Trail" 
        subtitle="Immutable ledger recording administrator actions, system mutations, state transitions, and security events."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Audit Logs' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                {{ number_format($metrics['total_logs']) }} Events
            </span>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center space-x-2">
                @if(auth()->user()->hasRole('super_admin'))
                <button 
                    type="button" 
                    @click="purgeOpen = true"
                    class="px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:hover:bg-rose-900/60 dark:text-rose-400 border border-rose-200 dark:border-rose-800 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition"
                    title="Purge Historical Logs"
                >
                    <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    <span>Retention Purge</span>
                </button>
                @endif

                <a 
                    href="{{ route('admin.audit-logs.export', request()->query()) }}" 
                    class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition"
                >
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Export Audit CSV</span>
                </a>
            </div>
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
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Staff &amp; Officer Actions</div>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ number_format($metrics['admin_actions']) }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Automated System Tasks</div>
            <div class="text-2xl font-bold text-slate-700 dark:text-slate-300 mt-1">{{ number_format($metrics['system_events']) }}</div>
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
                    placeholder="Search descriptions, actors, emails, subjects..." 
                    class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white pl-8 pr-3 py-2 focus:ring-1 focus:ring-indigo-500"
                >
                <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            <select name="log_name" class="text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white py-2 px-3">
                <option value="all">All Modules</option>
                @foreach($logNames as $ln)
                    <option value="{{ $ln }}" {{ request('log_name') === $ln ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $ln)) }}</option>
                @endforeach
            </select>

            <select name="event" class="text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white py-2 px-3">
                <option value="all">All Event Types</option>
                @foreach($events as $ev)
                    <option value="{{ $ev }}" {{ request('event') === $ev ? 'selected' : '' }}>{{ ucfirst($ev) }}</option>
                @endforeach
            </select>

            <input 
                type="date" 
                name="date" 
                value="{{ request('date') }}" 
                title="Filter by Exact Date"
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
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Timestamp</th>
                        <th class="px-6 py-3.5">Actor (Causer)</th>
                        <th class="px-6 py-3.5">Module / Category</th>
                        <th class="px-6 py-3.5">Subject</th>
                        <th class="px-6 py-3.5">Event</th>
                        <th class="px-6 py-3.5">Action Description</th>
                        <th class="px-6 py-3.5 text-right">Properties</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($logs as $log)
                    @php
                        $modColor = match($log->log_name) {
                            'settings' => 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/60 dark:text-purple-400',
                            'staff', 'role' => 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/60 dark:text-indigo-400',
                            'payment', 'settlement' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-400',
                            'store' => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/60 dark:text-blue-400',
                            'support' => 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/60 dark:text-sky-400',
                            'auth' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-400',
                            'audit' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/60 dark:text-rose-400',
                            default => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300',
                        };

                        $eventColor = match($log->event) {
                            'created' => 'text-emerald-600 dark:text-emerald-400',
                            'updated' => 'text-indigo-600 dark:text-indigo-400',
                            'deleted' => 'text-rose-600 dark:text-rose-400',
                            default => 'text-slate-600 dark:text-slate-400',
                        };
                    @endphp
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        <td class="px-6 py-4 text-slate-500 font-mono text-[11px] whitespace-nowrap">
                            <div>{{ $log->created_at?->format('Y-m-d H:i:s') ?? 'N/A' }}</div>
                            <div class="text-[10px] text-slate-400 font-sans">{{ $log->created_at?->diffForHumans() }}</div>
                        </td>
                        <td class="px-6 py-4 font-sans">
                            @if($log->causer)
                                <div class="flex items-center space-x-2">
                                    <div class="w-6 h-6 rounded-full bg-indigo-100 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-400 font-bold flex items-center justify-center text-[10px]">
                                        {{ strtoupper(substr($log->causer->name ?? 'A', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900 dark:text-white text-xs">{{ $log->causer->name }}</div>
                                        <div class="text-[10px] text-slate-400 font-mono">{{ $log->causer->email }}</div>
                                    </div>
                                </div>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400 mr-1.5"></span>
                                    System Automated
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border {{ $modColor }}">
                                {{ $log->log_name ?: 'system' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 font-sans">
                            @if($log->subject_type)
                                <div class="text-xs font-semibold text-slate-800 dark:text-slate-200">
                                    {{ class_basename($log->subject_type) }}
                                </div>
                                <div class="text-[10px] text-slate-400 font-mono">
                                    ID: #{{ $log->subject_id }}
                                </div>
                            @else
                                <span class="text-slate-400 text-[10px] italic">Global / None</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 font-mono">
                            <span class="text-[10px] font-bold uppercase {{ $eventColor }}">
                                {{ $log->event ?: 'action' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 font-sans text-xs text-slate-800 dark:text-slate-200 max-w-md">
                            {{ $log->description }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            @if($log->properties && $log->properties->isNotEmpty())
                                <button 
                                    type="button"
                                    @click="expanded = expanded === {{ $log->id }} ? null : {{ $log->id }}"
                                    class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded text-[10px] font-mono transition"
                                >
                                    <span x-text="expanded === {{ $log->id }} ? 'Hide JSON' : 'Inspect JSON'"></span>
                                </button>
                            @else
                                <span class="text-slate-400 text-[10px] font-mono">—</span>
                            @endif
                        </td>
                    </tr>
                    @if($log->properties && $log->properties->isNotEmpty())
                    <tr x-show="expanded === {{ $log->id }}" x-cloak class="bg-slate-50/80 dark:bg-slate-800/60">
                        <td colspan="7" class="px-6 py-4">
                            <div class="bg-slate-900 rounded-xl p-4 text-xs font-mono border border-slate-800 text-slate-200 space-y-2">
                                <div class="flex items-center justify-between text-slate-400 pb-2 border-b border-slate-800 text-[11px]">
                                    <span>Context Properties &bull; Event #{{ $log->id }}</span>
                                    <button 
                                        type="button" 
                                        @click="navigator.clipboard.writeText(JSON.stringify(@js($log->properties), null, 2))"
                                        class="text-indigo-400 hover:text-indigo-300 font-sans text-xs transition"
                                    >
                                        Copy JSON
                                    </button>
                                </div>
                                <pre class="text-[11px] text-emerald-400 overflow-x-auto max-h-64">{{ json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                        </td>
                    </tr>
                    @endif
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400 font-sans">
                            No audit log records found matching criteria.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-200 dark:border-slate-800">
            <x-pagination :paginator="$logs" />
        </div>
    </div>

    <!-- Purge Retention Modal (Super Admin Only) -->
    @if(auth()->user()->hasRole('super_admin'))
    <div 
        x-show="purgeOpen" 
        x-cloak 
        class="fixed inset-0 z-50 overflow-y-auto" 
        aria-labelledby="modal-title" 
        role="dialog" 
        aria-modal="true"
    >
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="purgeOpen" @click="purgeOpen = false" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

            <div 
                x-show="purgeOpen" 
                class="inline-block align-bottom bg-white dark:bg-slate-900 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border border-slate-200 dark:border-slate-800"
            >
                <form action="{{ route('admin.audit-logs.purge') }}" method="POST">
                    @csrf
                    <div class="p-6 space-y-4">
                        <div class="w-12 h-12 rounded-full bg-rose-100 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center mx-auto">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>

                        <div class="text-center">
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">Purge Historical Audit Trail</h3>
                            <p class="text-xs text-slate-500 mt-1">
                                Permanently delete historical audit log records older than the selected retention window to optimize database storage.
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Retention Window *</label>
                            <select name="days" required class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5">
                                <option value="365">Older than 365 Days (1 Year)</option>
                                <option value="180">Older than 180 Days (6 Months)</option>
                                <option value="90">Older than 90 Days (3 Months)</option>
                                <option value="60">Older than 60 Days (2 Months)</option>
                                <option value="30">Older than 30 Days (1 Month)</option>
                            </select>
                        </div>

                        <div class="p-3 bg-rose-50 dark:bg-rose-950/40 rounded-lg border border-rose-200 dark:border-rose-900 text-[11px] text-rose-700 dark:text-rose-300 space-y-1">
                            <div class="font-bold">Caution: Immutable Action</div>
                            <div>Purged audit events cannot be recovered. A new audit event will record this maintenance task with your user identity and timestamp.</div>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-800 flex justify-end space-x-2">
                        <button type="button" @click="purgeOpen = false" class="px-4 py-2 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white rounded-lg text-xs font-semibold shadow-sm transition">
                            Confirm Purge
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

</div>
@endsection
