@props([
    'variant' => 'primary', // primary, success, warning, danger, info, neutral
    'size' => 'md', // sm, md
    'dot' => false,
    'pulse' => false,
])

@php
    $variantMap = [
        'primary' => [
            'badge' => 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800/60',
            'dot' => 'bg-indigo-500',
        ],
        'success' => [
            'badge' => 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800/60',
            'dot' => 'bg-emerald-500',
        ],
        'warning' => [
            'badge' => 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800/60',
            'dot' => 'bg-amber-500',
        ],
        'danger' => [
            'badge' => 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800/60',
            'dot' => 'bg-rose-500',
        ],
        'info' => [
            'badge' => 'bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-800/60',
            'dot' => 'bg-sky-500',
        ],
        'neutral' => [
            'badge' => 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700',
            'dot' => 'bg-slate-500',
        ],
    ];

    $sizeClasses = [
        'sm' => 'px-2 py-0.5 text-[10px]',
        'md' => 'px-2.5 py-1 text-xs',
    ];

    $style = $variantMap[$variant] ?? $variantMap['primary'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center font-bold rounded-full border tracking-wide ' . $style['badge'] . ' ' . ($sizeClasses[$size] ?? $sizeClasses['md'])]) }}>
    @if($dot)
        <span class="mr-1.5 flex h-2 w-2 relative shrink-0">
            @if($pulse)
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $style['dot'] }} opacity-75"></span>
            @endif
            <span class="relative inline-flex rounded-full h-2 w-2 {{ $style['dot'] }}"></span>
        </span>
    @endif
    <span>{{ $slot }}</span>
</span>
