@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Platform Settings'])

@section('content')
<div class="space-y-6 max-w-5xl">
    <!-- Page Header -->
    <x-page-header 
        title="Platform Settings & Domain Defaults" 
        subtitle="Dynamic configuration values stored in database. Updates invalidate cache automatically."
        :breadcrumbs="['Command Central' => route('admin.dashboard'), 'Settings' => route('admin.settings.index')]"
    >
        <x-slot name="actions">
            <x-button variant="outline" size="sm" href="{{ route('admin.dashboard') }}">
                &larr; Back to Dashboard
            </x-button>
        </x-slot>
    </x-page-header>

    <!-- Settings Form -->
    <form action="{{ route('admin.settings.update') }}" method="POST" class="space-y-8">
        @csrf

        @foreach($settings as $group => $items)
            <x-card 
                title="{{ ucfirst($group) }} Domain Configuration" 
                subtitle="Settings namespace group: {{ $group }}"
            >
                <x-slot name="action">
                    <x-badge variant="neutral" size="sm">{{ count($items) }} Keys</x-badge>
                </x-slot>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach($items as $setting)
                        <div class="space-y-1.5">
                            @if($setting->type === 'boolean')
                                <x-select 
                                    label="{{ $setting->label ?? $setting->key }}"
                                    name="settings[{{ $setting->key }}]"
                                    helper="{{ $setting->description }}"
                                    :options="['1' => 'Enabled (True)', '0' => 'Disabled (False)']"
                                    selected="{{ $setting->value }}"
                                />
                            @elseif($setting->type === 'json' || $setting->type === 'array' || $setting->type === 'text')
                                <x-textarea 
                                    label="{{ $setting->label ?? $setting->key }}"
                                    name="settings[{{ $setting->key }}]"
                                    helper="{{ $setting->description }}"
                                    rows="4"
                                    value="{{ is_array($setting->castValue()) ? json_encode($setting->castValue(), JSON_PRETTY_PRINT) : $setting->value }}"
                                />
                            @else
                                <x-input 
                                    label="{{ $setting->label ?? $setting->key }}"
                                    name="settings[{{ $setting->key }}]"
                                    helper="{{ $setting->description }}"
                                    value="{{ $setting->value }}"
                                />
                            @endif

                            <div class="text-[10px] text-slate-400 font-mono flex items-center justify-between pt-1">
                                <span>Key: {{ $setting->key }}</span>
                                <span>Type: {{ $setting->type }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>
        @endforeach

        <div class="flex justify-end pt-4">
            <x-button type="submit" variant="primary" size="md">
                Save Changes & Clear Cache
            </x-button>
        </div>
    </form>
</div>
@endsection
