@props([
    'type' => 'block', // block, text, circle, card, table
    'lines' => 3,
    'width' => 'w-full',
    'height' => 'h-5',
])

@if($type === 'circle')
    <div {{ $attributes->merge(['class' => 'rounded-full animate-pulse bg-slate-200 dark:bg-slate-800 ' . $width . ' ' . $height]) }}></div>
@elseif($type === 'text')
    <div class="space-y-2.5 w-full">
        @for($i = 0; $i < $lines; $i++)
            <div 
                class="rounded-lg animate-pulse bg-slate-200 dark:bg-slate-800 h-3.5 {{ $i === $lines - 1 ? 'w-2/3' : 'w-full' }}"
            ></div>
        @endfor
    </div>
@elseif($type === 'card')
    <div {{ $attributes->merge(['class' => 'p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-4 animate-pulse']) }}>
        <div class="h-4 bg-slate-200 dark:bg-slate-800 rounded-md w-1/3"></div>
        <div class="h-8 bg-slate-200 dark:bg-slate-800 rounded-lg w-1/2"></div>
        <div class="space-y-2 pt-2">
            <div class="h-3 bg-slate-200 dark:bg-slate-800 rounded w-full"></div>
            <div class="h-3 bg-slate-200 dark:bg-slate-800 rounded w-4/5"></div>
        </div>
    </div>
@elseif($type === 'table')
    <div class="w-full rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden bg-white dark:bg-slate-900 p-4 space-y-3 animate-pulse">
        <div class="h-5 bg-slate-200 dark:bg-slate-800 rounded w-full"></div>
        <div class="h-4 bg-slate-100 dark:bg-slate-850 rounded w-full"></div>
        <div class="h-4 bg-slate-100 dark:bg-slate-850 rounded w-full"></div>
        <div class="h-4 bg-slate-100 dark:bg-slate-850 rounded w-full"></div>
    </div>
@else
    <div {{ $attributes->merge(['class' => 'rounded-xl animate-pulse bg-slate-200 dark:bg-slate-800 ' . $width . ' ' . $height]) }}></div>
@endif
