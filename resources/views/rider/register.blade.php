<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Delivery Partner Registration - OneStall</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-900 text-white min-h-full font-sans flex flex-col justify-center items-center p-4 py-8">

    <div class="max-w-md w-full space-y-6">
        <!-- Logo & Header -->
        <div class="text-center space-y-2">
            <div class="w-16 h-16 rounded-3xl bg-orange-500 text-white flex items-center justify-center mx-auto text-3xl shadow-lg shadow-orange-500/30">
                🛵
            </div>
            <h1 class="text-2xl font-black tracking-tight text-white">Join OneStall Delivery Fleet</h1>
            <p class="text-xs text-slate-400">Earn per order with instant payouts & flexible delivery shifts</p>
        </div>

        <!-- Flash Alerts -->
        @if(session('success'))
            <div class="bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 text-xs font-bold p-3 rounded-2xl text-center">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="bg-rose-500/20 border border-rose-500/40 text-rose-300 text-xs font-bold p-3 rounded-2xl text-center">
                {{ session('error') }}
            </div>
        @endif
        @if($errors->any())
            <div class="bg-rose-500/20 border border-rose-500/40 text-rose-300 text-xs font-bold p-3 rounded-2xl">
                <ul class="list-disc pl-5 space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Registration Form Card -->
        <div class="bg-slate-800 border border-slate-700/80 rounded-3xl p-6 shadow-xl space-y-4">
            <form action="{{ route('rider.register.submit') }}" method="POST" class="space-y-4">
                @csrf

                <!-- Full Name -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">
                        Full Name <span class="text-orange-400">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="name" 
                        value="{{ old('name') }}" 
                        required 
                        placeholder="e.g. Ramesh Kumar"
                        class="w-full p-3 text-sm rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
                    />
                </div>

                <!-- Phone & Email Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">
                            Phone Number <span class="text-orange-400">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="phone" 
                            value="{{ old('phone') }}" 
                            required 
                            placeholder="+919876543210"
                            class="w-full p-3 text-sm rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">
                            Email (Optional)
                        </label>
                        <input 
                            type="email" 
                            name="email" 
                            value="{{ old('email') }}" 
                            placeholder="rider@example.com"
                            class="w-full p-3 text-sm rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
                        />
                    </div>
                </div>

                <!-- Vehicle Type & Registration Plate -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">
                            Vehicle Type <span class="text-orange-400">*</span>
                        </label>
                        <select 
                            name="vehicle" 
                            required 
                            class="w-full p-3 text-sm rounded-xl bg-slate-900 border border-slate-700 text-white focus:outline-none focus:ring-2 focus:ring-orange-500"
                        >
                            <option value="Bike" {{ old('vehicle') === 'Bike' ? 'selected' : '' }}>Motorcycle / Bike</option>
                            <option value="Scooter" {{ old('vehicle') === 'Scooter' ? 'selected' : '' }}>Scooter / Activa</option>
                            <option value="Electric Bike" {{ old('vehicle') === 'Electric Bike' ? 'selected' : '' }}>Electric Bike / EV</option>
                            <option value="Bicycle" {{ old('vehicle') === 'Bicycle' ? 'selected' : '' }}>Bicycle / Cycle</option>
                            <option value="Van/Car" {{ old('vehicle') === 'Van/Car' ? 'selected' : '' }}>Delivery Van / Car</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">
                            Vehicle Reg. Number
                        </label>
                        <input 
                            type="text" 
                            name="vehicle_number" 
                            value="{{ old('vehicle_number') }}" 
                            placeholder="e.g. MH-12-AB-1234"
                            class="w-full p-3 text-sm rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500 uppercase"
                        />
                    </div>
                </div>

                <!-- Emergency Contact -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">
                            Emergency Contact Name
                        </label>
                        <input 
                            type="text" 
                            name="emergency_contact_name" 
                            value="{{ old('emergency_contact_name') }}" 
                            placeholder="Family member / Guardian"
                            class="w-full p-3 text-sm rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">
                            Emergency Phone
                        </label>
                        <input 
                            type="text" 
                            name="emergency_contact_phone" 
                            value="{{ old('emergency_contact_phone') }}" 
                            placeholder="+919876543211"
                            class="w-full p-3 text-sm rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
                        />
                    </div>
                </div>

                <!-- Password & Confirmation -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">
                            Create Password <span class="text-orange-400">*</span>
                        </label>
                        <input 
                            type="password" 
                            name="password" 
                            required 
                            placeholder="Min 6 characters"
                            class="w-full p-3 text-sm rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">
                            Confirm Password <span class="text-orange-400">*</span>
                        </label>
                        <input 
                            type="password" 
                            name="password_confirmation" 
                            required 
                            placeholder="Re-type password"
                            class="w-full p-3 text-sm rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
                        />
                    </div>
                </div>

                <!-- Terms & KYC notice -->
                <div class="pt-2">
                    <label class="flex items-start space-x-2.5 text-xs text-slate-400 cursor-pointer">
                        <input 
                            type="checkbox" 
                            name="terms" 
                            value="1" 
                            required 
                            {{ old('terms') ? 'checked' : '' }}
                            class="mt-0.5 rounded border-slate-700 bg-slate-900 text-orange-500 focus:ring-0"
                        >
                        <span>
                            I agree to the <a href="#" class="text-orange-400 hover:underline">OneStall Partner Terms of Service</a> and understand that my account requires KYC document verification before order assignments start.
                        </span>
                    </label>
                </div>

                <button 
                    type="submit" 
                    class="w-full py-3.5 bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white text-sm font-extrabold rounded-xl shadow-lg shadow-orange-500/20 transition cursor-pointer"
                >
                    Create Partner Account &amp; Proceed to KYC
                </button>
            </form>

            <div class="pt-2 border-t border-slate-700/60 text-center">
                <p class="text-xs text-slate-400">
                    Already registered as a partner?
                    <a href="{{ route('rider.login') }}" class="font-extrabold text-orange-400 hover:text-orange-300 transition underline underline-offset-2 ml-1">
                        Sign In here
                    </a>
                </p>
            </div>
        </div>

        <div class="text-center text-xs text-slate-500">
            &copy; {{ date('Y') }} OneStall Hyperlocal Logistics Fleet
        </div>
    </div>

</body>
</html>
