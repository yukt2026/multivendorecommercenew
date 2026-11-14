@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'helper' => null,
    'error' => null,
    'required' => false,
    'disabled' => false,
    'prefixIcon' => null,
    'suffixIcon' => null,
    'showPasswordToggle' => true,
])

@php
    $id = $id ?? ($name ?? 'input-' . \Illuminate\Support\Str::random(6));
    $hasError = $error || ($name && $errors->has($name));
    $errorMessage = $error ?? ($name ? $errors->first($name) : null);
    $isPassword = ($type === 'password') && $showPasswordToggle;
@endphp

<div class="space-y-1.5 w-full">
    @if($label)
        <div class="flex items-center justify-between">
            <label for="{{ $id }}" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                {{ $label }}
                @if($required)
                    <span class="text-rose-500">*</span>
                @endif
            </label>
        </div>
    @endif

    <div class="relative rounded-xl shadow-xs" @if($isPassword) x-data="{ showPassword: false }" @endif>
        @if($prefixIcon)
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 dark:text-slate-500">
                {{ $prefixIcon }}
            </div>
        @endif

        <input 
            type="{{ $type }}"
            @if($isPassword) :type="showPassword ? 'text' : 'password'" @endif
            name="{{ $name }}"
            id="{{ $id }}"
            value="{{ old($name, $value) }}"
            placeholder="{{ $placeholder }}"
            @if($required) required @endif
            @if($disabled) disabled @endif
            {{ $attributes->merge([
                'class' => 'w-full block rounded-xl text-sm transition-all duration-150 ' . 
                ($prefixIcon ? 'pl-10 ' : 'pl-3.5 ') . 
                (($suffixIcon || $isPassword) ? 'pr-10 ' : 'pr-3.5 ') . 
                'py-2.5 bg-white dark:bg-slate-900 border ' . 
                ($hasError 
                    ? 'border-rose-400 dark:border-rose-500 focus:border-rose-500 focus:ring-rose-500 text-rose-900 dark:text-rose-100 placeholder-rose-300' 
                    : 'border-slate-300 dark:border-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500') . 
                ($disabled ? ' bg-slate-100 dark:bg-slate-800 text-slate-400 cursor-not-allowed opacity-75' : '')
            ]) }}
        />

        @if($isPassword && !$suffixIcon)
            <button 
                type="button" 
                @click.prevent="showPassword = !showPassword" 
                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300 focus:outline-none transition-colors cursor-pointer"
                tabindex="-1"
                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                :title="showPassword ? 'Hide password' : 'Show password'"
            >
                <svg x-show="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
                <svg x-show="showPassword" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                </svg>
            </button>
        @elseif($suffixIcon)
            <div class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-slate-400 dark:text-slate-500">
                {{ $suffixIcon }}
            </div>
        @endif
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
