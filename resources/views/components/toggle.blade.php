@props([
    'label' => null,
    'description' => null,
    'name' => null,
    'id' => null,
    'checked' => false,
    'disabled' => false,
    'color' => 'indigo', // indigo, emerald, amber
])

@php
    $id = $id ?? ($name ?? 'toggle-' . \Illuminate\Support\Str::random(6));
    $initialState = old($name, $checked) ? 'true' : 'false';
@endphp

<div class="flex items-center justify-between" x-data="{ enabled: {{ $initialState }} }">
    @if($label || $description)
        <div class="flex flex-col mr-4">
            @if($label)
                <span class="text-xs font-semibold text-slate-800 dark:text-slate-200" id="{{ $id }}-label">
                    {{ $label }}
                </span>
            @endif
            @if($description)
                <span class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ $description }}
                </span>
            @endif
        </div>
    @endif

    @if($name)
        <input type="hidden" name="{{ $name }}" :value="enabled ? '1' : '0'" />
    @endif

    <button 
        type="button"
        role="switch"
        :aria-checked="enabled.toString()"
        aria-labelledby="{{ $id }}-label"
        @if(!$disabled) @click="enabled = !enabled" @endif
        :class="enabled ? 'bg-indigo-600 dark:bg-indigo-500' : 'bg-slate-200 dark:bg-slate-700'"
        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900 {{ $disabled ? 'opacity-50 cursor-not-allowed' : '' }}"
        {{ $attributes }}
    >
        <span 
            aria-hidden="true" 
            :class="enabled ? 'translate-x-5' : 'translate-x-0'"
            class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out"
        ></span>
    </button>
</div>
