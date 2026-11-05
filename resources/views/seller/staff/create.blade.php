@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'Invite Store Staff'])

@section('content')
<div class="max-w-3xl space-y-6" x-data="{
    role: 'manager',
    roleDefaults: {
        'owner': ['store.settings', 'inventory.view', 'inventory.manage', 'orders.view', 'orders.manage', 'orders.pack', 'packing.manage', 'delivery.manage', 'staff.manage', 'connections.manage', 'reports.view', 'support.tickets'],
        'manager': ['store.settings', 'inventory.view', 'inventory.manage', 'orders.view', 'orders.manage', 'orders.pack', 'packing.manage', 'delivery.manage', 'staff.manage', 'connections.manage', 'reports.view', 'support.tickets'],
        'inventory': ['inventory.view', 'inventory.manage', 'connections.manage'],
        'packing': ['orders.view', 'orders.manage', 'orders.pack', 'packing.manage'],
        'delivery': ['orders.view', 'delivery.manage'],
        'support': ['orders.view', 'support.tickets'],
        'cashier': ['orders.view', 'orders.manage']
    },
    selectedPermissions: ['store.settings', 'inventory.view', 'inventory.manage', 'orders.view', 'orders.manage', 'orders.pack', 'packing.manage', 'delivery.manage', 'staff.manage', 'connections.manage', 'reports.view', 'support.tickets'],
    applyRoleDefaults() {
        this.selectedPermissions = this.roleDefaults[this.role] || [];
    }
}">
    <x-page-header 
        title="Invite Store Staff Member" 
        subtitle="Authorize staff via email or phone with dedicated store scoping and customizable permissions (Blueprint Section 20)."
        :breadcrumbs="['Seller Studio' => route('seller.dashboard'), 'Staff & Roles' => route('seller.staff.index'), 'Invite Staff' => '#']"
    />

    <form method="POST" action="{{ route('seller.staff.store') }}" class="space-y-6">
        @csrf

        <x-card title="Staff Identity & Store Assignment">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Target Physical Store / Hub <span class="text-rose-500">*</span></label>
                    <select name="store_id" required class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white font-semibold">
                        @foreach($stores as $st)
                            <option value="{{ $st->id }}" {{ $selectedStoreId === $st->id ? 'selected' : '' }}>
                                {{ $st->name }} ({{ $st->location?->city ?? 'Central Hub' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Staff Full Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" placeholder="e.g. Ramesh Kumar" required class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white" />
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Email Address</label>
                    <input type="email" name="email" value="{{ old('email') }}" placeholder="ramesh@example.com" class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white" />
                    <span class="text-[10px] text-slate-400 mt-0.5 block">Staff will receive login credentials via email.</span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Mobile Phone (WhatsApp OTP)</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" placeholder="9876543210" class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white" />
                    <span class="text-[10px] text-slate-400 mt-0.5 block">Can also sign in via SMS / WhatsApp OTP.</span>
                </div>
            </div>
        </x-card>

        <x-card title="Role Assignment &amp; Granular Permission Matrix">
            <div class="space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex-1">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Primary Staff Role <span class="text-rose-500">*</span></label>
                        <select name="role" x-model="role" @change="applyRoleDefaults()" required class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white font-bold">
                            @foreach($roles as $r)
                                <option value="{{ $r->value }}">{{ $r->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="pt-4 sm:pt-0">
                        <button type="button" @click="applyRoleDefaults()" class="px-3 py-2 text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                            Reset to Role Defaults
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">Customized Permissions for this Store</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/40">
                        @foreach($availablePermissions as $key => $label)
                            <label class="flex items-start space-x-2.5 p-2 rounded-lg hover:bg-slate-100/80 dark:hover:bg-slate-800/80 cursor-pointer transition text-xs">
                                <input 
                                    type="checkbox" 
                                    name="permissions[]" 
                                    value="{{ $key }}" 
                                    :checked="selectedPermissions.includes('{{ $key }}')" 
                                    @click="selectedPermissions.includes('{{ $key }}') ? selectedPermissions = selectedPermissions.filter(p => p !== '{{ $key }}') : selectedPermissions.push('{{ $key }}')"
                                    class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 mt-0.5" 
                                />
                                <div>
                                    <div class="font-mono font-bold text-[11px] text-slate-900 dark:text-white">{{ $key }}</div>
                                    <div class="text-[10px] text-slate-500 leading-tight mt-0.5">{{ $label }}</div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </x-card>

        <div class="flex items-center justify-end space-x-3">
            <x-button variant="outline" size="sm" href="{{ route('seller.staff.index') }}">Cancel</x-button>
            <x-button variant="primary" size="sm" type="submit">Send Staff Invitation &amp; Authorize</x-button>
        </div>
    </form>
</div>
@endsection
