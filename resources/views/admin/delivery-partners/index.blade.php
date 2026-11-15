@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Delivery Partners Fleet Management'])

@section('content')
<div class="space-y-6" x-data="deliveryPartnerManager()">

    <!-- Page Header & Action Bar -->
    <x-page-header 
        title="Delivery Partners Fleet" 
        subtitle="Manage hyperlocal quick-commerce riders, realtime GPS coordinates, duty availability, KYC verification, and vehicle fleet."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Delivery Fleet' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                ⚡ {{ number_format($metrics['total_riders']) }} Fleet Riders
            </span>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center space-x-2">
                <a 
                    href="{{ route('admin.quick.index') }}" 
                    class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl transition flex items-center space-x-1"
                >
                    <span>&larr; Mission Control</span>
                </a>

                <a 
                    href="{{ route('admin.delivery_partners.settlements') }}" 
                    class="px-3.5 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:text-emerald-600 text-xs font-bold transition shadow-xs flex items-center space-x-1.5"
                    title="View Rider Wallets & Settlements"
                >
                    <span>💰</span>
                    <span>Wallets &amp; Payouts</span>
                </a>

                <a 
                    href="{{ route('admin.settings.index', ['tab' => 'rider_payout']) }}" 
                    class="px-3.5 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:text-indigo-600 text-xs font-bold transition shadow-xs flex items-center space-x-1.5"
                    title="Configure Base Pay, KM Rates &amp; Cash Limits"
                >
                    <span>⚙️</span>
                    <span>Payout Rates</span>
                </a>

                <a 
                    href="{{ route('admin.delivery_partners.export', request()->query()) }}" 
                    class="px-3.5 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:text-indigo-600 text-xs font-bold transition shadow-xs flex items-center space-x-1.5"
                    title="Export fleet to CSV"
                >
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Export CSV</span>
                </a>

                <button 
                    type="button" 
                    @click="openCreateModal()" 
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition shadow-xs flex items-center space-x-1.5 cursor-pointer"
                >
                    <span>+</span>
                    <span>Register Delivery Rider</span>
                </button>
            </div>
        </x-slot>
    </x-page-header>

    <!-- Metrics Cards Strip -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Fleet</div>
            <div class="text-xl font-black text-slate-900 dark:text-white">
                {{ number_format($metrics['total_riders']) }}
            </div>
            <div class="text-[10px] text-slate-400">Registered partners</div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Available &amp; Online</div>
            <div class="text-xl font-black text-emerald-600 dark:text-emerald-400 flex items-center space-x-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>{{ number_format($metrics['available']) }}</span>
            </div>
            <div class="text-[10px] text-slate-400">Ready for dispatch</div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Busy / On Delivery</div>
            <div class="text-xl font-black text-amber-500 flex items-center space-x-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                <span>{{ number_format($metrics['busy']) }}</span>
            </div>
            <div class="text-[10px] text-slate-400">Active package en route</div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Pending KYC</div>
            <div class="text-xl font-black text-violet-600 dark:text-violet-400">
                {{ number_format($metrics['pending_approval']) }}
            </div>
            <div class="text-[10px] text-slate-400">Awaiting verification</div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Offline / Off-Duty</div>
            <div class="text-xl font-black text-slate-500">
                {{ number_format($metrics['offline']) }}
            </div>
            <div class="text-[10px] text-slate-400">Not accepting orders</div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200 dark:border-slate-800 shadow-xs space-y-1">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Deliveries</div>
            <div class="text-xl font-black text-indigo-600 dark:text-indigo-400">
                {{ number_format($metrics['completed_deliveries']) }}
            </div>
            <div class="text-[10px] text-slate-400">Completed drop-offs</div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200 dark:border-slate-800 shadow-xs">
        <form method="GET" action="{{ route('admin.delivery_partners.index') }}" class="flex flex-wrap items-center gap-2.5">
            <!-- Search -->
            <div class="relative flex-1 min-w-[200px]">
                <input 
                    type="text" 
                    name="search" 
                    value="{{ $search }}" 
                    placeholder="Search rider name, phone, vehicle reg..." 
                    class="w-full text-xs font-medium pl-9 pr-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            <!-- Duty Status Filter -->
            <select name="status" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                <option value="all">All Duty Statuses</option>
                <option value="available" {{ $status === 'available' ? 'selected' : '' }}>🟢 Available &amp; Online</option>
                <option value="busy" {{ $status === 'busy' ? 'selected' : '' }}>🟡 Busy / On Job</option>
                <option value="offline" {{ $status === 'offline' ? 'selected' : '' }}>⚪ Offline / Off-Duty</option>
            </select>

            <!-- Approval Status Filter -->
            <select name="approval" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                <option value="all">All Approval Statuses</option>
                <option value="approved" {{ $approval === 'approved' ? 'selected' : '' }}>✓ Approved</option>
                <option value="pending" {{ $approval === 'pending' ? 'selected' : '' }}>⏳ Pending Approval</option>
                <option value="under_review" {{ $approval === 'under_review' ? 'selected' : '' }}>🔍 Under Review</option>
                <option value="rejected" {{ $approval === 'rejected' ? 'selected' : '' }}>✕ Rejected</option>
            </select>

            <!-- Vehicle Type Filter -->
            <select name="vehicle" class="text-xs font-semibold px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                <option value="all">All Vehicle Types</option>
                <option value="Electric Scooter" {{ $vehicle === 'Electric Scooter' ? 'selected' : '' }}>⚡ Electric Scooter</option>
                <option value="Motorcycle" {{ $vehicle === 'Motorcycle' ? 'selected' : '' }}>🏍️ Motorcycle</option>
                <option value="Bicycle" {{ $vehicle === 'Bicycle' ? 'selected' : '' }}>🚲 Bicycle</option>
                <option value="Van" {{ $vehicle === 'Van' ? 'selected' : '' }}>🚐 Van</option>
            </select>

            <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shrink-0">
                Filter
            </button>

            @if($search || $status !== 'all' || $vehicle !== 'all' || $approval !== 'all')
                <a href="{{ route('admin.delivery_partners.index') }}" class="text-xs font-semibold text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 self-center">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Fleet Table Card -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden">
        @if($riders->isEmpty())
            <div class="p-12 text-center space-y-3">
                <div class="w-16 h-16 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center text-3xl">
                    🚴
                </div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">No delivery partners found</h3>
                <p class="text-xs text-slate-500 max-w-sm mx-auto">Try clearing your filters or register a new delivery rider.</p>
                <button 
                    type="button" 
                    @click="openCreateModal()" 
                    class="inline-flex items-center space-x-1.5 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shadow-xs"
                >
                    <span>+ Register First Rider</span>
                </button>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 text-[11px] font-bold uppercase text-slate-400 tracking-wider">
                        <tr>
                            <th class="p-4">Rider Profile</th>
                            <th class="p-4">Phone / Contact</th>
                            <th class="p-4">Vehicle Fleet</th>
                            <th class="p-4">KYC Approval</th>
                            <th class="p-4">Duty Status</th>
                            <th class="p-4">Live GPS Position</th>
                            <th class="p-4 text-center">Active Jobs</th>
                            <th class="p-4 text-center">Deliveries</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                        @foreach($riders as $rider)
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition">
                                <td class="p-4">
                                    <div class="font-bold text-slate-900 dark:text-white flex items-center space-x-2">
                                        <div class="w-7 h-7 rounded-full bg-indigo-100 dark:bg-indigo-950/80 text-indigo-700 dark:text-indigo-400 font-black text-xs flex items-center justify-center shrink-0">
                                            {{ strtoupper(substr($rider->name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <span>{{ $rider->name }}</span>
                                            <div class="text-[10px] text-slate-400 font-mono">ID: #{{ $rider->id }}</div>
                                        </div>
                                    </div>
                                </td>

                                <td class="p-4">
                                    <div class="font-mono font-bold text-slate-800 dark:text-slate-200">
                                        <a href="tel:{{ $rider->phone }}" class="hover:text-indigo-600 transition">{{ $rider->phone }}</a>
                                    </div>
                                    @if($rider->emergency_contact_phone)
                                        <div class="text-[10px] text-slate-400">Emergency: {{ $rider->emergency_contact_phone }}</div>
                                    @else
                                        <span class="text-[10px] text-slate-400 font-sans">SMS / OTP Verified</span>
                                    @endif
                                </td>

                                <td class="p-4">
                                    <div class="font-bold text-slate-800 dark:text-slate-200">
                                        {{ $rider->vehicle }}
                                    </div>
                                    <div class="text-[10px] font-mono text-slate-400">
                                        {{ $rider->vehicle_number ?: 'No Plate Tagged' }}
                                    </div>
                                </td>

                                <td class="p-4">
                                    @if($rider->approval_status === 'approved')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                            ✓ Approved
                                        </span>
                                    @elseif(in_array($rider->approval_status, ['pending', 'under_review']))
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-200 dark:border-amber-800" title="Awaiting Admin KYC Review">
                                            ⏳ {{ ucfirst(str_replace('_', ' ', $rider->approval_status)) }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-400 border border-rose-200 dark:border-rose-800" title="{{ $rider->rejection_reason }}">
                                            ✕ Rejected
                                        </span>
                                    @endif

                                    @php
                                        $pendingDocs = $rider->documents->where('verification_status', 'pending')->count();
                                        $totalDocs = $rider->documents->count();
                                    @endphp
                                    <div class="mt-1.5">
                                        <button 
                                            type="button" 
                                            @click="openKycModal(@js($rider))"
                                            class="inline-flex items-center space-x-1 text-[10px] font-bold px-2 py-0.5 rounded-md border transition cursor-pointer
                                                {{ $pendingDocs > 0 ? 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-400 hover:bg-amber-100' : 
                                                   ($totalDocs > 0 ? 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 hover:bg-slate-200' : 
                                                   'bg-slate-50 text-slate-400 border-slate-100 dark:bg-slate-800 dark:text-slate-500 hover:bg-slate-100') }}"
                                            title="Review KYC Documents"
                                        >
                                            <span>📄 Docs: {{ $totalDocs }}</span>
                                            @if($pendingDocs > 0)
                                                <span class="text-amber-600 dark:text-amber-400 font-extrabold">({{ $pendingDocs }} pending)</span>
                                            @endif
                                        </button>
                                    </div>
                                </td>

                                <td class="p-4">
                                    <form method="POST" action="{{ route('admin.delivery_partners.toggle', $rider) }}" class="inline">
                                        @csrf
                                        <button 
                                            type="submit" 
                                            class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold border transition hover:opacity-80
                                                {{ $rider->status === 'available' ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-400 dark:border-emerald-800' :
                                                  ($rider->status === 'busy' ? 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-400 dark:border-amber-800' :
                                                   'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700') }}"
                                            title="Click to toggle Online/Offline"
                                        >
                                            <span class="w-1.5 h-1.5 rounded-full mr-1.5 
                                                {{ $rider->status === 'available' ? 'bg-emerald-500' : ($rider->status === 'busy' ? 'bg-amber-500' : 'bg-slate-400') }}"></span>
                                            <span class="capitalize">{{ $rider->status }}</span>
                                        </button>
                                    </form>
                                </td>

                                <td class="p-4 font-mono text-[11px] text-slate-600 dark:text-slate-300">
                                    @if($rider->current_lat && $rider->current_lng)
                                        <a 
                                            href="https://www.google.com/maps?q={{ $rider->current_lat }},{{ $rider->current_lng }}" 
                                            target="_blank" 
                                            class="text-indigo-600 dark:text-indigo-400 hover:underline flex items-center space-x-1"
                                            title="View GPS position on Google Maps"
                                        >
                                            <span>{{ number_format($rider->current_lat, 4) }}, {{ number_format($rider->current_lng, 4) }}</span>
                                            <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </a>
                                        @if($rider->last_location_updated_at)
                                            <div class="text-[9px] text-slate-400 font-sans mt-0.5">Updated {{ $rider->last_location_updated_at->diffForHumans() }}</div>
                                        @endif
                                    @else
                                        <span class="text-slate-400">GPS Offline</span>
                                    @endif
                                </td>

                                <td class="p-4 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $rider->active_orders_count > 0 ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-800' }}">
                                        {{ $rider->active_orders_count }} job(s)
                                    </span>
                                </td>

                                <td class="p-4 text-center font-bold text-slate-900 dark:text-white">
                                    {{ $rider->assignments_count }}
                                </td>

                                <td class="p-4 text-right">
                                    <div class="inline-flex items-center space-x-1">
                                        @if($rider->approval_status !== 'approved')
                                            <!-- Quick Approve Form -->
                                            <form method="POST" action="{{ route('admin.delivery_partners.approve', $rider) }}" class="inline" onsubmit="return confirm('Approve rider {{ $rider->name }} for delivery operations?');">
                                                @csrf
                                                <button 
                                                    type="submit" 
                                                    class="p-1.5 text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-950/60 rounded-lg transition" 
                                                    title="Approve Rider KYC"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                </button>
                                            </form>

                                            <!-- Quick Reject Button -->
                                            <button 
                                                type="button" 
                                                @click="openRejectModal({{ json_encode(['id' => $rider->id, 'name' => $rider->name]) }})" 
                                                class="p-1.5 text-rose-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/60 rounded-lg transition" 
                                                title="Reject Application"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        @endif

                                        <button 
                                            type="button" 
                                            @click="openKycModal(@js($rider))" 
                                            class="p-1.5 text-slate-400 hover:text-amber-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition" 
                                            title="Review KYC Documents"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        </button>

                                        <button 
                                            type="button" 
                                            @click="openEditModal(@js($rider))" 
                                            class="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition"
                                            title="Edit Rider Profile"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>

                                        <form method="POST" action="{{ route('admin.delivery_partners.destroy', $rider) }}" class="inline" onsubmit="return confirm('Remove rider {{ $rider->name }} from delivery fleet?');">
                                            @csrf
                                            @method('DELETE')
                                            <button 
                                                type="submit" 
                                                class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition" 
                                                title="Remove Rider"
                                                @if($rider->active_orders_count > 0) disabled title="Cannot remove rider with active orders" @endif
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $riders->links() }}
            </div>
        @endif
    </div>

    <!-- Create / Edit Rider Modal -->
    <x-modal name="rider-modal" size="lg">
        <x-slot name="header">
            <div class="flex items-center space-x-2">
                <span class="text-indigo-600 dark:text-indigo-400 text-lg">🚴</span>
                <h3 class="text-base font-bold text-slate-900 dark:text-white" x-text="modalMode === 'create' ? 'Register Delivery Partner' : 'Edit Delivery Partner Details'"></h3>
            </div>
        </x-slot>

        <form id="riderForm" :action="modalAction" method="POST" class="space-y-4">
            @csrf
            <template x-if="modalMode === 'edit'">
                <input type="hidden" name="_method" value="PUT">
            </template>

            <!-- Row 1: Name & Phone -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Full Name <span class="text-rose-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="name" 
                        x-model="form.name" 
                        required 
                        placeholder="e.g. Rahul Sharma"
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                    />
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Phone Number <span class="text-rose-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="phone" 
                        x-model="form.phone" 
                        required 
                        placeholder="+919876543210"
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
            </div>

            <!-- Row 2: Vehicle Type & Plate Number -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Vehicle Fleet Type <span class="text-rose-500">*</span>
                    </label>
                    <select 
                        name="vehicle" 
                        x-model="form.vehicle" 
                        required 
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                    >
                        <option value="Electric Scooter">⚡ Electric Scooter (EV)</option>
                        <option value="Motorcycle">🏍️ Motorcycle / Bike</option>
                        <option value="Bicycle">🚲 Bicycle</option>
                        <option value="Van">🚐 Delivery Van / Cargo</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Vehicle Reg Number (Plate)
                    </label>
                    <input 
                        type="text" 
                        name="vehicle_number" 
                        x-model="form.vehicle_number" 
                        placeholder="e.g. KA-01-EQ-1234"
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono uppercase"
                    />
                </div>
            </div>

            <!-- Row 3: Emergency Contact Info -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Emergency Contact Person
                    </label>
                    <input 
                        type="text" 
                        name="emergency_contact_name" 
                        x-model="form.emergency_contact_name" 
                        placeholder="e.g. Priya Sharma (Spouse / Parent)"
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                    />
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Emergency Contact Phone
                    </label>
                    <input 
                        type="text" 
                        name="emergency_contact_phone" 
                        x-model="form.emergency_contact_phone" 
                        placeholder="+919876500000"
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono"
                    />
                </div>
            </div>

            <!-- Row 4: Cash Limit & Password (Create Only) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Cash In Hand Limit (₹)
                    </label>
                    <input 
                        type="number" 
                        step="100" 
                        name="cash_in_hand_limit" 
                        x-model="form.cash_in_hand_limit" 
                        placeholder="5000"
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono"
                    />
                    <div class="text-[10px] text-slate-400 mt-0.5">Maximum COD amount rider can hold before remitting</div>
                </div>

                <template x-if="modalMode === 'create'">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Rider Login Password
                        </label>
                        <input 
                            type="password" 
                            name="password" 
                            placeholder="Leave empty for default (Password@123)"
                            class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                        />
                        <div class="text-[10px] text-slate-400 mt-0.5">Used for Rider Portal &amp; API access</div>
                    </div>
                </template>
            </div>

            <!-- Row 5: Duty Status & GPS Coordinates -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Duty Status
                    </label>
                    <select 
                        name="status" 
                        x-model="form.status" 
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                    >
                        <option value="available">🟢 Available &amp; Online</option>
                        <option value="busy">🟡 Busy / On Job</option>
                        <option value="offline">⚪ Offline / Off-Duty</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Initial Latitude
                    </label>
                    <input 
                        type="number" 
                        step="0.000001" 
                        name="current_lat" 
                        x-model="form.current_lat" 
                        placeholder="12.9716"
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono"
                    />
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Initial Longitude
                    </label>
                    <input 
                        type="number" 
                        step="0.000001" 
                        name="current_lng" 
                        x-model="form.current_lng" 
                        placeholder="77.5946"
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono"
                    />
                </div>
            </div>

            <!-- Active Checkbox -->
            <div class="flex items-center space-x-2.5 pt-1">
                <input 
                    type="checkbox" 
                    id="rider_active_box" 
                    name="is_active" 
                    value="1" 
                    x-model="form.is_active" 
                    class="rounded border-slate-300 dark:border-slate-700 text-indigo-600 focus:ring-indigo-500 h-4 w-4"
                >
                <label for="rider_active_box" class="text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
                    Rider account is verified &amp; active in quick commerce dispatcher pool
                </label>
            </div>
        </form>

        <x-slot name="footer">
            <button 
                type="button" 
                @click="$dispatch('close-modal', 'rider-modal')" 
                class="px-4 py-2 text-xs font-semibold rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 transition"
            >
                Cancel
            </button>
            <button 
                form="riderForm"
                type="submit" 
                class="px-5 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition flex items-center space-x-1.5 cursor-pointer"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span x-text="modalMode === 'create' ? 'Register Rider' : 'Save Changes'"></span>
            </button>
        </x-slot>
    </x-modal>

    <!-- Reject KYC Modal -->
    <x-modal name="reject-modal" size="md">
        <x-slot name="header">
            <div class="flex items-center space-x-2">
                <span class="text-rose-500 text-lg">✕</span>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Reject Rider KYC Application</h3>
            </div>
        </x-slot>

        <form id="rejectForm" :action="rejectAction" method="POST" class="space-y-4">
            @csrf
            <div class="text-xs text-slate-600 dark:text-slate-300">
                Rejecting application for <strong x-text="rejectRiderName"></strong>. Please provide a clear rejection reason for the applicant.
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                    Rejection Reason <span class="text-rose-500">*</span>
                </label>
                <textarea 
                    name="rejection_reason" 
                    rows="3" 
                    required 
                    placeholder="e.g. Driving license image is blurred; vehicle insurance expired."
                    class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-rose-500"
                ></textarea>
            </div>
        </form>

        <x-slot name="footer">
            <button 
                type="button" 
                @click="$dispatch('close-modal', 'reject-modal')" 
                class="px-4 py-2 text-xs font-semibold rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 transition"
            >
                Cancel
            </button>
            <button 
                form="rejectForm"
                type="submit" 
                class="px-5 py-2 text-xs font-bold rounded-xl bg-rose-600 hover:bg-rose-700 text-white shadow-xs transition flex items-center space-x-1.5 cursor-pointer"
            >
                <span>Confirm Rejection</span>
            </button>
        </x-slot>
    </x-modal>

    <!-- KYC Documents Review Modal -->
    <x-modal name="kyc-docs-modal" size="xl">
        <x-slot name="header">
            <div class="flex items-center space-x-2">
                <span class="text-indigo-600 text-lg">📄</span>
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">
                        KYC Documents: <span x-text="selectedRider?.name"></span>
                    </h3>
                    <p class="text-[11px] text-slate-400">
                        Phone: <span x-text="selectedRider?.phone"></span> &bull; 
                        Vehicle: <span x-text="selectedRider?.vehicle"></span> (<span x-text="selectedRider?.vehicle_number || 'No Plate'"></span>) &bull;
                        Status: <span class="uppercase font-bold" x-text="selectedRider?.approval_status"></span>
                    </p>
                </div>
            </div>
        </x-slot>

        <div class="space-y-4">
            <template x-if="!selectedDocs || selectedDocs.length === 0">
                <div class="p-8 text-center bg-slate-50 dark:bg-slate-800/40 rounded-2xl border border-dashed border-slate-200 dark:border-slate-700">
                    <div class="text-3xl mb-2">📤</div>
                    <div class="text-sm font-bold text-slate-700 dark:text-slate-300">No KYC Documents Uploaded Yet</div>
                    <div class="text-xs text-slate-500 max-w-sm mx-auto mt-1">
                        The rider has not submitted documents yet. They can upload documents through their mobile portal at <code>/rider/kyc</code>.
                    </div>
                </div>
            </template>

            <template x-if="selectedDocs && selectedDocs.length > 0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-800 text-[10px] font-bold uppercase text-slate-400 tracking-wider">
                            <tr>
                                <th class="p-3">Document Type</th>
                                <th class="p-3">Doc #</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">File / Preview</th>
                                <th class="p-3 text-right">Review Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <template x-for="doc in selectedDocs" :key="doc.id">
                                <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                                    <td class="p-3 font-bold text-slate-800 dark:text-slate-200">
                                        <span x-text="formatDocType(doc.document_type)"></span>
                                    </td>
                                    <td class="p-3 font-mono text-slate-600 dark:text-slate-400">
                                        <span x-text="doc.document_number || '—'"></span>
                                    </td>
                                    <td class="p-3">
                                        <span 
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold"
                                            :class="doc.verification_status === 'approved' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-400' : (doc.verification_status === 'rejected' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-400' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400')"
                                            x-text="(doc.verification_status || 'PENDING').toUpperCase()"
                                        ></span>
                                        <div x-show="doc.rejection_reason" class="text-[10px] text-rose-500 mt-0.5" x-text="'Reason: ' + doc.rejection_reason"></div>
                                    </td>
                                    <td class="p-3">
                                        <a 
                                            :href="'/admin/delivery-partners/' + selectedRider.id + '/documents/' + doc.id + '/view'" 
                                            target="_blank"
                                            class="inline-flex items-center space-x-1 text-indigo-600 dark:text-indigo-400 hover:underline font-bold"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                            <span>Open File</span>
                                        </a>
                                    </td>
                                    <td class="p-3 text-right">
                                        <div class="inline-flex items-center space-x-1.5" x-data="{ rejecting: false, reason: '' }">
                                            <form :action="'/admin/delivery-partners/' + selectedRider.id + '/documents/' + doc.id + '/review'" method="POST" class="inline">
                                                @csrf
                                                <input type="hidden" name="status" value="approved">
                                                <button 
                                                    type="submit" 
                                                    class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold rounded-lg transition"
                                                    :disabled="doc.verification_status === 'approved'"
                                                    :class="doc.verification_status === 'approved' ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'"
                                                >
                                                    Approve
                                                </button>
                                            </form>

                                            <button 
                                                type="button" 
                                                @click="rejecting = !rejecting"
                                                class="px-2.5 py-1 bg-rose-600 hover:bg-rose-700 text-white text-[11px] font-bold rounded-lg transition cursor-pointer"
                                                :disabled="doc.verification_status === 'rejected'"
                                                :class="doc.verification_status === 'rejected' ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'"
                                            >
                                                Reject
                                            </button>

                                            <!-- Inline rejection reason popup -->
                                            <div x-show="rejecting" x-cloak class="absolute z-20 mt-8 right-8 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 p-3 rounded-xl shadow-xl w-64 text-left">
                                                <form :action="'/admin/delivery-partners/' + selectedRider.id + '/documents/' + doc.id + '/review'" method="POST" class="space-y-2">
                                                    @csrf
                                                    <input type="hidden" name="status" value="rejected">
                                                    <label class="block text-[10px] font-bold uppercase text-slate-600 dark:text-slate-400">Rejection Reason</label>
                                                    <input type="text" name="rejection_reason" required placeholder="e.g. Image unreadable / expired" class="w-full text-xs p-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                                                    <div class="flex justify-end space-x-1 pt-1">
                                                        <button type="button" @click="rejecting = false" class="px-2 py-1 text-[10px] text-slate-500">Cancel</button>
                                                        <button type="submit" class="px-2.5 py-1 text-[10px] bg-rose-600 text-white font-bold rounded-lg">Confirm</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>

        <x-slot name="footer">
            <div class="flex items-center justify-between w-full">
                <div>
                    <template x-if="selectedRider && selectedRider.approval_status !== 'approved'">
                        <form :action="'/admin/delivery-partners/' + selectedRider.id + '/approve'" method="POST" class="inline" onsubmit="return confirm('Approve this rider for active deliveries?');">
                            @csrf
                            <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-xs transition cursor-pointer">
                                ✓ Approve Rider &amp; Activate
                            </button>
                        </form>
                    </template>
                </div>
                <button 
                    type="button" 
                    @click="$dispatch('close-modal', 'kyc-docs-modal')" 
                    class="px-4 py-2 text-xs font-semibold rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 transition"
                >
                    Close
                </button>
            </div>
        </x-slot>
    </x-modal>

</div>

<script>
    function deliveryPartnerManager() {
        return {
            modalMode: 'create',
            modalAction: '{{ route("admin.delivery_partners.store") }}',
            rejectAction: '',
            rejectRiderName: '',
            selectedRider: null,
            selectedDocs: [],
            form: {
                id: null,
                name: '',
                phone: '',
                vehicle: 'Electric Scooter',
                vehicle_number: '',
                emergency_contact_name: '',
                emergency_contact_phone: '',
                cash_in_hand_limit: 5000,
                status: 'available',
                current_lat: 12.9716,
                current_lng: 77.5946,
                is_active: true
            },

            formatDocType(type) {
                const map = {
                    'driving_license_front': 'Driving License (Front)',
                    'driving_license_back': 'Driving License (Back)',
                    'vehicle_rc': 'Vehicle Registration (RC)',
                    'aadhaar_card': 'Aadhaar Card / Govt ID',
                    'insurance': 'Vehicle Insurance Policy',
                    'pan_card': 'PAN Card'
                };
                return map[type] || (type ? type.replace(/_/g, ' ').toUpperCase() : 'Document');
            },

            openKycModal(rider) {
                this.selectedRider = rider;
                this.selectedDocs = rider.documents || [];
                this.$dispatch('open-modal', 'kyc-docs-modal');
            },

            openCreateModal() {
                this.modalMode = 'create';
                this.modalAction = '{{ route("admin.delivery_partners.store") }}';
                this.form = {
                    id: null,
                    name: '',
                    phone: '',
                    vehicle: 'Electric Scooter',
                    vehicle_number: '',
                    emergency_contact_name: '',
                    emergency_contact_phone: '',
                    cash_in_hand_limit: 5000,
                    status: 'available',
                    current_lat: 12.9716,
                    current_lng: 77.5946,
                    is_active: true
                };
                this.$dispatch('open-modal', 'rider-modal');
            },

            openEditModal(rider) {
                this.modalMode = 'edit';
                this.modalAction = '{{ url("/admin/delivery-partners") }}/' + rider.id;
                this.form = {
                    id: rider.id,
                    name: rider.name,
                    phone: rider.phone,
                    vehicle: rider.vehicle || 'Electric Scooter',
                    vehicle_number: rider.vehicle_number || '',
                    emergency_contact_name: rider.emergency_contact_name || '',
                    emergency_contact_phone: rider.emergency_contact_phone || '',
                    cash_in_hand_limit: rider.cash_in_hand_limit || 5000,
                    status: rider.status || 'available',
                    current_lat: rider.current_lat || 12.9716,
                    current_lng: rider.current_lng || 77.5946,
                    is_active: !!rider.is_active
                };
                this.$dispatch('open-modal', 'rider-modal');
            },

            openRejectModal(rider) {
                this.rejectRiderName = rider.name;
                this.rejectAction = '{{ url("/admin/delivery-partners") }}/' + rider.id + '/reject';
                this.$dispatch('open-modal', 'reject-modal');
            }
        };
    }
</script>
@endsection
