@props([
    'title' => null,
    'subtitle' => null,
    'action' => null,
    'footer' => null,
    'padding' => 'p-6',
    'variant' => 'default', // default, bordered, glass, flat
])

@php
    $variantClasses = [
        'default' => 'bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm dark:shadow-xl',
        'bordered' => 'bg-white dark:bg-slate-900 border-2 border-slate-300 dark:border-slate-700',
        'glass' => 'bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border border-slate-200/60 dark:border-slate-800/60 shadow-lg',
        'flat' => 'bg-slate-50 dark:bg-slate-850 border border-transparent',
    ];
    $cardStyle = $variantClasses[$variant] ?? $variantClasses['default'];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-2xl transition-all duration-200 ' . $cardStyle]) }}>
    @if($title || $subtitle || $action)
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800/80 flex items-center justify-between gap-4">
            <div>
                @if($title)
                    <h3 class="text-base font-bold text-slate-900 dark:text-white tracking-tight">{{ $title }}</h3>
                @endif
                @if($subtitle)
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 leading-normal">{{ $subtitle }}</p>
                @endif
            </div>
            @if($action)
                <div class="flex items-center space-x-2 shrink-0">{{ $action }}</div>
            @endif
        </div>
    @endif

    <div class="{{ $padding }}">
        {{ $slot }}
    </div>

    @if($footer)
        <div class="px-6 py-3.5 bg-slate-50/70 dark:bg-slate-950/40 rounded-b-2xl border-t border-slate-100 dark:border-slate-800/80 text-xs text-slate-600 dark:text-slate-400 flex items-center justify-between">
            {{ $footer }}
        </div>
    @endif
</div>
