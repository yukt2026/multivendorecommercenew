@props([
    'label' => null,
    'description' => null,
    'name' => null,
    'id' => null,
    'value' => '1',
    'checked' => false,
    'disabled' => false,
    'error' => null,
])

@php
    $id = $id ?? ($name ?? 'checkbox-' . \Illuminate\Support\Str::random(6));
    $hasError = $error || ($name && $errors->has($name));
@endphp

<div class="flex items-start">
    <div class="flex items-center h-5">
        <input 
            type="checkbox"
            name="{{ $name }}"
            id="{{ $id }}"
            value="{{ $value }}"
            @if(old($name, $checked)) checked @endif
            @if($disabled) disabled @endif
            {{ $attributes->merge([
                'class' => 'h-4 w-4 rounded-md border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-indigo-600 focus:ring-indigo-500/30 transition-all ' . 
                ($hasError ? 'border-rose-400 focus:ring-rose-400' : '') .
                ($disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer')
            ]) }}
        />
    </div>

    @if($label || $description)
        <div class="ml-2.5 text-xs">
            @if($label)
                <label for="{{ $id }}" class="font-semibold text-slate-800 dark:text-slate-200 select-none {{ $disabled ? 'opacity-50' : 'cursor-pointer' }}">
                    {{ $label }}
                </label>
            @endif
            @if($description)
                <p class="text-slate-500 dark:text-slate-400 mt-0.5">{{ $description }}</p>
            @endif
        </div>
    @endif
</div>
