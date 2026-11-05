@props([
    'headers' => [],
    'striped' => false,
    'compact' => false,
])

<div class="w-full overflow-hidden rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm">
            @if(count($headers) > 0 || isset($thead))
                <thead class="bg-slate-50/80 dark:bg-slate-950/60 text-slate-500 dark:text-slate-400 uppercase text-[11px] font-bold tracking-wider border-b border-slate-200 dark:border-slate-800">
                    @if(isset($thead))
                        {{ $thead }}
                    @else
                        <tr>
                            @foreach($headers as $key => $header)
                                <th scope="col" class="{{ $compact ? 'px-4 py-2.5' : 'px-6 py-3.5' }} font-bold">
                                    {{ is_array($header) ? ($header['label'] ?? $key) : $header }}
                                </th>
                            @endforeach
                        </tr>
                    @endif
                </thead>
            @endif

            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 {{ $striped ? '[&>tr:nth-child(even)]:bg-slate-50/50 dark:[&>tr:nth-child(even)]:bg-slate-850/40' : '' }} text-slate-700 dark:text-slate-300">
                {{ $slot }}
            </tbody>
        </table>
    </div>

    @if(isset($footer))
        <div class="px-6 py-3 bg-slate-50/80 dark:bg-slate-950/60 border-t border-slate-200 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400">
            {{ $footer }}
        </div>
    @endif
</div>
