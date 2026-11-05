@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'System Settings'])

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">

    <!-- Page Header -->
    <x-page-header 
        title="Global Platform Settings" 
        subtitle="Configure application parameters, payment keys, notification providers, SEO, and fulfilment logic."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'System Settings' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                Configuration Engine
            </span>
        </x-slot>
    </x-page-header>

    <!-- Tabbed Settings Layout -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        
        <!-- Tab Bar -->
        <div class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 p-2 overflow-x-auto flex items-center space-x-1">
            @foreach($groups as $grpKey => $grpTitle)
                <a 
                    href="{{ route('admin.settings.index', ['tab' => $grpKey]) }}" 
                    class="px-4 py-2 rounded-lg text-xs font-semibold whitespace-nowrap transition {{ $currentTab === $grpKey ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}"
                >
                    {{ $grpTitle }}
                </a>
            @endforeach
        </div>

        <!-- Tab Body Form -->
        <form method="POST" action="{{ route('admin.settings.update') }}" class="p-6 space-y-6">
            @csrf

            @php
                $tabSettings = $settings->get($currentTab, collect());
            @endphp

            @if($tabSettings->isEmpty())
                <div class="py-12 text-center text-slate-400 text-xs">
                    No configurable settings found under {{ $groups[$currentTab] ?? $currentTab }}.
                </div>
            @else
                <div class="space-y-4 divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($tabSettings as $setting)
                        <div class="pt-4 first:pt-0 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                            <div>
                                <label class="text-xs font-bold text-slate-900 dark:text-white block">
                                    {{ $setting->label ?: ucwords(str_replace('_', ' ', $setting->key)) }}
                                </label>
                                <span class="font-mono text-[10px] text-slate-400 block mt-0.5">
                                    {{ $setting->key }}
                                </span>
                            </div>

                            <div class="md:col-span-2">
                                @if($setting->type === 'boolean')
                                    <select 
                                        name="settings[{{ $setting->key }}]" 
                                        class="text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 max-w-xs w-full"
                                    >
                                        <option value="1" {{ $setting->value ? 'selected' : '' }}>Enabled / True</option>
                                        <option value="0" {{ !$setting->value ? 'selected' : '' }}>Disabled / False</option>
                                    </select>
                                @elseif($setting->type === 'textarea')
                                    <textarea 
                                        name="settings[{{ $setting->key }}]" 
                                        rows="3" 
                                        class="text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 w-full focus:ring-1 focus:ring-indigo-500 font-mono"
                                    >{{ is_array($setting->value) ? json_encode($setting->value, JSON_PRETTY_PRINT) : $setting->value }}</textarea>
                                @elseif($setting->type === 'integer' || $setting->type === 'numeric' || $setting->type === 'float')
                                    <input 
                                        type="number" 
                                        step="any" 
                                        name="settings[{{ $setting->key }}]" 
                                        value="{{ $setting->value }}" 
                                        class="text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 max-w-xs w-full focus:ring-1 focus:ring-indigo-500"
                                    >
                                @else
                                    <input 
                                        type="text" 
                                        name="settings[{{ $setting->key }}]" 
                                        value="{{ is_array($setting->value) ? json_encode($setting->value) : $setting->value }}" 
                                        class="text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 w-full focus:ring-1 focus:ring-indigo-500"
                                    >
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end pt-6 border-t border-slate-100 dark:border-slate-800">
                    <button 
                        type="submit" 
                        class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow-sm transition"
                    >
                        Save {{ $groups[$currentTab] ?? 'Settings' }}
                    </button>
                </div>
            @endif
        </form>

    </div>

</div>
@endsection
