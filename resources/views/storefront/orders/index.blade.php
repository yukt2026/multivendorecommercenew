@extends('layouts.storefront')

@section('title', 'My Orders - ' . setting('name', 'OneStall.in'))

@section('content')
<div class="min-h-screen bg-slate-50 py-8 sm:py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Breadcrumbs & Heading -->
        <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <nav class="flex items-center space-x-2 text-xs font-semibold text-slate-400 mb-2">
                    <a href="{{ route('home') }}" class="hover:text-slate-700">Home</a>
                    <span>&rsaquo;</span>
                    <span class="text-slate-800">My Orders</span>
                </nav>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">My Orders</h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-1">Track past purchases, split delivery packages, invoices, and reorders.</p>
            </div>

            <!-- Search form -->
            <form method="GET" action="{{ route('orders.index') }}" class="flex items-center space-x-2 w-full md:w-80">
                <input type="hidden" name="status" value="{{ $currentStatus }}">
                <div class="relative w-full">
                    <input 
                        type="text" 
                        name="search" 
                        value="{{ $search }}" 
                        placeholder="Search by Order # or item..." 
                        class="w-full text-xs font-medium pl-9 pr-4 py-2.5 rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-xs"
                    >
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <button type="submit" class="px-3.5 py-2.5 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-slate-800 transition shrink-0">
                    Search
                </button>
            </form>
        </div>

        <!-- Status Filter Tabs -->
        <div class="flex items-center space-x-2 overflow-x-auto pb-4 mb-6 border-b border-slate-200 text-xs font-bold scrollbar-none">
            <a 
                href="{{ route('orders.index', ['status' => 'all', 'search' => $search]) }}"
                class="px-4 py-2 rounded-xl transition whitespace-nowrap {{ $currentStatus === 'all' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' }}"
            >
                All Orders
            </a>
            <a 
                href="{{ route('orders.index', ['status' => 'processing', 'search' => $search]) }}"
                class="px-4 py-2 rounded-xl transition whitespace-nowrap flex items-center space-x-1.5 {{ $currentStatus === 'processing' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' }}"
            >
                <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                <span>In Progress</span>
            </a>
            <a 
                href="{{ route('orders.index', ['status' => 'delivered', 'search' => $search]) }}"
                class="px-4 py-2 rounded-xl transition whitespace-nowrap {{ $currentStatus === 'delivered' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' }}"
            >
                Delivered
            </a>
            <a 
                href="{{ route('orders.index', ['status' => 'cancelled', 'search' => $search]) }}"
                class="px-4 py-2 rounded-xl transition whitespace-nowrap {{ $currentStatus === 'cancelled' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' }}"
            >
                Cancelled
            </a>
        </div>

        <!-- Orders List -->
        @if($orders->isEmpty())
            <div class="bg-white rounded-3xl p-12 text-center border border-slate-200 shadow-sm space-y-4">
                <div class="w-16 h-16 mx-auto rounded-full bg-slate-100 text-slate-400 flex items-center justify-center text-3xl">
                    📦
                </div>
                <h3 class="text-lg font-bold text-slate-900">No orders found</h3>
                <p class="text-xs text-slate-500 max-w-sm mx-auto">
                    @if($search || $currentStatus !== 'all')
                        No orders match your filter criteria. Try resetting the filter.
                    @else
                        You haven't placed any orders yet. Explore our hyperlocal stores and discover lightning-fast deliveries!
                    @endif
                </p>
                <div class="pt-2">
                    <a href="{{ route('home') }}" class="inline-flex items-center px-5 py-2.5 rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 shadow-sm transition">
                        Start Shopping &rarr;
                    </a>
                </div>
            </div>
        @else
            <div class="space-y-6">
                @foreach($orders as $order)
                    <div class="bg-white rounded-2xl sm:rounded-3xl border border-slate-200 shadow-xs overflow-hidden transition hover:shadow-md">
                        <!-- Order Header Bar -->
                        <div class="bg-slate-50/80 px-4 sm:px-6 py-3.5 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3 text-xs">
                            <div class="flex flex-wrap items-center gap-x-6 gap-y-1">
                                <div>
                                    <span class="text-slate-400 font-semibold block text-[10px] uppercase">Order Placed</span>
                                    <span class="font-bold text-slate-800">{{ $order->created_at->format('d M Y') }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-400 font-semibold block text-[10px] uppercase">Total</span>
                                    <span class="font-bold text-slate-900">{{ format_currency((float) $order->total) }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-400 font-semibold block text-[10px] uppercase">Ship To</span>
                                    <span class="font-bold text-slate-800 truncate max-w-[140px] inline-block" title="{{ $order->address_snapshot['name'] ?? '' }}">
                                        {{ $order->address_snapshot['name'] ?? 'Recipient' }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center space-x-3">
                                <span class="font-mono text-slate-500 font-bold">#{{ $order->order_number }}</span>
                                <x-order-status-badge :status="$order->status" size="sm" />
                            </div>
                        </div>

                        <!-- Packages Container -->
                        <div class="p-4 sm:p-6 space-y-6 divide-y divide-slate-100">
                            @foreach($order->fulfilmentOrders as $fo)
                                <div class="pt-4 first:pt-0">
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3">
                                        <div class="flex items-center space-x-2">
                                            @if($fo->fulfilment_type === \App\Enums\FulfilmentType::QUICK)
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-900 border border-amber-300">
                                                    ⚡ Quick Commerce (10-30m)
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                                    📦 Standard Delivery
                                                </span>
                                            @endif
                                            <span class="text-xs font-bold text-slate-700">
                                                Package #{{ $fo->fulfilment_order_number }}
                                            </span>
                                            <span class="text-xs text-slate-400 font-medium">
                                                via {{ $fo->store?->name ?? $fo->seller?->business_name ?? 'OneStall Logistics' }}
                                            </span>
                                        </div>

                                        <div class="text-xs font-semibold text-slate-600">
                                            Status: <span class="font-bold text-slate-900 capitalize">{{ str_replace('_', ' ', $fo->status) }}</span>
                                        </div>
                                    </div>

                                    <!-- Items Preview -->
                                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                                        @foreach($fo->items as $item)
                                            <div class="flex items-center space-x-3 p-2.5 rounded-xl bg-slate-50/60 border border-slate-100">
                                                <div class="w-12 h-12 rounded-lg bg-white border border-slate-200 flex items-center justify-center shrink-0 overflow-hidden">
                                                    @if($item->variant?->product?->primary_image_url)
                                                        <img src="{{ $item->variant->product->primary_image_url }}" alt="{{ $item->title }}" class="w-full h-full object-cover">
                                                    @else
                                                        <span class="text-lg">🛒</span>
                                                    @endif
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <div class="text-xs font-bold text-slate-800 truncate" title="{{ $item->title }}">{{ $item->title }}</div>
                                                    <div class="text-[11px] text-slate-400">Qty: {{ $item->qty }} &bull; {{ format_currency((float) $item->price) }}</div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Footer Actions -->
                        <div class="bg-slate-50/50 px-4 sm:px-6 py-3 border-t border-slate-200 flex flex-wrap items-center justify-between gap-2">
                            <div class="text-[11px] text-slate-400 font-medium">
                                Payment: <strong>{{ strtoupper($order->payment_method->value ?? 'Online') }}</strong> ({{ strtoupper($order->payment_status->value ?? 'Paid') }})
                            </div>

                            <div class="flex items-center space-x-2">
                                <a 
                                    href="{{ route('orders.show', $order->order_number) }}" 
                                    class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 shadow-xs transition"
                                >
                                    View Details &amp; Track &rarr;
                                </a>

                                <form method="POST" action="{{ route('orders.reorder', $order->order_number) }}" class="inline">
                                    @csrf
                                    <button 
                                        type="submit" 
                                        class="px-3.5 py-2 rounded-xl bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 text-xs font-semibold transition"
                                    >
                                        Reorder
                                    </button>
                                </form>

                                <a 
                                    href="{{ route('orders.invoice', $order->order_number) }}" 
                                    class="px-3 py-2 rounded-xl bg-white text-slate-600 hover:text-indigo-600 hover:bg-slate-100 border border-slate-200 text-xs font-semibold transition"
                                    title="Download Tax Invoice"
                                >
                                    Invoice PDF
                                </a>
                            </div>
                        </div>

                    </div>
                @endforeach
            </div>

            <div class="mt-8">
                {{ $orders->links() }}
            </div>
        @endif

    </div>
</div>
@endsection
