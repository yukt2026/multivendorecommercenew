@props([
    'paginator' => null,
])

@if ($paginator && ($paginator instanceof \Illuminate\Pagination\AbstractPaginator || $paginator instanceof \Illuminate\Pagination\LengthAwarePaginator) && $paginator->hasPages())
    @php
        $elements = [];
        if ($paginator instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $window = \Illuminate\Pagination\UrlWindow::make($paginator);
            $elements = array_filter([
                $window['first'],
                is_array($window['slider']) ? '...' : null,
                $window['slider'],
                is_array($window['last']) ? '...' : null,
                $window['last'],
            ]);
        }
    @endphp
    <nav role="navigation" aria-label="Pagination Navigation" class="flex items-center justify-between px-4 py-3 sm:px-6">
        <div class="flex justify-between flex-1 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="relative inline-flex items-center px-4 py-2 text-xs font-medium text-slate-400 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 cursor-not-allowed rounded-lg">
                    &laquo; Previous
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="relative inline-flex items-center px-4 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800">
                    &laquo; Previous
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="relative inline-flex items-center px-4 py-2 ml-3 text-xs font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800">
                    Next &raquo;
                </a>
            @else
                <span class="relative inline-flex items-center px-4 py-2 ml-3 text-xs font-medium text-slate-400 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 cursor-not-allowed rounded-lg">
                    Next &raquo;
                </span>
            @endif
        </div>

        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-xs text-slate-600 dark:text-slate-400">
                    Showing
                    <span class="font-bold text-slate-900 dark:text-white">{{ method_exists($paginator, 'firstItem') ? $paginator->firstItem() : 1 }}</span>
                    to
                    <span class="font-bold text-slate-900 dark:text-white">{{ method_exists($paginator, 'lastItem') ? $paginator->lastItem() : $paginator->count() }}</span>
                    of
                    <span class="font-bold text-slate-900 dark:text-white">{{ method_exists($paginator, 'total') ? $paginator->total() : $paginator->count() }}</span>
                    results
                </p>
            </div>

            <div>
                <span class="relative z-0 inline-flex rounded-xl shadow-xs -space-x-px">
                    {{-- Pagination Elements --}}
                    @foreach ($elements as $element)
                        {{-- "Three Dots" Separator --}}
                        @if (is_string($element))
                            <span aria-disabled="true">
                                <span class="relative inline-flex items-center px-3.5 py-2 text-xs font-medium text-slate-500 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700">{{ $element }}</span>
                            </span>
                        @endif

                        {{-- Array Of Links --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page">
                                        <span class="relative inline-flex items-center px-3.5 py-2 text-xs font-bold text-white bg-indigo-600 border border-indigo-600 z-10">{{ $page }}</span>
                                    </span>
                                @else
                                    <a href="{{ $url }}" class="relative inline-flex items-center px-3.5 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                                        {{ $page }}
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach
                </span>
            </div>
        </div>
    </nav>
@else
    {{-- Demo visual presentation for static UI kit preview --}}
    <div class="flex items-center justify-between px-4 py-3 bg-white dark:bg-slate-900 border-t border-slate-200/80 dark:border-slate-800 sm:px-6">
        <div class="text-xs text-slate-500 dark:text-slate-400">
            Showing <span class="font-bold text-slate-900 dark:text-white">1</span> to <span class="font-bold text-slate-900 dark:text-white">10</span> of <span class="font-bold text-slate-900 dark:text-white">96</span> results
        </div>
        <div class="inline-flex rounded-xl shadow-xs -space-x-px text-xs">
            <button class="px-3 py-1.5 rounded-l-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">&laquo;</button>
            <button class="px-3 py-1.5 border border-indigo-600 bg-indigo-600 text-white font-bold">1</button>
            <button class="px-3 py-1.5 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">2</button>
            <button class="px-3 py-1.5 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">3</button>
            <span class="px-3 py-1.5 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-400">...</span>
            <button class="px-3 py-1.5 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">10</button>
            <button class="px-3 py-1.5 rounded-r-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">&raquo;</button>
        </div>
    </div>
@endif
