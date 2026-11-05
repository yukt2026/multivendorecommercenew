@extends('layouts.admin')

@section('title', 'Delivery Partners Fleet Management')

@section('content')
<div class="space-y-6" x-data="{ showAddModal: false }">
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Delivery Partners Fleet</h1>
            <p class="text-xs text-slate-500 mt-0.5">Manage hyperlocal delivery riders, live GPS coordinates, and availability status.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.quick.index') }}" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
                ← Mission Control
            </a>
            <button @click="showAddModal = true" class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-xs font-bold rounded-xl transition shadow flex items-center gap-1.5">
                <span>+</span> Add Delivery Rider
            </button>
        </div>
    </div>

    <!-- Alerts -->
    @if(session('success'))
        <div class="bg-emerald-50 text-emerald-800 border border-emerald-200 p-4 rounded-2xl text-xs font-bold">
            ✓ {{ session('success') }}
        </div>
    @endif

    <!-- Fleet Table Card -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-400 uppercase tracking-wider text-[10px]">
                        <th class="py-3 px-4 font-semibold">Rider Name</th>
                        <th class="py-3 px-4 font-semibold">Phone</th>
                        <th class="py-3 px-4 font-semibold">Vehicle</th>
                        <th class="py-3 px-4 font-semibold">Status</th>
                        <th class="py-3 px-4 font-semibold">Current Coords</th>
                        <th class="py-3 px-4 font-semibold">Active Jobs</th>
                        <th class="py-3 px-4 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($riders as $rider)
                        <tr class="hover:bg-slate-50/50">
                            <td class="py-3 px-4 font-bold text-slate-900">
                                {{ $rider->name }}
                            </td>
                            <td class="py-3 px-4 text-slate-600 font-mono">
                                {{ $rider->phone }}
                            </td>
                            <td class="py-3 px-4 text-slate-700">
                                {{ $rider->vehicle }} ({{ $rider->vehicle_number ?? 'N/A' }})
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2.5 py-1 text-[10px] font-bold rounded-lg uppercase
                                    {{ $rider->status === 'available' ? 'bg-emerald-100 text-emerald-800' :
                                      ($rider->status === 'busy' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600') }}">
                                    {{ $rider->status }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-mono text-[11px] text-slate-500">
                                {{ $rider->current_lat ? number_format($rider->current_lat, 4) . ', ' . number_format($rider->current_lng, 4) : 'N/A' }}
                            </td>
                            <td class="py-3 px-4 font-bold text-slate-900">
                                {{ $rider->active_orders_count }}
                            </td>
                            <td class="py-3 px-4 text-right">
                                <form action="{{ route('admin.delivery_partners.toggle', $rider->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-orange-600 hover:text-orange-800">
                                        {{ $rider->status === 'offline' ? 'Set Online' : 'Set Offline' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400">
                                No delivery partners registered yet. Click "+ Add Delivery Rider" above.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100">
            {{ $riders->links() }}
        </div>
    </div>

    <!-- Add Rider Modal -->
    <div x-show="showAddModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white rounded-3xl p-6 w-full max-w-md shadow-2xl space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 class="font-extrabold text-base text-slate-900">Register New Rider</h3>
                <button @click="showAddModal = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
            </div>

            <form action="{{ route('admin.delivery_partners.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Full Name</label>
                    <input type="text" name="name" required class="w-full text-xs rounded-xl border-slate-200">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Phone Number</label>
                    <input type="text" name="phone" required class="w-full text-xs rounded-xl border-slate-200">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Vehicle Type</label>
                        <select name="vehicle" class="w-full text-xs rounded-xl border-slate-200">
                            <option value="Electric Scooter">Electric Scooter</option>
                            <option value="Motorcycle">Motorcycle</option>
                            <option value="Bicycle">Bicycle</option>
                            <option value="Van">Van</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Vehicle Plate No.</label>
                        <input type="text" name="vehicle_number" placeholder="KA-01-AB-1234" class="w-full text-xs rounded-xl border-slate-200">
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="showAddModal = false" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-orange-600 text-white font-bold rounded-xl text-xs shadow">
                        Save Partner
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
