@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'Add Store Outlet'])

@section('content')
<div class="max-w-4xl space-y-6">
    <x-page-header 
        title="Add Physical Store Outlet" 
        subtitle="Establish a new retail outlet, warehouse shelf, or quick commerce dark store with separate inventory."
        :breadcrumbs="['Seller Studio' => route('seller.dashboard'), 'Stores' => route('seller.stores.index'), 'Add Outlet' => '#']"
    />

    <form method="POST" action="{{ route('seller.stores.store') }}" class="space-y-6" x-data="{
        lat: {{ old('lat', 12.9716) }},
        lng: {{ old('lng', 77.5946) }},
        address: @js(old('address', '')),
        city: @js(old('city', 'Bengaluru')),
        state: @js(old('state', 'Karnataka')),
        pincode: @js(old('pincode', '560034')),
        isLocating: false,

        detectLocation() {
            this.isLocating = true;

            if (!navigator.geolocation) {
                this.detectViaIp('Browser does not support direct GPS. Located via network.');
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.lat = parseFloat(pos.coords.latitude.toFixed(6));
                    this.lng = parseFloat(pos.coords.longitude.toFixed(6));
                    this.reverseGeocode(this.lat, this.lng);
                    this.isLocating = false;
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { message: '📍 GPS coordinates locked and address detected!', type: 'success' }
                    }));
                },
                (err) => {
                    console.warn('Geolocation notice:', err.message);
                    this.detectViaIp(err.code === 1 ? 'Location permission denied. Located via network IP.' : 'GPS unavailable. Located via network IP.');
                },
                { enableHighAccuracy: false, timeout: 7000, maximumAge: 60000 }
            );
        },

        async detectViaIp(msg = '') {
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
                    this.lat = parseFloat(lat.toFixed(6));
                    this.lng = parseFloat(lng.toFixed(6));
                    if (city && !this.city) this.city = city;
                    if (state && !this.state) this.state = state;
                    if (postal && !this.pincode) this.pincode = postal;
                    this.reverseGeocode(this.lat, this.lng);
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { message: msg || `📍 Located via network: ${city || ''}, ${state || ''}`, type: 'info' }
                    }));
                } else {
                    throw new Error('IP geolocation unavailable');
                }
            } catch (e) {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { message: 'Could not auto-detect location. Please enter address manually.', type: 'warning' }
                }));
            } finally {
                this.isLocating = false;
            }
        },

        async reverseGeocode(lat, lng) {
            try {
                const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`);
                if (!res.ok) return;
                const data = await res.json();
                if (data && data.address) {
                    const a = data.address;
                    if (a.city || a.town || a.village) this.city = a.city || a.town || a.village;
                    if (a.state) this.state = a.state;
                    if (a.postcode && /^[1-9][0-9]{5}$/.test(a.postcode)) this.pincode = a.postcode;
                    if (data.display_name && !this.address) {
                        this.address = data.display_name.split(',').slice(0, 3).join(',').trim();
                    }
                }
            } catch (e) {}
        }
    }">
        @csrf

        <!-- General Outlet Details -->
        <x-card title="Outlet Identity & Commercial Branding">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Outlet / Branch Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" placeholder="e.g., Zenith Supermarket - Koramangala" required class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500" />
                    @error('name') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Store URL Slug (Optional)</label>
                    <input type="text" name="slug" value="{{ old('slug') }}" placeholder="zenith-koramangala" class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 font-mono" />
                    @error('slug') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Store Direct Phone</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" placeholder="9876543210" class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white" />
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Store Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" placeholder="store@merchant.com" class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white" />
                </div>
            </div>
        </x-card>

        <!-- Physical Location & Geo Coordinates -->
        <x-card title="Physical Location & Delivery Coordinates">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Shop / Building Address <span class="text-rose-500">*</span></label>
                    <textarea name="address" x-model="address" rows="2" required placeholder="Shop #12, Ground Floor, 100ft Road..." class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white">{{ old('address') }}</textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">City <span class="text-rose-500">*</span></label>
                        <input type="text" name="city" x-model="city" value="{{ old('city', 'Bengaluru') }}" required class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">State <span class="text-rose-500">*</span></label>
                        <input type="text" name="state" x-model="state" value="{{ old('state', 'Karnataka') }}" required class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Postal Pincode <span class="text-rose-500">*</span></label>
                        <input type="text" name="pincode" x-model="pincode" value="{{ old('pincode', '560034') }}" required class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white" />
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
                    <div class="flex items-center justify-between mb-3">
                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200">Delivery Geo-Coordinates (Quick Commerce Radius Origin)</div>
                        <button type="button" @click="detectLocation()" :disabled="isLocating" class="px-3 py-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 font-bold text-xs border border-emerald-300 dark:border-emerald-800 hover:bg-emerald-100 transition flex items-center space-x-1.5 shrink-0 shadow-2xs">
                            <svg x-show="!isLocating" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <svg x-show="isLocating" class="w-3.5 h-3.5 animate-spin text-emerald-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            <span x-text="isLocating ? 'Detecting Location...' : '📍 Detect via Browser GPS'"></span>
                        </button>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-medium text-slate-500 mb-1">Latitude</label>
                            <input type="number" step="0.000001" name="lat" x-model="lat" required class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 font-mono text-slate-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-slate-500 mb-1">Longitude</label>
                            <input type="number" step="0.000001" name="lng" x-model="lng" required class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 font-mono text-slate-900 dark:text-white" />
                        </div>
                    </div>
                </div>
            </div>
        </x-card>

        <!-- Operating Hours & Delivery Radius -->
        <x-card title="Operating Hours & Fulfillment Radius">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Delivery Radius (km)</label>
                    <input type="number" step="0.5" name="delivery_radius_km" value="{{ old('delivery_radius_km', 8.0) }}" class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 font-bold" />
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Daily Opening Time</label>
                    <input type="time" name="opens_at" value="{{ old('opens_at', '09:00') }}" class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 font-mono" />
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Daily Closing Time</label>
                    <input type="time" name="closes_at" value="{{ old('closes_at', '22:00') }}" class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 font-mono" />
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-slate-200 dark:border-slate-800 flex flex-wrap gap-6">
                <label class="flex items-center space-x-2 text-xs font-bold cursor-pointer">
                    <input type="checkbox" name="accepts_quick_commerce" value="1" checked class="rounded border-slate-300 text-amber-500 focus:ring-amber-500" />
                    <span>⚡ Enable 10-30 min Quick Commerce dispatch</span>
                </label>
                <label class="flex items-center space-x-2 text-xs font-bold cursor-pointer">
                    <input type="checkbox" name="accepts_pickup" value="1" checked class="rounded border-slate-300 text-sky-500 focus:ring-sky-500" />
                    <span>Enable Customer Click &amp; Collect Pickup</span>
                </label>
            </div>
        </x-card>

        <div class="flex items-center justify-end space-x-3">
            <x-button variant="outline" size="sm" href="{{ route('seller.stores.index') }}">Cancel</x-button>
            <x-button variant="primary" size="sm" type="submit">Create Outlet &amp; Storage Shelf</x-button>
        </div>
    </form>
</div>
@endsection
