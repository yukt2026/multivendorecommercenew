@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => null,
    'helper' => null,
    'error' => null,
    'required' => false,
    'disabled' => false,
])

@php
    $id = $id ?? ($name ?? 'select-' . \Illuminate\Support\Str::random(6));
    $hasError = $error || ($name && $errors->has($name));
    $errorMessage = $error ?? ($name ? $errors->first($name) : null);
    $currentValue = old($name, $selected);
@endphp

<div class="space-y-1.5 w-full">
    @if($label)
        <label for="{{ $id }}" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
            {{ $label }}
            @if($required)
                <span class="text-rose-500">*</span>
            @endif
        </label>
    @endif

    <div class="relative">
        <select 
            name="{{ $name }}"
            id="{{ $id }}"
            @if($required) required @endif
            @if($disabled) disabled @endif
            {{ $attributes->merge([
                'class' => 'w-full block rounded-xl text-sm py-2.5 pl-3.5 pr-10 bg-white dark:bg-slate-900 border transition-all duration-150 ' . 
                ($hasError 
                    ? 'border-rose-400 dark:border-rose-500 focus:border-rose-500 focus:ring-rose-500 text-rose-900 dark:text-rose-100' 
                    : 'border-slate-300 dark:border-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 text-slate-900 dark:text-slate-100') . 
                ($disabled ? ' bg-slate-100 dark:bg-slate-800 text-slate-400 cursor-not-allowed opacity-75' : '')
            ]) }}
        >
            @if($placeholder)
                <option value="" disabled {{ $currentValue === null || $currentValue === '' ? 'selected' : '' }}>
                    {{ $placeholder }}
                </option>
            @endif

            @if(count($options) > 0)
                @foreach($options as $val => $optLabel)
                    <option value="{{ $val }}" {{ (string)$currentValue === (string)$val ? 'selected' : '' }}>
                        {{ $optLabel }}
                    </option>
                @endforeach
            @else
                {{ $slot }}
            @endif
        </select>
    </div>

    @if($hasError)
        <p class="text-xs text-rose-600 dark:text-rose-400 font-medium flex items-center space-x-1 mt-1">
            <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <span>{{ $errorMessage }}</span>
        </p>
    @elseif($helper)
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $helper }}</p>
    @endif
</div>
