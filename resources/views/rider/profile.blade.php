@extends('layouts.rider', ['title' => 'Rider Profile & Account - OneStall'])

@section('content')
<div class="space-y-4" x-data="{ 
    showLogoutModal: false, 
    showRemovePhotoModal: false,
    photoPreview: null,
    previewPhoto(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                this.photoPreview = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    },
    clearPhotoPreview() {
        this.photoPreview = null;
        document.getElementById('avatar-input').value = '';
    }
}">

    <!-- Page Header -->
    <div class="space-y-1">
        <h1 class="text-base font-black text-slate-900">Account &amp; Partner Profile</h1>
        <p class="text-xs text-slate-500">Manage your profile photo, vehicle details, security, and fleet preferences.</p>
    </div>

    <!-- Profile Overview Card -->
    <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-xs space-y-4">
        <div class="flex items-center space-x-4 pb-4 border-b border-slate-100">
            <!-- Profile Avatar Thumbnail -->
            <div class="relative shrink-0">
                @if($rider->avatar_url)
                    <img 
                        src="{{ $rider->avatar_url }}" 
                        alt="{{ $rider->name }}" 
                        class="w-16 h-16 rounded-2xl object-cover border-2 border-orange-500 shadow-sm"
                    />
                @else
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-orange-500 to-amber-400 text-white font-black text-2xl flex items-center justify-center shadow-sm">
                        {{ strtoupper(substr($rider->name, 0, 1)) }}
                    </div>
                @endif
                <div class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-slate-900 text-white text-[10px] flex items-center justify-center border-2 border-white">
                    🛵
                </div>
            </div>

            <div class="flex-1 min-w-0">
                <h2 class="text-base font-extrabold text-slate-900 leading-tight truncate">{{ $rider->name }}</h2>
                <p class="text-xs font-mono text-slate-500">{{ $rider->phone }}</p>
                <div class="mt-1 flex items-center space-x-1.5">
                    @if($rider->approval_status === 'approved')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800">
                            ✓ KYC Verified &amp; Active
                        </span>
                    @elseif(in_array($rider->approval_status, ['pending', 'under_review']))
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-800">
                            ⏳ KYC {{ ucfirst(str_replace('_', ' ', $rider->approval_status)) }}
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-100 text-rose-800" title="{{ $rider->rejection_reason }}">
                            ✕ Application Rejected
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 text-xs">
            <div class="bg-slate-50 p-2.5 rounded-2xl border border-slate-100">
                <span class="text-[10px] font-bold text-slate-400 uppercase block">Vehicle Type</span>
                <span class="font-bold text-slate-800 truncate block">{{ $rider->vehicle }}</span>
            </div>
            <div class="bg-slate-50 p-2.5 rounded-2xl border border-slate-100">
                <span class="text-[10px] font-bold text-slate-400 uppercase block">Plate Number</span>
                <span class="font-mono font-bold text-slate-800 truncate block">{{ $rider->vehicle_number ?: 'Not Set' }}</span>
            </div>
            <div class="bg-slate-50 p-2.5 rounded-2xl border border-slate-100">
                <span class="text-[10px] font-bold text-slate-400 uppercase block">Linked Email</span>
                <span class="text-slate-700 truncate block">{{ $user?->email ?? 'rider_' . $rider->id . '@onestall.internal' }}</span>
            </div>
            <div class="bg-slate-50 p-2.5 rounded-2xl border border-slate-100">
                <span class="text-[10px] font-bold text-slate-400 uppercase block">Member Since</span>
                <span class="text-slate-800 font-semibold">{{ $rider->created_at?->format('M Y') ?? 'Recent' }}</span>
            </div>
        </div>

        <!-- KYC Verification Shortcut Button -->
        <a href="{{ route('rider.kyc') }}" class="mt-2 bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white rounded-2xl p-3.5 shadow-sm flex items-center justify-between transition">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 rounded-xl bg-white/20 flex items-center justify-center text-base">
                    📄
                </div>
                <div>
                    <div class="font-extrabold text-xs">KYC Documents &amp; Verification</div>
                    <div class="text-[10px] text-white/85">Driving License, RC, Aadhaar &amp; Insurance</div>
                </div>
            </div>
            <div class="flex items-center space-x-1 text-xs font-black bg-white/20 px-2.5 py-1 rounded-xl">
                <span>Manage</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </div>
        </a>
    </div>

    <!-- Profile Photo Upload & Change Section -->
    <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-xs space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-800 flex items-center space-x-1.5">
                <span>📸</span>
                <span>Profile Photo</span>
            </h3>
            @if($rider->avatar)
                <button 
                    type="button" 
                    @click="showRemovePhotoModal = true"
                    class="text-[11px] font-bold text-rose-600 hover:text-rose-700 underline cursor-pointer"
                >
                    Remove Photo
                </button>
            @endif
        </div>

        <form action="{{ route('rider.profile.avatar') }}" method="POST" enctype="multipart/form-data" class="space-y-3">
            @csrf
            
            <div class="flex items-center space-x-4">
                <!-- Preview area -->
                <div class="w-16 h-16 rounded-2xl border-2 border-dashed border-slate-300 flex items-center justify-center shrink-0 overflow-hidden bg-slate-50 relative">
                    <template x-if="photoPreview">
                        <img :src="photoPreview" class="w-full h-full object-cover">
                    </template>
                    <template x-if="!photoPreview">
                        @if($rider->avatar_url)
                            <img src="{{ $rider->avatar_url }}" class="w-full h-full object-cover">
                        @else
                            <span class="text-slate-400 text-xs font-bold">No Photo</span>
                        @endif
                    </template>
                </div>

                <!-- Input & Actions -->
                <div class="flex-1 space-y-1.5">
                    <label 
                        for="avatar-input" 
                        class="inline-flex items-center space-x-1.5 px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold rounded-xl cursor-pointer border border-slate-200 transition"
                    >
                        <span>📷 Choose Photo or Take Selfie</span>
                        <input 
                            id="avatar-input" 
                            type="file" 
                            name="avatar" 
                            accept="image/*" 
                            capture="user" 
                            @change="previewPhoto" 
                            class="hidden"
                            required
                        >
                    </label>
                    <p class="text-[10px] text-slate-400">JPG, PNG, WEBP (Max: 2MB). Camera or Gallery.</p>
                </div>
            </div>

            <!-- Preview actions -->
            <div x-show="photoPreview" x-cloak class="flex items-center space-x-2 pt-2 border-t border-slate-100">
                <button 
                    type="submit" 
                    class="flex-1 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-xl font-bold text-xs shadow-xs transition cursor-pointer"
                >
                    ✓ Save New Photo
                </button>
                <button 
                    type="button" 
                    @click="clearPhotoPreview" 
                    class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl font-bold text-xs transition cursor-pointer"
                >
                    Cancel
                </button>
            </div>
        </form>
    </div>

    <!-- Edit Profile Details Form -->
    <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-xs space-y-4">
        <h3 class="text-xs font-black uppercase tracking-wider text-slate-800 flex items-center space-x-1.5">
            <span>✏️</span>
            <span>Personal &amp; Vehicle Information</span>
        </h3>

        <form action="{{ route('rider.profile.update') }}" method="POST" class="space-y-3">
            @csrf
            @method('PUT')

            <!-- Name -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                    Full Name
                </label>
                <input 
                    type="text" 
                    name="name" 
                    value="{{ old('name', $rider->name) }}" 
                    required 
                    class="w-full text-xs font-semibold p-2.5 rounded-xl border border-slate-300 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-orange-500 transition"
                />
            </div>

            <!-- Phone (Readonly with Note) -->
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-xs font-bold text-slate-700">
                        Registered Mobile Number
                    </label>
                    <span class="text-[10px] text-slate-400 font-medium">Locked for Fleet Security</span>
                </div>
                <div class="relative">
                    <input 
                        type="text" 
                        value="{{ $rider->phone }}" 
                        disabled 
                        class="w-full text-xs font-mono font-bold p-2.5 rounded-xl border border-slate-200 bg-slate-100 text-slate-500 cursor-not-allowed"
                    />
                    <span class="absolute right-3 top-2.5 text-xs text-slate-400">🔒</span>
                </div>
            </div>

            <!-- Email -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                    Email Address
                </label>
                <input 
                    type="email" 
                    name="email" 
                    value="{{ old('email', $user?->email) }}" 
                    placeholder="partner@onestall.com" 
                    class="w-full text-xs p-2.5 rounded-xl border border-slate-300 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-orange-500 transition"
                />
            </div>

            <!-- Vehicle Type -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                    Fleet Vehicle Type
                </label>
                <select 
                    name="vehicle" 
                    required 
                    class="w-full text-xs font-semibold p-2.5 rounded-xl border border-slate-300 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-orange-500 transition"
                >
                    @php
                        $types = ['Electric Scooter', 'Motorcycle', 'Bicycle', 'Delivery Van', 'Three-Wheeler Auto'];
                    @endphp
                    @foreach($types as $type)
                        <option value="{{ $type }}" {{ old('vehicle', $rider->vehicle) === $type ? 'selected' : '' }}>
                            {{ $type }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Vehicle Plate -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                    Vehicle Registration Number (Plate)
                </label>
                <input 
                    type="text" 
                    name="vehicle_number" 
                    value="{{ old('vehicle_number', $rider->vehicle_number) }}" 
                    placeholder="e.g. KA-05-EQ-9821" 
                    class="w-full text-xs font-mono uppercase p-2.5 rounded-xl border border-slate-300 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-orange-500 transition"
                />
            </div>

            <div class="pt-2 border-t border-slate-100">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Emergency Details</span>

                <!-- Emergency Contact Name -->
                <div class="mb-3">
                    <label class="block text-xs font-bold text-slate-700 mb-1">
                        Emergency Contact Person
                    </label>
                    <input 
                        type="text" 
                        name="emergency_contact_name" 
                        value="{{ old('emergency_contact_name', $rider->emergency_contact_name) }}" 
                        placeholder="e.g. Spouse / Parent name" 
                        class="w-full text-xs p-2.5 rounded-xl border border-slate-300 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-orange-500 transition"
                    />
                </div>

                <!-- Emergency Contact Phone -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">
                        Emergency Contact Phone
                    </label>
                    <input 
                        type="text" 
                        name="emergency_contact_phone" 
                        value="{{ old('emergency_contact_phone', $rider->emergency_contact_phone) }}" 
                        placeholder="+919876500000" 
                        class="w-full text-xs font-mono p-2.5 rounded-xl border border-slate-300 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-orange-500 transition"
                    />
                </div>
            </div>

            <button 
                type="submit" 
                class="w-full py-3 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-bold text-xs shadow-xs transition cursor-pointer mt-2"
            >
                Save Profile Updates
            </button>
        </form>
    </div>

    <!-- Change Password Section -->
    <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-xs space-y-4">
        <h3 class="text-xs font-black uppercase tracking-wider text-slate-800 flex items-center space-x-1.5">
            <span>🔐</span>
            <span>Security &amp; Change Password</span>
        </h3>

        <form action="{{ route('rider.profile.password') }}" method="POST" class="space-y-3">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                    Current Password
                </label>
                <input 
                    type="password" 
                    name="current_password" 
                    required 
                    placeholder="••••••••" 
                    class="w-full text-xs p-2.5 rounded-xl border border-slate-300 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-orange-500 transition"
                />
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                    New Password
                </label>
                <input 
                    type="password" 
                    name="password" 
                    required 
                    placeholder="Min 6 characters" 
                    class="w-full text-xs p-2.5 rounded-xl border border-slate-300 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-orange-500 transition"
                />
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                    Confirm New Password
                </label>
                <input 
                    type="password" 
                    name="password_confirmation" 
                    required 
                    placeholder="Repeat new password" 
                    class="w-full text-xs p-2.5 rounded-xl border border-slate-300 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-orange-500 transition"
                />
            </div>

            <button 
                type="submit" 
                class="w-full py-2.5 bg-slate-800 hover:bg-slate-700 text-white rounded-xl font-bold text-xs shadow-xs transition cursor-pointer"
            >
                Update Password
            </button>
        </form>
    </div>

    <!-- Account Sign Out Button -->
    <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-xs text-center space-y-2">
        <button 
            type="button" 
            @click="showLogoutModal = true"
            class="w-full py-3 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-2xl font-black text-xs border border-rose-200 transition cursor-pointer flex items-center justify-center space-x-2 shadow-xs"
        >
            <span>🚪</span>
            <span>Sign Out of Rider Account</span>
        </button>
        <p class="text-[10px] text-slate-400">Signed in as {{ $rider->name }} ({{ $rider->phone }})</p>
    </div>

    <!-- Modal: Confirmation Before Sign Out -->
    <div 
        x-show="showLogoutModal" 
        x-cloak 
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4"
    >
        <div 
            class="bg-white rounded-3xl p-6 w-full max-w-sm shadow-xl space-y-4 text-center animate-fade-in" 
            @click.away="showLogoutModal = false"
        >
            <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-600 text-xl font-bold flex items-center justify-center mx-auto">
                🚪
            </div>
            <div>
                <h3 class="font-extrabold text-base text-slate-900">Sign Out of Rider Portal?</h3>
                <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                    You will not receive new order dispatch assignments while signed out. You can sign back in at any time.
                </p>
            </div>
            <div class="grid grid-cols-2 gap-2 pt-2">
                <button 
                    type="button" 
                    @click="showLogoutModal = false" 
                    class="py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition cursor-pointer"
                >
                    Cancel
                </button>
                <form action="{{ route('rider.logout') }}" method="POST">
                    @csrf
                    <button 
                        type="submit" 
                        class="w-full py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-black rounded-xl shadow-sm transition cursor-pointer"
                    >
                        Yes, Sign Out
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Confirmation Before Removing Photo -->
    <div 
        x-show="showRemovePhotoModal" 
        x-cloak 
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4"
    >
        <div 
            class="bg-white rounded-3xl p-6 w-full max-w-sm shadow-xl space-y-4 text-center animate-fade-in" 
            @click.away="showRemovePhotoModal = false"
        >
            <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-600 text-xl font-bold flex items-center justify-center mx-auto">
                🗑️
            </div>
            <div>
                <h3 class="font-extrabold text-base text-slate-900">Remove Profile Photo?</h3>
                <p class="text-xs text-slate-500 mt-1">
                    Your photo will be deleted from your rider profile and replaced with your default initials icon.
                </p>
            </div>
            <div class="grid grid-cols-2 gap-2 pt-2">
                <button 
                    type="button" 
                    @click="showRemovePhotoModal = false" 
                    class="py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition cursor-pointer"
                >
                    Cancel
                </button>
                <form action="{{ route('rider.profile.avatar.remove') }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button 
                        type="submit" 
                        class="w-full py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-black rounded-xl shadow-sm transition cursor-pointer"
                    >
                        Remove
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
