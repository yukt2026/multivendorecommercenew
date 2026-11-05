@props([
    'title',
    'subtitle' => null,
    'breadcrumbs' => [],
])

<div {{ $attributes->merge(['class' => 'pb-6 mb-6 border-b border-slate-200/80 dark:border-slate-800 flex flex-col md:flex-row md:items-center md:justify-between gap-4']) }}>
    <div class="space-y-1">
        @if(count($breadcrumbs) > 0 || isset($breadcrumbsSlot))
            <nav class="flex items-center space-x-1.5 text-xs text-slate-500 dark:text-slate-400 mb-2">
                @if(isset($breadcrumbsSlot))
                    {{ $breadcrumbsSlot }}
                @else
                    @foreach($breadcrumbs as $label => $url)
                        @if(!$loop->last)
                            <a href="{{ $url }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition">{{ $label }}</a>
                            <span class="text-slate-400 dark:text-slate-600">/</span>
                        @else
                            <span class="text-slate-800 dark:text-slate-200 font-semibold">{{ $label }}</span>
                        @endif
                    @endforeach
                @endif
            </nav>
        @endif

        <div class="flex items-center space-x-3">
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                {{ $title }}
            </h1>
            @if(isset($badge))
                <div>{{ $badge }}</div>
            @endif
        </div>

        @if($subtitle)
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-normal">{{ $subtitle }}</p>
        @endif
    </div>

    @if(isset($actions) || $slot->isNotEmpty())
        <div class="flex items-center space-x-3 shrink-0">
            {{ $actions ?? $slot }}
        </div>
    @endif
</div>
