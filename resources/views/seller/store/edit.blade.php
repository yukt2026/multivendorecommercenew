@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'My Storefront Configuration'])

@section('content')
<div class="space-y-8" x-data="sellerStoreEditor({
    slug: @js($store->slug),
    checkSlugUrl: '{{ route("seller.store.check_slug") }}',
    lat: {{ $store->location?->lat ?: 12.9716 }},
    lng: {{ $store->location?->lng ?: 77.5946 }},
    publicUrl: '{{ $publicStoreUrl }}',
})">
    <!-- Page Header -->
    <x-page-header 
        title="Storefront & Omnichannel Profile" 
        subtitle="Manage your public merchant storefront, Blinkit-style delivery radius, operating schedule, and SEO visibility."
        :breadcrumbs="['Seller Studio' => route('seller.dashboard'), 'My Storefront' => '#']"
    >
        <x-slot name="badge">
            <x-badge :variant="$store->status->value === 'active' ? 'success' : 'danger'" dot pulse size="sm">
                {{ $store->status->value === 'active' ? 'Storefront Live & Open' : 'Storefront Temporarily Closed' }}
            </x-badge>
        </x-slot>

        <x-slot name="actions">
            <!-- Share & QR Modal Trigger -->
            <button 
                type="button" 
                @click="$dispatch('open-modal', 'store-share-modal')"
                class="px-3.5 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold text-xs border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 transition flex items-center space-x-1.5"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                <span>Share &amp; QR Code</span>
            </button>

            <x-button variant="outline" size="sm" href="{{ route('store.show', $store->slug) }}" target="_blank">
                View Public Storefront &rarr;
            </x-button>
        </x-slot>
    </x-page-header>

    @if (session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-xs font-semibold flex items-center space-x-2">
            <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200 text-xs space-y-1">
            <div class="font-bold flex items-center space-x-1">
                <svg class="w-4 h-4 text-rose-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                <span>Please fix the errors below:</span>
            </div>
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('seller.store.update') }}" enctype="multipart/form-data" class="space-y-8">
        @csrf
        @method('PUT')

        <!-- SECTION 1: CORE BRANDING & VISUAL IDENTITY -->
        <x-card title="1. Brand & Visual Identity" subtitle="Upload your storefront logo and promotional hero banner">
            <div class="space-y-6">
                <!-- Banner Upload & Preview -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-2">
                        Store Hero Banner (1200 x 400 recommended)
                    </label>
                    <div class="relative w-full h-44 sm:h-52 rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-700 overflow-hidden bg-slate-100 dark:bg-slate-900 flex items-center justify-center">
                        <template x-if="bannerPreview">
                            <img :src="bannerPreview" class="w-full h-full object-cover" />
                        </template>
                        <template x-if="!bannerPreview && '{{ $store->banner }}'">
                            <img src="{{ $store->banner }}" class="w-full h-full object-cover" />
                        </template>
                        <div x-show="!bannerPreview && !'{{ $store->banner }}'" class="text-center p-4">
                            <svg class="w-8 h-8 text-slate-400 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span class="text-xs text-slate-500 font-medium">Click below to upload hero banner</span>
                        </div>
                    </div>
                    <input 
                        type="file" 
                        name="banner" 
                        accept="image/*" 
                        @change="previewImage($event, 'banner')"
                        class="mt-2 block w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                    />
                </div>

                <!-- Logo & Store Name -->
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-6 items-center">
                    <div class="sm:col-span-1">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-2">
                            Store Logo (1:1)
                        </label>
                        <div class="w-24 h-24 rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-700 overflow-hidden bg-slate-100 dark:bg-slate-900 flex items-center justify-center relative">
                            <template x-if="logoPreview">
                                <img :src="logoPreview" class="w-full h-full object-cover" />
                            </template>
                            <template x-if="!logoPreview && '{{ $store->logo }}'">
                                <img src="{{ $store->logo }}" class="w-full h-full object-cover" />
                            </template>
                            <div x-show="!logoPreview && !'{{ $store->logo }}'" class="text-center">
                                <span class="text-[10px] text-slate-400">1:1 Logo</span>
                            </div>
                        </div>
                        <input 
                            type="file" 
                            name="logo" 
                            accept="image/*" 
                            @change="previewImage($event, 'logo')"
                            class="mt-2 block w-full text-[11px] text-slate-500 file:mr-1 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-[10px] file:font-semibold file:bg-indigo-50 file:text-indigo-700"
                        />
                    </div>

                    <div class="sm:col-span-3 space-y-4">
                        <!-- Store Name -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Store Display Name <span class="text-rose-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="name" 
                                value="{{ old('name', $store->name) }}" 
                                required 
                                class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-bold"
                            />
                        </div>

                        <!-- Unique Slug with Live Availability Check -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Public Storefront URL Slug <span class="text-rose-500">*</span>
                            </label>
                            <div class="flex items-center space-x-2">
                                <span class="px-3 py-2 text-xs font-mono text-slate-500 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl select-none">
                                    onestall.in/store/
                                </span>
                                <input 
                                    type="text" 
                                    name="slug" 
                                    x-model="slug"
                                    @input.debounce.400ms="checkSlugLive()" 
                                    required 
                                    placeholder="e.g. metro-supermart"
                                    class="flex-1 p-2.5 text-xs font-mono rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-bold"
                                />
                            </div>

                            <!-- Live availability message -->
                            <div class="mt-1 text-[11px] font-semibold flex items-center space-x-1" x-show="slugStatus">
                                <span :class="slugAvailable ? 'text-emerald-600' : 'text-rose-500'" x-text="slugStatus"></span>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-0.5">Reserved keywords (admin, seller, api, login, etc.) are blocked.</p>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Store Description &amp; Customer Pitch
                    </label>
                    <textarea 
                        name="description" 
                        rows="3" 
                        placeholder="Tell shoppers about your store, specialties, fresh stock arrival times, and quick fulfillment commitment..."
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                    >{{ old('description', $store->description) }}</textarea>
                </div>

                <!-- Contact Phone & Email -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Store Contact Phone
                        </label>
                        <input 
                            type="text" 
                            name="phone" 
                            value="{{ old('phone', $store->phone) }}" 
                            placeholder="+91 98765 43210"
                            class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Store Contact Email
                        </label>
                        <input 
                            type="email" 
                            name="email" 
                            value="{{ old('email', $store->email) }}" 
                            placeholder="support@merchantstore.in"
                            class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono"
                        />
                    </div>
                </div>

                <!-- Handled Categories -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-2">
                        Retail Categories Handled
                    </label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2.5">
                        @php
                            $selectedCats = (array) old('category_ids', $store->category_ids ?? []);
                        @endphp
                        @foreach($categories as $cat)
                            <label class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/40 flex items-center space-x-2 cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                                <input 
                                    type="checkbox" 
                                    name="category_ids[]" 
                                    value="{{ $cat->id }}" 
                                    {{ in_array($cat->id, $selectedCats) ? 'checked' : '' }}
                                    class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span class="text-xs font-medium text-slate-800 dark:text-slate-200">{{ $cat->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </x-card>

        <!-- SECTION 2: PHYSICAL LOCATION & MAP PIN -->
        <x-card title="2. Store Location & Map Coordinates" subtitle="Geocoded location used for rider dispatch and Haversine distance calculations">
            <div class="space-y-4">
                <!-- Street Address -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Street Address / Shop Premises <span class="text-rose-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="address" 
                        value="{{ old('address', $store->location?->address) }}" 
                        required 
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                    />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            City <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="city" 
                            value="{{ old('city', $store->location?->city ?? 'Bengaluru') }}" 
                            required 
                            class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            State <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="state" 
                            value="{{ old('state', $store->location?->state ?? 'Karnataka') }}" 
                            required 
                            class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Pincode <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="pincode" 
                            maxlength="6" 
                            value="{{ old('pincode', $store->location?->pincode ?? '560038') }}" 
                            required 
                            class="w-full p-2.5 text-xs font-mono rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                        />
                    </div>
                </div>

                <!-- Leaflet Map Container -->
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                Interactive Storefront Map Pin
                            </span>
                            <p class="text-[11px] text-slate-500">Drag or click to adjust exact doorstep coordinates.</p>
                        </div>
                        <button 
                            type="button" 
                            @click="detectLocation()" 
                            class="px-3 py-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold text-xs border border-indigo-200"
                        >
                            📍 Use My GPS Position
                        </button>
                    </div>

                    <div id="store-edit-map" class="h-64 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden bg-slate-200"></div>

                    <div class="grid grid-cols-2 gap-4 pt-1">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500">Latitude</label>
                            <input type="text" name="lat" x-model="lat" required readonly class="w-full p-2 text-xs font-mono rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700" />
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500">Longitude</label>
                            <input type="text" name="lng" x-model="lng" required readonly class="w-full p-2 text-xs font-mono rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700" />
                        </div>
                    </div>
                </div>
            </div>
        </x-card>

        <!-- SECTION 3: OPERATING TIMINGS (COPY-TO-ALL) -->
        <x-card title="3. Operating Schedule & Timings" subtitle="Daily opening and closing hours for 10-30m instant dispatch">
            <x-slot name="action">
                <button 
                    type="button" 
                    @click="copyMondayToAll()" 
                    class="px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-400 font-bold text-xs border border-indigo-200 hover:bg-indigo-100 transition"
                >
                    &crarr; Copy Monday Schedule to All Days
                </button>
            </x-slot>

            <div class="space-y-3">
                @php
                    $days = [
                        1 => 'Monday',
                        2 => 'Tuesday',
                        3 => 'Wednesday',
                        4 => 'Thursday',
                        5 => 'Friday',
                        6 => 'Saturday',
                        0 => 'Sunday',
                    ];
                    $existingTimings = $store->timings->keyBy('day_of_week');
                @endphp

                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($days as $dayNum => $dayName)
                        @php
                            $timing = $existingTimings->get($dayNum);
                            $open = $timing?->opens_at ? substr($timing->opens_at, 0, 5) : '08:00';
                            $close = $timing?->closes_at ? substr($timing->closes_at, 0, 5) : '22:00';
                            $isClosed = (bool) ($timing?->is_closed ?? false);
                        @endphp
                        <div class="py-2.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs" x-data="{ closed: {{ $isClosed ? 'true' : 'false' }} }">
                            <div class="w-28 font-bold text-slate-800 dark:text-slate-200">
                                {{ $dayName }}
                                <input type="hidden" name="timings[{{ $dayNum }}][day_of_week]" value="{{ $dayNum }}">
                            </div>

                            <div class="flex items-center space-x-3">
                                <label class="flex items-center space-x-1.5 cursor-pointer">
                                    <input 
                                        type="checkbox" 
                                        name="timings[{{ $dayNum }}][is_closed]" 
                                        value="1" 
                                        x-model="closed" 
                                        class="rounded border-slate-300 text-rose-600 focus:ring-rose-500"
                                    />
                                    <span class="text-slate-500 font-medium" :class="closed ? 'text-rose-600 font-bold' : ''">Closed</span>
                                </label>

                                <div class="flex items-center space-x-2" x-show="!closed">
                                    <input 
                                        type="time" 
                                        id="open-{{ $dayNum }}"
                                        name="timings[{{ $dayNum }}][opens_at]" 
                                        value="{{ $open }}" 
                                        class="p-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900"
                                    />
                                    <span class="text-slate-400">to</span>
                                    <input 
                                        type="time" 
                                        id="close-{{ $dayNum }}"
                                        name="timings[{{ $dayNum }}][closes_at]" 
                                        value="{{ $close }}" 
                                        class="p-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900"
                                    />
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-card>

        <!-- SECTION 4: DELIVERY RADIUS & FULFILLMENT TOGGLES (RULE 2: PERSISTED IN DB) -->
        <x-card title="4. Delivery Radius, Zones & Fulfillment Toggles" subtitle="Configure hyper-local coverage limits, delivery fees, and order fulfillment capabilities">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <!-- Delivery Radius (km) -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Instant Delivery Radius (km)
                    </label>
                    <input 
                        type="number" 
                        step="0.5" 
                        min="1" 
                        max="50" 
                        name="delivery_radius_km" 
                        value="{{ old('delivery_radius_km', $store->delivery_radius_km ?? $defaultDeliveryRadius) }}" 
                        class="w-full p-2.5 text-xs font-mono rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                    />
                    <p class="text-[10px] text-slate-400 mt-1">Platform default is {{ $defaultDeliveryRadius }} km from settings.</p>
                </div>

                <!-- Delivery Fee -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Store Base Delivery Fee (₹)
                    </label>
                    @php
                        $activeZone = $store->deliveryZones->firstWhere('is_quick', true);
                    @endphp
                    <input 
                        type="number" 
                        step="1" 
                        min="0" 
                        name="delivery_fee" 
                        value="{{ old('delivery_fee', $activeZone?->delivery_fee ?? $defaultDeliveryFee) }}" 
                        class="w-full p-2.5 text-xs font-mono rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                    />
                    <p class="text-[10px] text-slate-400 mt-1">Base fee charged for quick dispatch.</p>
                </div>

                <!-- Minimum Order Value -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Minimum Order Value (₹)
                    </label>
                    <input 
                        type="number" 
                        step="1" 
                        min="0" 
                        name="min_order_value" 
                        value="{{ old('min_order_value', $activeZone?->min_order_value ?? $defaultMinOrder) }}" 
                        class="w-full p-2.5 text-xs font-mono rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                    />
                    <p class="text-[10px] text-slate-400 mt-1">Minimum checkout amount for this outlet.</p>
                </div>
            </div>

            <!-- Fulfillment Mode Toggles -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                <!-- Quick Commerce Toggle -->
                <label class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/40 flex items-start space-x-3 cursor-pointer">
                    <input 
                        type="checkbox" 
                        name="accepts_quick_commerce" 
                        value="1" 
                        {{ old('accepts_quick_commerce', $store->accepts_quick_commerce) ? 'checked' : '' }}
                        class="mt-0.5 rounded border-slate-300 text-amber-500 focus:ring-amber-500"
                    />
                    <div>
                        <span class="text-xs font-bold text-slate-900 dark:text-white block">⚡ 10-30m Quick Commerce</span>
                        <span class="text-[11px] text-slate-500">Enable rider dispatch within store radius.</span>
                    </div>
                </label>

                <!-- Store Pickup Toggle -->
                <label class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/40 flex items-start space-x-3 cursor-pointer">
                    <input 
                        type="checkbox" 
                        name="accepts_pickup" 
                        value="1" 
                        {{ old('accepts_pickup', $store->accepts_pickup) ? 'checked' : '' }}
                        class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <div>
                        <span class="text-xs font-bold text-slate-900 dark:text-white block">🏬 Store Pickup / Click &amp; Collect</span>
                        <span class="text-[11px] text-slate-500">Allow customers to collect orders at store counter.</span>
                    </div>
                </label>

                <!-- Store Open / Live Status Toggle -->
                <label class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/40 flex items-start space-x-3 cursor-pointer">
                    <input 
                        type="checkbox" 
                        name="is_active" 
                        value="1" 
                        {{ old('is_active', $store->status->value === 'active') ? 'checked' : '' }}
                        class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                    />
                    <div>
                        <span class="text-xs font-bold text-slate-900 dark:text-white block">🟢 Storefront Open for Orders</span>
                        <span class="text-[11px] text-slate-500">Turn OFF to temporarily close storefront.</span>
                    </div>
                </label>
            </div>
        </x-card>

        <!-- SECTION 5: SEO METADATA & GOOGLE SNIPPET PREVIEW -->
        <x-card title="5. Search Engine Optimization (SEO) & Social Previews" subtitle="Optimize your store presence on Google Search, WhatsApp, and social networks">
            <div class="space-y-4" x-data="{ metaTitle: @js($store->meta_title ?: $store->name), metaDesc: @js($store->meta_description ?: $store->description) }">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            SEO Meta Title (Max 60 chars recommended)
                        </label>
                        <input 
                            type="text" 
                            name="meta_title" 
                            x-model="metaTitle"
                            maxlength="160"
                            placeholder="e.g. Metro Supermart - 15 Min Grocery Delivery Indiranagar"
                            class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            SEO Meta Description (Max 160 chars recommended)
                        </label>
                        <input 
                            type="text" 
                            name="meta_description" 
                            x-model="metaDesc"
                            maxlength="320"
                            placeholder="e.g. Order fresh vegetables, dairy, pulses, and pantry essentials from Metro Supermart online."
                            class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                        />
                    </div>
                </div>

                <!-- Google SERP Snippet Preview -->
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-1">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Google Search Result Snippet Preview</span>
                    <div class="text-[11px] text-slate-500 font-mono truncate" x-text="publicUrl"></div>
                    <div class="text-sm font-bold text-indigo-600 dark:text-indigo-400 hover:underline truncate" x-text="(metaTitle || 'Your Store') + ' | OneStall.in'"></div>
                    <p class="text-xs text-slate-600 dark:text-slate-400 line-clamp-2" x-text="metaDesc || 'Order groceries, organic produce, and daily essentials from our verified digital storefront.'"></p>
                </div>
            </div>
        </x-card>

        <!-- Save Button Bar -->
        <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-200 dark:border-slate-800">
            <a href="{{ route('seller.dashboard') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-100 transition">
                Cancel
            </a>
            <button 
                type="submit" 
                class="px-8 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs shadow-lg shadow-indigo-600/30 transition flex items-center space-x-2"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>Save All Storefront Changes</span>
            </button>
        </div>
    </form>

    <!-- Shareable URL & QR Code Modal -->
    <x-modal name="store-share-modal" title="Share Storefront &amp; QR Code" size="md">
        <div class="space-y-4 text-center">
            <!-- QR Code Container -->
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 inline-block shadow-sm">
                <img 
                    src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=10&data={{ urlencode($publicStoreUrl) }}" 
                    alt="Store QR Code" 
                    class="w-48 h-48 mx-auto"
                />
            </div>

            <h4 class="text-base font-extrabold text-slate-900 dark:text-white">
                Scan to Open {{ $store->name }}
            </h4>
            <p class="text-xs text-slate-500">Place this QR code at your store counter, carry bags, or marketing flyers for instant customer ordering.</p>

            <!-- URL Copy Box -->
            <div class="flex items-center space-x-2 p-2 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700">
                <input 
                    type="text" 
                    readonly 
                    value="{{ $publicStoreUrl }}" 
                    id="shareable-store-url"
                    class="flex-1 bg-transparent text-xs font-mono text-slate-800 dark:text-slate-200 border-0 focus:ring-0 p-1 select-all"
                />
                <button 
                    type="button" 
                    @click="copyUrl()"
                    class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-xs transition"
                >
                    <span x-text="copied ? 'Copied! ✓' : 'Copy URL'"></span>
                </button>
            </div>

            <div class="pt-2 flex items-center justify-center space-x-3">
                <a 
                    href="https://api.qrserver.com/v1/create-qr-code/?size=500x500&margin=10&data={{ urlencode($publicStoreUrl) }}" 
                    download="qr-{{ $store->slug }}.png"
                    target="_blank"
                    class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-200 transition"
                >
                    Download High-Res QR (PNG) &darr;
                </a>
            </div>
        </div>
    </x-modal>
</div>

<!-- Leaflet Map CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
function sellerStoreEditor(config) {
    return {
        slug: config.slug,
        slugStatus: '',
        slugAvailable: true,
        lat: config.lat,
        lng: config.lng,
        publicUrl: config.publicUrl,
        bannerPreview: null,
        logoPreview: null,
        copied: false,
        map: null,
        marker: null,

        init() {
            this.$nextTick(() => {
                this.initMap();
            });
        },

        initMap() {
            const container = document.getElementById('store-edit-map');
            if (!container || typeof L === 'undefined') return;

            this.map = L.map('store-edit-map').setView([this.lat, this.lng], 14);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(this.map);

            this.marker = L.marker([this.lat, this.lng], { draggable: true }).addTo(this.map);

            this.marker.on('dragend', (e) => {
                const pos = e.target.getLatLng();
                this.lat = Number(pos.lat.toFixed(7));
                this.lng = Number(pos.lng.toFixed(7));
            });

            this.map.on('click', (e) => {
                this.marker.setLatLng(e.latlng);
                this.lat = Number(e.latlng.lat.toFixed(7));
                this.lng = Number(e.latlng.lng.toFixed(7));
            });
        },

        detectLocation() {
            if (!navigator.geolocation) {
                alert('Geolocation is not supported.');
                return;
            }
            navigator.geolocation.getCurrentPosition((pos) => {
                this.lat = Number(pos.coords.latitude.toFixed(7));
                this.lng = Number(pos.coords.longitude.toFixed(7));
                if (this.map && this.marker) {
                    this.map.setView([this.lat, this.lng], 16);
                    this.marker.setLatLng([this.lat, this.lng]);
                }
            });
        },

        previewImage(event, type) {
            const file = event.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = (e) => {
                if (type === 'banner') {
                    this.bannerPreview = e.target.result;
                } else if (type === 'logo') {
                    this.logoPreview = e.target.result;
                }
            };
            reader.readAsDataURL(file);
        },

        async checkSlugLive() {
            if (!this.slug) {
                this.slugStatus = '';
                return;
            }
            try {
                const res = await fetch(config.checkSlugUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ slug: this.slug })
                });
                const data = await res.json();
                this.slugAvailable = data.available;
                this.slugStatus = data.message;
                if (data.available) {
                    this.publicUrl = '{{ url("/store") }}/' + this.slug;
                }
            } catch (e) {
                console.error(e);
            }
        },

        copyMondayToAll() {
            const openVal = document.getElementById('open-1')?.value || '08:00';
            const closeVal = document.getElementById('close-1')?.value || '22:00';

            for (let i = 0; i <= 6; i++) {
                const openInput = document.getElementById('open-' + i);
                const closeInput = document.getElementById('close-' + i);
                if (openInput) openInput.value = openVal;
                if (closeInput) closeInput.value = closeVal;
            }
            alert('Copied Monday schedule ( ' + openVal + ' - ' + closeVal + ' ) to all days!');
        },

        copyUrl() {
            const el = document.getElementById('shareable-store-url');
            if (el) {
                navigator.clipboard.writeText(el.value);
                this.copied = true;
                setTimeout(() => { this.copied = false; }, 3000);
            }
        }
    };
}
</script>
@endsection
