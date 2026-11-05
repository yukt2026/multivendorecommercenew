@props([
    'status',
    'size' => 'md',
])

@php
    $val = $status instanceof \App\Enums\OrderStatus ? $status->value : (string) $status;
    $label = $status instanceof \App\Enums\OrderStatus ? $status->label() : ucwords(str_replace('_', ' ', $val));

    $colorClasses = match(strtolower($val)) {
        'placed' => 'bg-sky-100 text-sky-800 border-sky-300 dark:bg-sky-950/60 dark:text-sky-300 dark:border-sky-800',
        'confirmed', 'store_accepted' => 'bg-blue-100 text-blue-800 border-blue-300 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800',
        'processing', 'packing' => 'bg-indigo-100 text-indigo-800 border-indigo-300 dark:bg-indigo-950/60 dark:text-indigo-300 dark:border-indigo-800',
        'ready_for_pickup', 'ready', 'ready_to_ship' => 'bg-purple-100 text-purple-800 border-purple-300 dark:bg-purple-950/60 dark:text-purple-300 dark:border-purple-800',
        'rider_assigned', 'in_transit', 'picked_up', 'out_for_delivery' => 'bg-amber-100 text-amber-900 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800',
        'delivered' => 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800',
        'cancelled', 'rejected' => 'bg-rose-100 text-rose-800 border-rose-300 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800',
        'refunded' => 'bg-slate-100 text-slate-800 border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
        default => 'bg-slate-100 text-slate-800 border-slate-300 dark:bg-slate-800 dark:text-slate-300',
    };

    $sizeClasses = match($size) {
        'sm' => 'px-2 py-0.5 text-[10px]',
        'lg' => 'px-3.5 py-1.5 text-xs font-black',
        default => 'px-2.5 py-1 text-xs font-bold',
    };
@endphp

<span class="inline-flex items-center rounded-full border {{ $colorClasses }} {{ $sizeClasses }} font-bold">
    @if(in_array(strtolower($val), ['placed', 'processing', 'packing', 'in_transit', 'out_for_delivery']))
        <span class="w-1.5 h-1.5 rounded-full bg-current mr-1.5 animate-pulse opacity-75"></span>
    @endif
    <span>{{ $label }}</span>
</span>
