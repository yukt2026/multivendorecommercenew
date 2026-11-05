@props([
    'src' => null,
    'name' => 'User',
    'size' => 'md', // xs, sm, md, lg, xl
    'status' => null, // online, offline, busy
    'rounded' => 'full', // full, xl
])

@php
    $sizeMap = [
        'xs' => ['box' => 'h-6 w-6 text-[10px]', 'dot' => 'h-1.5 w-1.5'],
        'sm' => ['box' => 'h-8 w-8 text-xs', 'dot' => 'h-2 w-2'],
        'md' => ['box' => 'h-10 w-10 text-sm font-bold', 'dot' => 'h-2.5 w-2.5'],
        'lg' => ['box' => 'h-12 w-12 text-base font-bold', 'dot' => 'h-3 w-3'],
        'xl' => ['box' => 'h-16 w-16 text-xl font-bold', 'dot' => 'h-3.5 w-3.5'],
    ];

    $statusColor = match ($status) {
        'online' => 'bg-emerald-500 ring-white dark:ring-slate-900',
        'busy' => 'bg-rose-500 ring-white dark:ring-slate-900',
        'offline' => 'bg-slate-400 ring-white dark:ring-slate-900',
        default => null,
    };

    $dimensions = $sizeMap[$size] ?? $sizeMap['md'];
    $roundedClass = $rounded === 'full' ? 'rounded-full' : 'rounded-2xl';

    // Generate initials from name
    $words = explode(' ', trim($name));
    $initials = '';
    foreach (array_slice($words, 0, 2) as $w) {
        $initials .= strtoupper(substr($w, 0, 1));
    }
    if (empty($initials)) $initials = 'OS';
@endphp

<div class="relative inline-block shrink-0">
    @if($src)
        <img 
            src="{{ $src }}" 
            alt="{{ $name }}" 
            class="{{ $dimensions['box'] }} {{ $roundedClass }} object-cover border border-slate-200 dark:border-slate-800 shadow-xs"
        />
    @else
        <div class="{{ $dimensions['box'] }} {{ $roundedClass }} bg-gradient-to-tr from-indigo-600 to-indigo-400 text-white flex items-center justify-center font-bold tracking-tight shadow-xs select-none">
            {{ $initials }}
        </div>
    @endif

    @if($statusColor)
        <span class="absolute bottom-0 right-0 block {{ $dimensions['dot'] }} {{ $statusColor }} ring-2 rounded-full"></span>
    @endif
</div>
