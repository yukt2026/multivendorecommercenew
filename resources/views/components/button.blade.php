@props([
    'variant' => 'primary', // primary, secondary, danger, ghost, amber, outline
    'size' => 'md', // sm, md, lg
    'type' => 'button',
    'loading' => false,
    'href' => null,
    'icon' => null,
    'iconTrailing' => null,
])

@php
    $variantClasses = [
        'primary' => 'bg-indigo-600 hover:bg-indigo-500 text-white shadow-sm shadow-indigo-600/30 focus:ring-indigo-500 border border-transparent dark:bg-indigo-600 dark:hover:bg-indigo-500',
        'secondary' => 'bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 focus:ring-slate-400',
        'danger' => 'bg-rose-600 hover:bg-rose-500 text-white shadow-sm shadow-rose-600/30 focus:ring-rose-500 border border-transparent',
        'ghost' => 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800 border border-transparent focus:ring-slate-400',
        'amber' => 'bg-amber-400 hover:bg-amber-300 text-slate-950 font-bold shadow-sm shadow-amber-400/20 border border-amber-500/30 focus:ring-amber-400',
        'outline' => 'bg-transparent text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800/60 border border-slate-300 dark:border-slate-700 focus:ring-indigo-500',
    ];

    $sizeClasses = [
        'sm' => 'px-3 py-1.5 text-xs rounded-lg gap-1.5',
        'md' => 'px-4 py-2 text-sm rounded-xl gap-2',
        'lg' => 'px-6 py-2.5 text-base rounded-xl gap-2.5',
    ];

    $classes = 'inline-flex items-center justify-center font-medium transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-slate-950 disabled:opacity-50 disabled:pointer-events-none active:scale-[0.98] cursor-pointer ' . ($variantClasses[$variant] ?? $variantClasses['primary']) . ' ' . ($sizeClasses[$size] ?? $sizeClasses['md']);
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)
            <span class="shrink-0">{{ $icon }}</span>
        @endif
        <span>{{ $slot }}</span>
        @if($iconTrailing)
            <span class="shrink-0">{{ $iconTrailing }}</span>
        @endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }} @if($loading) disabled @endif>
        @if($loading)
            <svg class="animate-spin -ml-0.5 h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        @elseif($icon)
            <span class="shrink-0">{{ $icon }}</span>
        @endif

        <span>{{ $slot }}</span>

        @if($iconTrailing)
            <span class="shrink-0">{{ $iconTrailing }}</span>
        @endif
    </button>
@endif
