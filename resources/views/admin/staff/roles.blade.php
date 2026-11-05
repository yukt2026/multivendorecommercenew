@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Role & Permission Matrix'])

@section('content')
<div class="space-y-6 max-w-6xl mx-auto">

    <!-- Page Header -->
    <x-page-header 
        title="Role &amp; Permission Access Matrix" 
        subtitle="Configure granular platform capabilities and access authorizations across staff security roles."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Staff' => route('admin.staff.index'), 'Permissions Matrix' => '#']"
    >
        <x-slot name="actions">
            <a 
                href="{{ route('admin.staff.index') }}" 
                class="px-4 py-2 border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold transition"
            >
                Back to Staff Accounts
            </a>
        </x-slot>
    </x-page-header>

    <!-- Role Tabs & Permission Matrix -->
    @foreach($roles as $role)
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center space-x-3">
                    <span class="w-3 h-3 rounded-full {{ $role->name === 'super_admin' ? 'bg-rose-500' : 'bg-indigo-500' }}"></span>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white capitalize">
                            {{ str_replace('_', ' ', $role->name) }}
                        </h3>
                        <p class="text-xs text-slate-500">Guard: {{ $role->guard_name }} &bull; Assigned Permissions: {{ $role->permissions->count() }}</p>
                    </div>
                </div>

                @if($role->name === 'super_admin')
                    <span class="px-2.5 py-1 bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400 border border-rose-200 dark:border-rose-800 text-[10px] font-bold uppercase rounded-md">
                        All Access (Bypass)
                    </span>
                @endif
            </div>

            <form method="POST" action="{{ route('admin.staff.roles.update', $role) }}" class="space-y-6">
                @csrf
                @method('PUT')

                @php
                    $rolePermissionNames = $role->permissions->pluck('name')->flip();
                @endphp

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($permissions as $group => $perms)
                        <div class="bg-slate-50 dark:bg-slate-800/40 rounded-xl p-4 border border-slate-200 dark:border-slate-800 space-y-3">
                            <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center justify-between">
                                <span>{{ ucfirst($group) }}</span>
                                <span class="text-[10px] text-slate-400 font-mono">{{ count($perms) }}</span>
                            </h4>

                            <div class="space-y-2 pt-1">
                                @foreach($perms as $p)
                                    <label class="flex items-start space-x-2.5 cursor-pointer text-xs">
                                        <input 
                                            type="checkbox" 
                                            name="permissions[]" 
                                            value="{{ $p->name }}"
                                            {{ isset($rolePermissionNames[$p->name]) || $role->name === 'super_admin' ? 'checked' : '' }}
                                            {{ $role->name === 'super_admin' ? 'disabled' : '' }}
                                            class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                        >
                                        <span class="text-slate-700 dark:text-slate-300 leading-tight">
                                            {{ $p->name }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($role->name !== 'super_admin')
                    <div class="flex justify-end pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button 
                            type="submit" 
                            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow-sm transition"
                        >
                            Save Permissions for {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                        </button>
                    </div>
                @endif
            </form>
        </div>
    @endforeach

</div>
@endsection
