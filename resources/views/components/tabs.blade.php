@props([
    'tabs' => [],
    'defaultTab' => null,
    'variant' => 'pills', // pills, underline
])

@php
    $firstKey = !empty($tabs) ? array_key_first($tabs) : '';
    $activeTab = $defaultTab ?? $firstKey;
@endphp

<div x-data="{ currentTab: '{{ $activeTab }}' }" class="w-full">
    <!-- Tab Headers -->
    <div class="{{ $variant === 'pills' ? 'p-1.5 bg-slate-100 dark:bg-slate-800/80 rounded-2xl inline-flex space-x-1 max-w-full overflow-x-auto border border-slate-200/60 dark:border-slate-700/60' : 'border-b border-slate-200 dark:border-slate-800 flex space-x-6' }}">
        @foreach($tabs as $tabId => $tabInfo)
            @php
                $tabTitle = is_array($tabInfo) ? ($tabInfo['label'] ?? $tabId) : $tabInfo;
                $tabBadge = is_array($tabInfo) ? ($tabInfo['badge'] ?? null) : null;
                $tabIcon = is_array($tabInfo) ? ($tabInfo['icon'] ?? null) : null;
            @endphp

            @if($variant === 'pills')
                <button
                    type="button"
                    @click="currentTab = '{{ $tabId }}'"
                    :class="currentTab === '{{ $tabId }}' 
                        ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-xs font-bold' 
                        : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-medium'"
                    class="px-4 py-2 text-xs rounded-xl transition-all duration-150 flex items-center space-x-2 shrink-0 cursor-pointer"
                >
                    @if($tabIcon)
                        <span>{!! $tabIcon !!}</span>
                    @endif
                    <span>{{ $tabTitle }}</span>
                    @if($tabBadge)
                        <span class="px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                            {{ $tabBadge }}
                        </span>
                    @endif
                </button>
            @else
                <button
                    type="button"
                    @click="currentTab = '{{ $tabId }}'"
                    :class="currentTab === '{{ $tabId }}' 
                        ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-bold' 
                        : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 hover:border-slate-300 font-medium'"
                    class="py-3 px-1 border-b-2 text-sm transition-all duration-150 flex items-center space-x-2 shrink-0 cursor-pointer"
                >
                    <span>{{ $tabTitle }}</span>
                    @if($tabBadge)
                        <span class="px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                            {{ $tabBadge }}
                        </span>
                    @endif
                </button>
            @endif
        @endforeach
    </div>

    <!-- Tab Panels Container -->
    <div class="mt-4">
        {{ $slot }}
    </div>
</div>
