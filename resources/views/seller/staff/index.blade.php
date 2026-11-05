@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'Store Staff & Permissions'])

@section('content')
<div class="space-y-6" x-data="{
    editingStaff: null,
    editRole: '',
    editPermissions: [],
    openEditModal(staff) {
        this.editingStaff = staff;
        this.editRole = staff.role;
        this.editPermissions = staff.permissions || [];
    }
}">
    <!-- Header -->
    <x-page-header 
        title="Store Staff &amp; Team Permissions" 
        subtitle="Manage store managers, inventory pickers, packing operators, and delivery staff with granular access controls (Blueprint Section 20)."
        :breadcrumbs="['Seller Studio' => route('seller.dashboard'), 'Staff & Roles' => route('seller.staff.index')]"
    >
        <x-slot name="actions">
            <x-button variant="primary" size="sm" href="{{ route('seller.staff.create') }}" icon="plus">
                Invite Staff Member
            </x-button>
        </x-slot>
    </x-page-header>

    <!-- Staff Table Card -->
    <x-card :padded="false">
        <div class="p-4 sm:p-5 border-b border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Active Store Assignments</h3>

            <form method="GET" action="{{ route('seller.staff.index') }}" class="flex items-center space-x-2">
                <select name="store_id" onchange="this.form.submit()" class="text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-1.5 text-slate-900 dark:text-white font-semibold">
                    <option value="all">All Stores</option>
                    @foreach($stores as $st)
                        <option value="{{ $st->id }}" {{ $selectedStoreId === $st->id ? 'selected' : '' }}>
                            {{ $st->name }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <th class="py-3 px-4">Staff Member</th>
                        <th class="py-3 px-4">Assigned Store</th>
                        <th class="py-3 px-4">Role</th>
                        <th class="py-3 px-4">Permission Matrix</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                    @forelse($staffList as $staff)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                            <td class="py-3 px-4">
                                <div class="flex items-center space-x-3">
                                    <x-avatar :name="$staff->user->name" size="sm" />
                                    <div>
                                        <div class="font-bold text-slate-900 dark:text-white">{{ $staff->user->name }}</div>
                                        <div class="text-[11px] text-slate-400">{{ $staff->user->email ?? $staff->user->phone }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-bold text-slate-800 dark:text-slate-200">{{ $staff->store->name }}</div>
                                <div class="text-[11px] text-slate-400">{{ $staff->store->location?->city ?? 'Central Hub' }}</div>
                            </td>
                            <td class="py-3 px-4">
                                @php
                                    $roleBadge = match($staff->role->value) {
                                        'owner' => 'purple',
                                        'manager' => 'indigo',
                                        'inventory' => 'emerald',
                                        'packing' => 'amber',
                                        'delivery' => 'sky',
                                        default => 'slate',
                                    };
                                @endphp
                                <x-badge :variant="$roleBadge" size="sm">
                                    {{ $staff->role->label() }}
                                </x-badge>
                            </td>
                            <td class="py-3 px-4 max-w-xs">
                                <div class="flex flex-wrap gap-1">
                                    @php
                                        $perms = $staff->permissions ?? $staff->role->defaultPermissions();
                                    @endphp
                                    @foreach(array_slice($perms, 0, 3) as $p)
                                        <span class="px-1.5 py-0.5 rounded text-[10px] bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-mono">
                                            {{ $p }}
                                        </span>
                                    @endforeach
                                    @if(count($perms) > 3)
                                        <span class="px-1.5 py-0.5 rounded text-[10px] bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-mono font-bold">
                                            +{{ count($perms) - 3 }} more
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <x-badge :variant="$staff->status === 'active' ? 'success' : 'warning'" size="sm" dot>
                                    {{ ucfirst($staff->status) }}
                                </x-badge>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end space-x-2">
                                    <button 
                                        type="button" 
                                        @click="openEditModal({{ Js::from($staff) }})"
                                        class="px-2 py-1 text-xs font-semibold rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                                    >
                                        Edit Matrix
                                    </button>

                                    @if($staff->role->value !== 'owner')
                                        <form method="POST" action="{{ route('seller.staff.destroy', $staff) }}" onsubmit="return confirm('Revoke staff access for {{ $staff->user->name }}?')" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition" title="Revoke Staff">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400">
                                No staff members assigned yet. Click "Invite Staff Member" to add team members.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <!-- Quick Permission Matrix Legend Card -->
    <x-card title="Standard Role Capabilities (Blueprint Section 20)">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs pt-1">
            <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/30">
                <div class="font-bold text-slate-900 dark:text-white flex items-center space-x-1.5 mb-1">
                    <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                    <span>Store Operations Manager</span>
                </div>
                <p class="text-slate-500">Full operations access: orders console, stock adjustments, staff management, thermal slips, and local settings.</p>
            </div>
            <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/30">
                <div class="font-bold text-slate-900 dark:text-white flex items-center space-x-1.5 mb-1">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    <span>Order Packaging &amp; Dispatch</span>
                </div>
                <p class="text-slate-500">Scoped to incoming orders queue, mark packing/packed, print 4x6 labels, and handoff to quick commerce riders.</p>
            </div>
            <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/30">
                <div class="font-bold text-slate-900 dark:text-white flex items-center space-x-1.5 mb-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span>Inventory &amp; Shelf Controller</span>
                </div>
                <p class="text-slate-500">View SKU balances, quick restock, barcode lookup, location transfers, and bulk CSV catalog imports.</p>
            </div>
        </div>
    </x-card>

    <!-- Edit Staff Permissions Modal -->
    <div 
        x-show="editingStaff" 
        x-cloak 
        class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs"
    >
        <div 
            @click.away="editingStaff = null" 
            class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-xl w-full p-6 shadow-2xl space-y-4"
        >
            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Edit Staff Permissions</h3>
                <button @click="editingStaff = null" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <template x-if="editingStaff">
                <form :action="'/seller/staff/' + editingStaff.id" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Assigned Role</label>
                        <select name="role" x-model="editRole" class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white">
                            @foreach($roles as $r)
                                <option value="{{ $r->value }}">{{ $r->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">Granular Permission Matrix</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-56 overflow-y-auto p-2 border border-slate-200 dark:border-slate-800 rounded-xl bg-slate-50 dark:bg-slate-950/50">
                            @foreach($availablePermissions as $key => $desc)
                                <label class="flex items-start space-x-2 p-1.5 rounded hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer text-xs">
                                    <input 
                                        type="checkbox" 
                                        name="permissions[]" 
                                        value="{{ $key }}" 
                                        :checked="editPermissions.includes('{{ $key }}')" 
                                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 mt-0.5" 
                                    />
                                    <div>
                                        <div class="font-mono font-bold text-[11px] text-slate-800 dark:text-slate-200">{{ $key }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $desc }}</div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                        <button type="button" @click="editingStaff = null" class="px-3 py-1.5 text-xs font-semibold rounded-xl border border-slate-300 dark:border-slate-700 hover:bg-slate-100">Cancel</button>
                        <x-button variant="primary" size="sm" type="submit">Save Matrix Updates</x-button>
                    </div>
                </form>
            </template>
        </div>
    </div>
</div>
@endsection
