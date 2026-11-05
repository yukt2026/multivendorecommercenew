@php
    $defaultVariant = $product->variants->firstWhere('is_default', true) ?: $product->variants->first();
    $sellingPrice = $defaultVariant?->selling_price ?: 0.00;
    $mrp = $defaultVariant?->mrp ?: $sellingPrice;
    $discountPct = $mrp > $sellingPrice ? round((($mrp - $sellingPrice) / $mrp) * 100) : 0;
    $imagePath = $product->media->first()?->path;
@endphp

<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs hover:shadow-md transition flex flex-col justify-between group">
    <div>
        <!-- Image & Quick Badge Container -->
        <div class="relative w-full h-44 bg-slate-100 flex items-center justify-center overflow-hidden">
            @if($imagePath)
                <img src="{{ asset('storage/' . $imagePath) }}" alt="{{ $product->title }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" />
            @else
                <div class="text-slate-300">
                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
            @endif

            @if($product->is_quick_capable)
                <span class="absolute top-2 left-2 px-2 py-0.5 rounded-md text-[10px] font-extrabold bg-amber-400 text-slate-950 shadow-xs flex items-center space-x-1">
                    <span>⚡ 15 MINS</span>
                </span>
            @endif

            @if($discountPct > 0)
                <span class="absolute top-2 right-2 px-1.5 py-0.5 rounded-md text-[10px] font-bold bg-emerald-600 text-white shadow-xs">
                    {{ $discountPct }}% OFF
                </span>
            @endif
        </div>

        <!-- Product Information -->
        <div class="p-3.5 space-y-1">
            @if($product->brand)
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block truncate">
                    {{ $product->brand->name }}
                </span>
            @endif

            <h3 class="text-xs font-bold text-slate-900 group-hover:text-indigo-600 transition line-clamp-2">
                {{ $product->title }}
            </h3>

            @if($defaultVariant?->name)
                <span class="text-[11px] text-slate-500 font-medium block">
                    {{ $defaultVariant->name }}
                </span>
            @endif
        </div>
    </div>

    <!-- Pricing & Action Bar -->
    <div class="p-3.5 pt-0 border-t border-slate-100 flex items-center justify-between">
        <div>
            <div class="text-sm font-extrabold text-slate-900">
                {{ format_currency((float) $sellingPrice) }}
            </div>
            @if($mrp > $sellingPrice)
                <div class="text-[10px] text-slate-400 line-through">
                    {{ format_currency((float) $mrp) }}
                </div>
            @endif
        </div>

        <button 
            type="button" 
            class="px-3 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white font-bold text-xs border border-indigo-200 hover:border-indigo-600 transition shadow-xs"
        >
            Add +
        </button>
    </div>
</div>
