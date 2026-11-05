@props([
    'label',
    'value',
    'change' => null,
    'changeType' => 'up', // up, down, neutral
    'icon' => null,
    'badge' => null,
    'color' => 'primary', // primary, emerald, amber, rose, sky
])

@php
    $colorClasses = [
        'primary' => [
            'icon' => 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-800/40',
            'badge' => 'bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800/60',
        ],
        'emerald' => [
            'icon' => 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-800/40',
            'badge' => 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800/60',
        ],
        'amber' => [
            'icon' => 'bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 border border-amber-100 dark:border-amber-800/40',
            'badge' => 'bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800/60',
        ],
        'rose' => [
            'icon' => 'bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 border border-rose-100 dark:border-rose-800/40',
            'badge' => 'bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800/60',
        ],
        'sky' => [
            'icon' => 'bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 border border-sky-100 dark:border-sky-800/40',
            'badge' => 'bg-sky-50 dark:bg-sky-950/50 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-800/60',
        ],
    ];
    $selectedColor = $colorClasses[$color] ?? $colorClasses['primary'];
@endphp

<div {{ $attributes->merge(['class' => 'p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm dark:shadow-xl transition-all duration-200 hover:shadow-md hover:border-slate-300 dark:hover:border-slate-700/80 relative overflow-hidden flex flex-col justify-between']) }}>
    <div>
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $label }}</span>
            @if($badge)
                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full border {{ $selectedColor['badge'] }}">
                    {{ $badge }}
                </span>
            @elseif(isset($iconSlot) || $icon)
                <div class="w-8 h-8 rounded-xl flex items-center justify-center text-sm {{ $selectedColor['icon'] }}">
                    @if(isset($iconSlot))
                        {{ $iconSlot }}
                    @else
                        {{ $icon }}
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-baseline justify-between gap-2">
            <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                {{ $value }}
            </div>

            @if($change !== null)
                <div class="flex items-center text-xs font-bold {{ $changeType === 'up' ? 'text-emerald-600 dark:text-emerald-400' : ($changeType === 'down' ? 'text-rose-600 dark:text-rose-400' : 'text-slate-500 dark:text-slate-400') }}">
                    @if($changeType === 'up')
                        <svg class="w-3.5 h-3.5 mr-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                        </svg>
                    @elseif($changeType === 'down')
                        <svg class="w-3.5 h-3.5 mr-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                        </svg>
                    @endif
                    <span>{{ $change }}</span>
                </div>
            @endif
        </div>
    </div>

    @if(isset($sparkline) && $sparkline)
        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800/80">
            {{ $sparkline }}
        </div>
    @endif
</div>
