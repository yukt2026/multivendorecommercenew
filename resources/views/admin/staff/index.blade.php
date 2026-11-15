@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Admin Staff & Access Control'])

@section('content')
<div class="space-y-6" x-data="staffManager()">

    <!-- Page Header -->
    <x-page-header 
        title="Admin Staff & Role Management" 
        subtitle="Manage platform administrative personnel, assign roles, and configure granular capability permissions."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Staff' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                {{ $metrics['total_staff'] }} Personnel
            </span>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center space-x-2">
                <a 
                    href="{{ route('admin.staff.roles') }}" 
                    class="px-4 py-2 border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition"
                >
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    <span>Permissions Matrix</span>
                </a>
                <button 
                    @click="openModal()" 
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm flex items-center space-x-1.5 transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Add Staff Member</span>
                </button>
            </div>
        </x-slot>
    </x-page-header>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-slate-500">Total Staff Accounts</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $metrics['total_staff'] }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-rose-600 dark:text-rose-400">Super Admins</div>
            <div class="text-2xl font-bold text-rose-600 dark:text-rose-400 mt-1">{{ $metrics['super_admins'] }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-indigo-600 dark:text-indigo-400">Operations Admins</div>
            <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ $metrics['admins'] }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-amber-600 dark:text-amber-400">Support Desk Admins</div>
            <div class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ $metrics['support_admins'] }}</div>
        </div>
    </div>

    <!-- Filter Strip -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm flex flex-col md:flex-row gap-4 justify-between items-center">
        <form method="GET" action="{{ route('admin.staff.index') }}" class="flex flex-wrap items-center gap-2 w-full">
            <div class="relative flex-1 min-w-[200px]">
                <input 
                    type="text" 
                    name="search" 
                    value="{{ request('search') }}" 
                    placeholder="Search name, email, phone..." 
                    class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white pl-8 pr-3 py-2 focus:ring-1 focus:ring-indigo-500"
                >
                <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            <select name="role" class="text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white py-2 px-3">
                <option value="all">All Roles</option>
                @foreach($roles as $r)
                    <option value="{{ $r->name }}" {{ request('role') === $r->name ? 'selected' : '' }}>
                        {{ ucfirst(str_replace('_', ' ', $r->name)) }}
                    </option>
                @endforeach
            </select>

            <select name="status" class="text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white py-2 px-3">
                <option value="all">All Statuses</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active Only</option>
                <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended Only</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive Only</option>
            </select>

            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow-sm transition">
                Filter
            </button>
            <a href="{{ route('admin.staff.index') }}" class="px-3 py-2 text-xs text-slate-500 hover:text-slate-800 dark:hover:text-slate-200">
                Reset
            </a>
        </form>
    </div>

    <!-- Staff Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Staff Member</th>
                        <th class="px-6 py-3.5">Contact</th>
                        <th class="px-6 py-3.5">Assigned Role</th>
                        <th class="px-6 py-3.5">Account Status</th>
                        <th class="px-6 py-3.5">Joined Date</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($staff as $u)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        <td class="px-6 py-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-400 font-bold flex items-center justify-center text-xs">
                                    {{ strtoupper(substr($u->name, 0, 1)) }}
                                </div>
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white">{{ $u->name }}</div>
                                    @if($u->id === auth()->id())
                                        <span class="text-[9px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">(You)</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                            <div>{{ $u->email }}</div>
                            <div class="text-[10px] text-slate-400">{{ $u->phone ?: 'No phone recorded' }}</div>
                        </td>
                        <td class="px-6 py-4">
                            @foreach($u->roles as $role)
                                <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider
                                    {{ $role->name === 'super_admin' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-400 border border-rose-200 dark:border-rose-800' : '' }}
                                    {{ $role->name === 'admin' ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800' : '' }}
                                    {{ $role->name === 'support_admin' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-200 dark:border-amber-800' : '' }}
                                    {{ !in_array($role->name, ['super_admin', 'admin', 'support_admin']) ? 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700' : '' }}
                                ">
                                    {{ str_replace('_', ' ', $role->name) }}
                                </span>
                            @endforeach
                        </td>
                        <td class="px-6 py-4">
                            @php
                                $statusEnum = $u->status instanceof \App\Enums\UserStatus ? $u->status : \App\Enums\UserStatus::tryFrom((string) $u->status);
                                $rawStatus = $statusEnum?->value ?? ($u->status ?? 'active');
                                $label = $statusEnum?->label() ?? ucfirst((string) $rawStatus);
                                $badgeClass = match ($rawStatus) {
                                    'active' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-400',
                                    'suspended' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/60 dark:text-rose-400',
                                    'inactive' => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300',
                                    'pending' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-400',
                                    default => 'bg-slate-50 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300',
                                };
                                $dotClass = match ($rawStatus) {
                                    'active' => 'bg-emerald-500',
                                    'suspended' => 'bg-rose-500',
                                    'inactive' => 'bg-slate-400',
                                    'pending' => 'bg-amber-500',
                                    default => 'bg-slate-400',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border {{ $badgeClass }}">
                                <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $dotClass }}"></span>
                                {{ $label }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-slate-500">
                            {{ $u->created_at->format('d M Y') }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end space-x-2">
                                <button 
                                    @click="editStaff(@js($u), '{{ $u->roles->first()?->name }}')" 
                                    class="p-1.5 text-slate-500 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition" 
                                    title="Edit Staff Member"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                @if($u->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.staff.destroy', $u) }}" onsubmit="return confirm('Revoke and delete this admin staff account?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 text-slate-500 hover:text-rose-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition" title="Delete Account">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                            No staff accounts found matching criteria.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-200 dark:border-slate-800">
            <x-pagination :paginator="$staff" />
        </div>
    </div>

    <!-- Add / Edit Staff Modal -->
    <div 
        x-show="isOpen" 
        x-cloak 
        class="fixed inset-0 z-50 overflow-y-auto" 
        aria-labelledby="modal-title" 
        role="dialog" 
        aria-modal="true"
    >
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="isOpen" @click="closeModal()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

            <div 
                x-show="isOpen" 
                class="inline-block align-bottom bg-white dark:bg-slate-900 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border border-slate-200 dark:border-slate-800"
            >
                <form :action="formUrl" method="POST">
                    @csrf
                    <template x-if="isEditing">
                        <input type="hidden" name="_method" value="PUT">
                    </template>

                    <div class="px-6 py-5 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white" x-text="isEditing ? 'Edit Staff Member' : 'Create Admin Staff Member'"></h3>
                        <button type="button" @click="closeModal()" class="text-slate-400 hover:text-slate-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Full Name *</label>
                            <input type="text" name="name" x-model="form.name" required placeholder="e.g. Ramesh Kumar" class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email Address *</label>
                            <input type="email" name="email" x-model="form.email" required placeholder="admin@domain.com" class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Phone Number</label>
                            <input type="text" name="phone" x-model="form.phone" placeholder="+91 9876543210" class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Administrative Role *</label>
                            <select name="role" x-model="form.role" required class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5">
                                @foreach($roles as $r)
                                    <option value="{{ $r->name }}">{{ ucfirst(str_replace('_', ' ', $r->name)) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Account Status</label>
                            <select name="status" x-model="form.status" class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5">
                                <option value="active">Active</option>
                                <option value="suspended">Suspended</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1" x-text="isEditing ? 'New Password (Leave blank to keep current)' : 'Password *'"></label>
                            <div class="relative rounded-lg shadow-xs" x-data="{ showPass: false }">
                                <input :type="showPass ? 'text' : 'password'" type="password" name="password" :required="!isEditing" placeholder="••••••••" class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 pr-10 focus:ring-1 focus:ring-indigo-500">
                                <button 
                                    type="button" 
                                    @click.prevent="showPass = !showPass" 
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300 focus:outline-none cursor-pointer"
                                    tabindex="-1"
                                    :aria-label="showPass ? 'Hide password' : 'Show password'"
                                    :title="showPass ? 'Hide password' : 'Show password'"
                                >
                                    <svg x-show="!showPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                    <svg x-show="showPass" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-800 flex justify-end space-x-2">
                        <button type="button" @click="closeModal()" class="px-4 py-2 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow-sm transition" x-text="isEditing ? 'Update Staff' : 'Create Staff'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
function staffManager() {
    return {
        isOpen: false,
        isEditing: false,
        formUrl: '{{ route("admin.staff.store") }}',
        form: {
            name: '',
            email: '',
            phone: '',
            role: 'admin',
            status: 'active'
        },
        openModal() {
            this.isEditing = false;
            this.formUrl = '{{ route("admin.staff.store") }}';
            this.form = {
                name: '',
                email: '',
                phone: '',
                role: 'admin',
                status: 'active'
            };
            this.isOpen = true;
        },
        editStaff(staff, currentRole) {
            this.isEditing = true;
            this.formUrl = `/admin/staff/${staff.id}`;
            let statusVal = 'active';
            if (staff.status && typeof staff.status === 'object' && staff.status.value) {
                statusVal = staff.status.value;
            } else if (typeof staff.status === 'string') {
                statusVal = staff.status;
            }
            this.form = {
                name: staff.name,
                email: staff.email,
                phone: staff.phone || '',
                role: currentRole || 'admin',
                status: statusVal
            };
            this.isOpen = true;
        },
        closeModal() {
            this.isOpen = false;
        }
    }
}
</script>
@endsection
