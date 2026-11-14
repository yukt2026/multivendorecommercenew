@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'My Storefront Configuration'])

@push('styles')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
    <style>
        .leaflet-container {
            z-index: 5 !important;
            font-family: inherit;
        }
        #store-edit-map {
            min-height: 288px !important;
            height: 288px !important;
            width: 100% !important;
            display: block !important;
            position: relative !important;
        }
        #store-edit-map .leaflet-tile-pane img {
            max-width: none !important;
            max-height: none !important;
        }
    </style>
@endpush

@section('content')
<div class="space-y-8" x-data="sellerStoreEditor({
    initialSlug: @js($store->slug),
    checkSlugUrl: '{{ route('seller.store.check_slug') }}',
    lat: {{ (float) ($store->location?->lat ?: 12.9716) }},
    lng: {{ (float) ($store->location?->lng ?: 77.5946) }},
    publicUrl: '{{ $publicStoreUrl }}',
    initialLogo: @js($store->logo_url),
    initialBanner: @js($store->banner_url),
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

    @if (isset($errors) && $errors->any())
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

    @if(isset($stores) && $stores->count() > 1)
        <div class="p-3.5 rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-2xs">
            <div class="flex items-center space-x-2">
                <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Configuring Outlet:</span>
                <span class="text-xs font-extrabold text-indigo-700 dark:text-indigo-300 px-2.5 py-1 rounded-xl bg-white dark:bg-slate-900 border border-indigo-200 dark:border-indigo-800 shadow-2xs">
                    {{ $store->name }}
                </span>
            </div>
            <div class="flex items-center space-x-2">
                <label for="store_outlet_switcher" class="text-xs text-slate-500 font-medium">Switch Outlet:</label>
                <select id="store_outlet_switcher" onchange="window.location.href = '{{ route('seller.store.edit') }}?store_id=' + this.value" class="text-xs rounded-xl bg-white dark:bg-slate-900 border border-indigo-200 dark:border-indigo-800 py-1.5 px-3 font-semibold text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500">
                    @foreach($stores as $st)
                        <option value="{{ $st->id }}" {{ $store->id === $st->id ? 'selected' : '' }}>
                            {{ $st->name }} ({{ $st->status->label() }})
                        </option>
                    @endforeach
                </select>
                <a href="{{ route('seller.stores.index') }}" class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline px-1.5 whitespace-nowrap">
                    Manage Outlets &rarr;
                </a>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('seller.store.update', ['store_id' => $store->id]) }}" enctype="multipart/form-data" class="space-y-8">
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
                        <template x-if="!bannerPreview && initialBanner">
                            <img :src="initialBanner" class="w-full h-full object-cover" />
                        </template>
                        <div x-show="!bannerPreview && !initialBanner" class="text-center p-4">
                            <svg class="w-8 h-8 text-slate-400 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span class="text-xs text-slate-500 font-medium">Click below to upload hero banner</span>
                        </div>
                    </div>
                    <input 
                        type="file" 
                        name="banner" 
                        accept="image/jpeg,image/png,image/webp" 
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
                            <template x-if="!logoPreview && initialLogo">
                                <img :src="initialLogo" class="w-full h-full object-cover" />
                            </template>
                            <div x-show="!logoPreview && !initialLogo" class="text-center">
                                <span class="text-[10px] text-slate-400">1:1 Logo</span>
                            </div>
                        </div>
                        <input 
                            type="file" 
                            name="logo" 
                            accept="image/jpeg,image/png,image/webp" 
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
                                class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-bold focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                            />
                        </div>

                        <!-- Unique Slug with Live Availability Check -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Public Storefront URL Slug <span class="text-rose-500">*</span>
                            </label>
                            <div class="flex items-center space-x-2">
                                <span class="px-3 py-2 text-xs font-mono text-slate-500 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl select-none">
                                    {{ url('/store') }}/
                                </span>
                                <input 
                                    type="text" 
                                    name="slug" 
                                    x-model="slug"
                                    @input.debounce.400ms="checkSlugLive()" 
                                    required 
                                    placeholder="e.g. metro-supermart"
                                    class="flex-1 p-2.5 text-xs font-mono rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-bold focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                />
                            </div>

                            <!-- Live availability message -->
                            <div class="mt-1 text-[11px] font-semibold flex items-center space-x-1" x-show="slugStatus">
                                <span :class="slugAvailable ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-500'" x-text="slugStatus"></span>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-0.5">Letters, numbers, and hyphens only. Reserved keywords are blocked.</p>
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
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
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
        <x-card title="2. Store Location & GPS Coordinates" subtitle="Geocoded physical outlet used for rider pickup, 15m radius calculation, and customer navigation">
            <div class="space-y-4">
                <!-- Street Address -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Street Address / Shop Premises <span class="text-rose-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="store-address-input"
                        name="address" 
                        x-model="address"
                        required 
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            City <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="store-city-input"
                            name="city" 
                            x-model="city"
                            required 
                            class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            State <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="store-state-input"
                            name="state" 
                            x-model="state"
                            required 
                            class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Pincode <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="store-pincode-input"
                            name="pincode" 
                            x-model="pincode"
                            maxlength="6" 
                            required 
                            class="w-full p-2.5 text-xs font-mono rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        />
                    </div>
                </div>

                <!-- Leaflet Interactive Map Container -->
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 flex items-center space-x-1.5">
                                <span>Interactive GPS Pin</span>
                                <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-mono">(Click or Drag to adjust)</span>
                            </span>
                            <p class="text-[11px] text-slate-500">Riders navigate to this exact coordinate for order collection.</p>
                        </div>
                        <button 
                            type="button" 
                            @click="detectLocation()" 
                            :disabled="isLocating"
                            class="px-3.5 py-1.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 font-bold text-xs border border-emerald-300 dark:border-emerald-800 hover:bg-emerald-100 transition flex items-center space-x-1.5 shrink-0 shadow-2xs"
                        >
                            <svg x-show="!isLocating" class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <svg x-show="isLocating" class="w-4 h-4 animate-spin text-emerald-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            <span x-text="isLocating ? 'Detecting Location...' : '📍 Detect via Browser GPS'"></span>
                        </button>
                    </div>

                    <!-- Search Input directly above map -->
                    <div class="flex items-center space-x-2">
                        <div class="relative flex-1">
                            <input 
                                type="text" 
                                x-model="searchQuery" 
                                @keydown.enter.prevent="searchLocation()"
                                placeholder="Search area, landmark or street to fly pin on map..." 
                                class="w-full pl-9 pr-3 py-2 text-xs rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                            />
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                        </div>
                        <button 
                            type="button" 
                            @click="searchLocation()" 
                            :disabled="isSearching"
                            class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-xs transition flex items-center space-x-1 shrink-0"
                        >
                            <span x-show="!isSearching">Search Map</span>
                            <span x-show="isSearching">Searching...</span>
                        </button>
                    </div>

                    <!-- Map Render Canvas -->
                    <div id="store-edit-map" class="h-72 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden bg-slate-200 dark:bg-slate-800 relative shadow-inner">
                        <div x-show="mapLoading" class="absolute inset-0 z-20 bg-slate-100/90 dark:bg-slate-900/90 flex items-center justify-center space-x-2 text-xs font-semibold text-slate-600 dark:text-slate-300">
                            <svg class="w-4 h-4 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            <span>Loading Interactive Map...</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 pt-1">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider">Latitude</label>
                            <input type="text" name="lat" x-model="lat" required readonly class="w-full p-2 text-xs font-mono font-bold rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider">Longitude</label>
                            <input type="text" name="lng" x-model="lng" required readonly class="w-full p-2 text-xs font-mono font-bold rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white" />
                        </div>
                    </div>
                </div>
            </div>
        </x-card>

        <!-- SECTION 3: OPERATING TIMINGS (COPY-TO-ALL & 24x7 PRESET) -->
        <x-card title="3. Operating Schedule & Timings" subtitle="Daily opening and closing hours for 10-30m instant dispatch. Configure normal hours, overnight shifts, or 24x7.">
            <x-slot name="action">
                <div class="flex items-center space-x-2">
                    <button 
                        type="button" 
                        @click="set24x7AllDays()" 
                        class="px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 font-bold text-xs border border-emerald-300 dark:border-emerald-800 hover:bg-emerald-100 transition flex items-center space-x-1"
                        title="Set 24 Hours Open (00:00 - 23:59) for all 7 days"
                    >
                        <span>⚡</span>
                        <span>Open 24x7 (All Days)</span>
                    </button>
                    <button 
                        type="button" 
                        @click="copyMondayToAll()" 
                        class="px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-400 font-bold text-xs border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 transition"
                        title="Copy Monday's schedule and open/closed state to all 7 days"
                    >
                        &crarr; Copy Monday to All
                    </button>
                </div>
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
                            $isDay24 = (!$isClosed && (($open === '00:00' && in_array($close, ['00:00', '23:59'])) || $open === $close));
                        @endphp
                        <div 
                            class="py-2.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs" 
                            x-data="{ 
                                closed: {{ $isClosed ? 'true' : 'false' }},
                                is24: {{ $isDay24 ? 'true' : 'false' }},
                                check24() {
                                    const o = document.getElementById('open-{{ $dayNum }}')?.value;
                                    const c = document.getElementById('close-{{ $dayNum }}')?.value;
                                    this.is24 = !this.closed && ((o === '00:00' && (c === '00:00' || c === '23:59')) || (o && o === c));
                                },
                                make24() {
                                    this.closed = false;
                                    this.$nextTick(() => {
                                        const o = document.getElementById('open-{{ $dayNum }}');
                                        const c = document.getElementById('close-{{ $dayNum }}');
                                        if (o) o.value = '00:00';
                                        if (c) c.value = '23:59';
                                        this.check24();
                                    });
                                }
                            }"
                            @set-timings-24x7.window="make24()"
                            @copy-monday.window="if ({{ $dayNum }} !== 1) { 
                                closed = $event.detail.closed; 
                                $nextTick(() => { 
                                    const o = document.getElementById('open-{{ $dayNum }}');
                                    const c = document.getElementById('close-{{ $dayNum }}');
                                    if (o) o.value = $event.detail.open; 
                                    if (c) c.value = $event.detail.close; 
                                    check24();
                                }); 
                            }"
                        >
                            <div class="w-36 flex items-center space-x-2 font-bold text-slate-800 dark:text-slate-200">
                                <span>{{ $dayName }}</span>
                                <input type="hidden" name="timings[{{ $dayNum }}][day_of_week]" value="{{ $dayNum }}">
                                <span x-show="is24 && !closed" class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                    24h Open
                                </span>
                            </div>

                            <div class="flex items-center space-x-3">
                                <label class="flex items-center space-x-1.5 cursor-pointer">
                                    <input 
                                        type="checkbox" 
                                        name="timings[{{ $dayNum }}][is_closed]" 
                                        value="1" 
                                        x-model="closed" 
                                        @change="check24()"
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
                                        @change="check24()"
                                        class="p-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 font-mono"
                                    />
                                    <span class="text-slate-400">to</span>
                                    <input 
                                        type="time" 
                                        id="close-{{ $dayNum }}"
                                        name="timings[{{ $dayNum }}][closes_at]" 
                                        value="{{ $close }}" 
                                        @change="check24()"
                                        class="p-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 font-mono"
                                    />
                                    <button 
                                        type="button" 
                                        @click="make24()" 
                                        class="px-2 py-1 text-[11px] font-bold rounded-md bg-slate-100 dark:bg-slate-800 hover:bg-emerald-50 dark:hover:bg-emerald-950 hover:text-emerald-600 dark:hover:text-emerald-300 border border-slate-200 dark:border-slate-700 transition"
                                        title="Set this day to 24 Hours Open (00:00 - 23:59)"
                                    >
                                        24h
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-card>

        <!-- SECTION 4: DELIVERY RADIUS & FULFILLMENT TOGGLES -->
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
                        min="0.5" 
                        max="50" 
                        name="delivery_radius_km" 
                        value="{{ old('delivery_radius_km', $store->delivery_radius_km ?? $defaultDeliveryRadius) }}" 
                        class="w-full p-2.5 text-xs font-mono rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                    />
                    <p class="text-[10px] text-slate-400 mt-1">Platform default is {{ $defaultDeliveryRadius }} km from system settings.</p>
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
                    <p class="text-[10px] text-slate-400 mt-1">Base fee charged for quick dispatch orders.</p>
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
                            placeholder="e.g. Metro Supermart - 15 Min Grocery Delivery"
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
                            placeholder="e.g. Order fresh vegetables, dairy, pulses, and pantry essentials online."
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
    <x-modal name="store-share-modal" title="Share Storefront & QR Code" size="md">
        <div class="space-y-4 text-center">
            <!-- QR Code Container -->
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 inline-block shadow-sm">
                <img 
                    src="{{ route('seller.store.qr_code') }}" 
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
                    href="{{ route('seller.store.qr_code') }}" 
                    download="qr-{{ $store->slug }}.png"
                    class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-200 transition"
                >
                    Download High-Res QR (PNG) &darr;
                </a>
            </div>
        </div>
    </x-modal>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
function sellerStoreEditor(config) {
    return {
        slug: config.initialSlug,
        initialSlug: config.initialSlug,
        checkSlugUrl: config.checkSlugUrl,
        slugStatus: '',
        slugAvailable: true,
        lat: config.lat,
        lng: config.lng,
        address: @js(old('address', $store->location?->address ?? '')),
        city: @js(old('city', $store->location?->city ?? 'Bengaluru')),
        state: @js(old('state', $store->location?->state ?? 'Karnataka')),
        pincode: @js(old('pincode', $store->location?->pincode ?? '560038')),
        publicUrl: config.publicUrl,
        initialLogo: config.initialLogo,
        initialBanner: config.initialBanner,
        bannerPreview: null,
        logoPreview: null,
        copied: false,
        map: null,
        marker: null,
        mapLoading: true,
        isLocating: false,
        isSearching: false,
        searchQuery: '',

        init() {
            this.ensureLeafletLoaded().then(() => {
                this.initMap();
            });
        },

        ensureLeafletLoaded() {
            return new Promise((resolve) => {
                if (typeof window.L !== 'undefined') {
                    resolve();
                    return;
                }

                // If not loaded, inject script dynamically
                const script = document.createElement('script');
                script.src = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js';
                script.onload = () => resolve();
                script.onerror = () => {
                    const fallback = document.createElement('script');
                    fallback.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                    fallback.onload = () => resolve();
                    fallback.onerror = () => resolve();
                    document.head.appendChild(fallback);
                };
                document.head.appendChild(script);

                const checkInterval = setInterval(() => {
                    if (typeof window.L !== 'undefined') {
                        clearInterval(checkInterval);
                        resolve();
                    }
                }, 100);
                setTimeout(() => {
                    clearInterval(checkInterval);
                    resolve();
                }, 4000);
            });
        },

        initMap() {
            const container = document.getElementById('store-edit-map');
            if (!container || typeof window.L === 'undefined') {
                this.mapLoading = false;
                return;
            }

            try {
                // Fix default marker icons from CDN
                if (L.Icon && L.Icon.Default) {
                    delete L.Icon.Default.prototype._getIconUrl;
                    L.Icon.Default.mergeOptions({
                        iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
                        iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
                        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                    });
                }

                this.map = L.map('store-edit-map', {
                    zoomControl: true,
                    scrollWheelZoom: false
                }).setView([this.lat, this.lng], 15);

                // High-performance CartoDB Voyager tile layer
                const primaryTiles = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                    subdomains: 'abcd',
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap, &copy; CARTO'
                }).addTo(this.map);

                // Backup tile layer if primary has any network failure
                primaryTiles.on('tileerror', () => {
                    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap'
                    }).addTo(this.map);
                });

                this.marker = L.marker([this.lat, this.lng], {
                    draggable: true,
                    autoPan: true
                }).addTo(this.map);

                this.marker.on('dragend', (e) => {
                    const pos = e.target.getLatLng();
                    this.lat = Number(pos.lat.toFixed(7));
                    this.lng = Number(pos.lng.toFixed(7));
                    this.reverseGeocode(this.lat, this.lng);
                });

                this.map.on('click', (e) => {
                    this.marker.setLatLng(e.latlng);
                    this.lat = Number(e.latlng.lat.toFixed(7));
                    this.lng = Number(e.latlng.lng.toFixed(7));
                    this.reverseGeocode(this.lat, this.lng);
                });

                // Multi-stage invalidation to guarantee full container rendering
                [50, 200, 500, 1000].forEach((delay) => {
                    setTimeout(() => {
                        if (this.map) {
                            this.map.invalidateSize();
                            this.mapLoading = false;
                        }
                    }, delay);
                });

                window.addEventListener('resize', () => {
                    if (this.map) this.map.invalidateSize();
                });
            } catch (err) {
                console.error('Map initialization error:', err);
                this.mapLoading = false;
            }
        },

        detectLocation() {
            this.isLocating = true;

            // If browser does not support geolocation or is not in secure context, fall back to IP immediately
            if (!navigator.geolocation) {
                this.detectLocationViaIp('Browser does not support direct GPS. Located via network.');
                return;
            }

            // Using enableHighAccuracy: false first for fast desktop network/WiFi geolocation without GPS satellite timeout
            const geoOptions = {
                enableHighAccuracy: false,
                timeout: 7000,
                maximumAge: 60000
            };

            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.lat = Number(pos.coords.latitude.toFixed(7));
                    this.lng = Number(pos.coords.longitude.toFixed(7));

                    if (this.map && this.marker) {
                        this.map.setView([this.lat, this.lng], 16);
                        this.marker.setLatLng([this.lat, this.lng]);
                        this.map.invalidateSize();
                    }

                    this.reverseGeocode(this.lat, this.lng, true);
                    this.isLocating = false;
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { message: '📍 GPS position locked and address updated!', type: 'success' }
                    }));
                },
                (err) => {
                    console.warn('Browser GPS notice:', err.message, '- auto falling back to network IP');
                    // Automatically fallback to network IP geolocation so detection NEVER fails
                    const reason = err.code === 1 
                        ? 'Browser location permission denied. Detected approximate location via network IP.'
                        : 'GPS satellite unavailable on this device. Detected location via network IP.';
                    this.detectLocationViaIp(reason);
                },
                geoOptions
            );
        },

        async detectLocationViaIp(noticeMsg = '') {
            try {
                let lat = null, lng = null, city = '', state = '', postal = '';

                try {
                    const res = await fetch('https://ipwho.is/');
                    if (res.ok) {
                        const d = await res.json();
                        if (d && d.success && d.latitude && d.longitude) {
                            lat = parseFloat(d.latitude);
                            lng = parseFloat(d.longitude);
                            city = d.city || '';
                            state = d.region || '';
                            postal = d.postal || '';
                        }
                    }
                } catch (e) {}

                if (!lat || !lng) {
                    try {
                        const res2 = await fetch('https://api.bigdatacloud.net/data/reverse-geocode-client');
                        if (res2.ok) {
                            const d2 = await res2.json();
                            if (d2 && d2.latitude && d2.longitude) {
                                lat = parseFloat(d2.latitude);
                                lng = parseFloat(d2.longitude);
                                city = d2.city || d2.locality || '';
                                state = d2.principalSubdivision || '';
                                postal = d2.postcode || '';
                            }
                        }
                    } catch (e) {}
                }

                if (lat && lng) {
                    this.lat = Number(lat.toFixed(7));
                    this.lng = Number(lng.toFixed(7));

                    if (this.map && this.marker) {
                        this.map.setView([this.lat, this.lng], 15);
                        this.marker.setLatLng([this.lat, this.lng]);
                        this.map.invalidateSize();
                    }

                    if (city && !this.city) this.city = city;
                    if (state && !this.state) this.state = state;
                    if (postal && !this.pincode) this.pincode = postal;

                    // Also reverse geocode coordinates to fill address details
                    this.reverseGeocode(this.lat, this.lng, true);

                    const finalNotice = noticeMsg || `📍 Located via network: ${city || ''}, ${state || ''}`;
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { message: finalNotice + ' (Drag map pin to adjust exact doorstep)', type: 'info' }
                    }));
                } else {
                    throw new Error('Both IP geolocation services unreachable');
                }
            } catch (e) {
                console.error('IP location error:', e);
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { message: 'Unable to auto-detect position. Please use the search bar above the map or click on the map directly.', type: 'warning' }
                }));
            } finally {
                this.isLocating = false;
            }
        },

        async searchLocation() {
            if (!this.searchQuery || this.searchQuery.trim().length < 3) {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { message: 'Please enter at least 3 characters to search.', type: 'warning' }
                }));
                return;
            }

            this.isSearching = true;
            try {
                const q = encodeURIComponent(this.searchQuery.trim());
                const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${q}&limit=1&addressdetails=1`, {
                    headers: { 'Accept': 'application/json' }
                });

                if (!res.ok) throw new Error('Search failed');
                const results = await res.json();

                if (results && results.length > 0) {
                    const place = results[0];
                    this.lat = Number(parseFloat(place.lat).toFixed(7));
                    this.lng = Number(parseFloat(place.lon).toFixed(7));

                    if (this.map && this.marker) {
                        this.map.setView([this.lat, this.lng], 16);
                        this.marker.setLatLng([this.lat, this.lng]);
                        this.map.invalidateSize();
                    }

                    // Auto-fill address details with force overwrite since user explicitly searched
                    this.applyAddressDetails(place, true);
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { message: '📍 Located on map: ' + place.display_name.split(',')[0], type: 'success' }
                    }));
                } else {
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { message: 'No location found. Please try a different area or landmark.', type: 'warning' }
                    }));
                }
            } catch (err) {
                console.error(err);
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { message: 'Search service unavailable. You can click on the map directly.', type: 'warning' }
                }));
            } finally {
                this.isSearching = false;
            }
        },

        async reverseGeocode(lat, lng, force = false) {
            try {
                const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) return;
                const data = await res.json();
                if (data && data.address) {
                    this.applyAddressDetails(data, force);
                }
            } catch (e) {
                // Silently ignore reverse geocode failures
            }
        },

        applyAddressDetails(data, force = false) {
            const addr = data.address || {};
            const cityVal = addr.city || addr.town || addr.village || addr.suburb || addr.county || '';
            const stateVal = addr.state || '';
            const pincodeVal = addr.postcode || '';

            if (cityVal && (force || !this.city)) this.city = cityVal;
            if (stateVal && (force || !this.state)) this.state = stateVal;
            if (pincodeVal && /^[1-9][0-9]{5}$/.test(pincodeVal) && (force || !this.pincode)) this.pincode = pincodeVal;
            if (data.display_name && (force || !this.address)) {
                const parts = data.display_name.split(',');
                this.address = parts.slice(0, 3).join(',').trim();
            }
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
            const cleanSlug = (this.slug || '').trim().toLowerCase();
            if (!cleanSlug) {
                this.slugStatus = '';
                return;
            }

            if (cleanSlug === this.initialSlug) {
                this.slugAvailable = true;
                this.slugStatus = 'Current storefront URL (No change needed)';
                return;
            }

            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res = await fetch(this.checkSlugUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify({ slug: cleanSlug })
                });
                const data = await res.json();
                this.slugAvailable = data.available;
                this.slugStatus = data.message;
                if (data.available) {
                    this.publicUrl = '{{ url("/store") }}/' + cleanSlug;
                }
            } catch (e) {
                console.error(e);
            }
        },

        set24x7AllDays() {
            window.dispatchEvent(new CustomEvent('set-timings-24x7'));
            window.dispatchEvent(new CustomEvent('toast', {
                detail: { message: '⚡ All 7 days configured to 24 Hours Open (00:00 - 23:59)! Click "Save Storefront Changes" below to save.', type: 'success' }
            }));
        },

        copyMondayToAll() {
            const openVal = document.getElementById('open-1')?.value || '08:00';
            const closeVal = document.getElementById('close-1')?.value || '22:00';
            const mondayClosed = document.querySelector('input[name="timings[1][is_closed]"]')?.checked || false;

            window.dispatchEvent(new CustomEvent('copy-monday', {
                detail: { open: openVal, close: closeVal, closed: mondayClosed }
            }));

            window.dispatchEvent(new CustomEvent('toast', {
                detail: { message: `Copied Monday schedule (${mondayClosed ? 'Closed' : openVal + ' - ' + closeVal}) to all 7 days! Click "Save Storefront Changes" to save.`, type: 'success' }
            }));
        },

        copyUrl() {
            const el = document.getElementById('shareable-store-url');
            if (el) {
                navigator.clipboard.writeText(el.value);
                this.copied = true;
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { message: 'Storefront URL copied to clipboard!', type: 'success' }
                }));
                setTimeout(() => { this.copied = false; }, 3000);
            }
        }
    };
}
</script>
@endpush
