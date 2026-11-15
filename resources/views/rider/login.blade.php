<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Rider Login - OneStall Delivery Fleet</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-900 text-white min-h-full font-sans flex flex-col justify-center items-center p-4">

    <div class="max-w-sm w-full space-y-6">
        <!-- Logo & Branding -->
        <div class="text-center space-y-2">
            <div class="w-16 h-16 rounded-3xl bg-orange-500 text-white flex items-center justify-center mx-auto text-3xl shadow-lg shadow-orange-500/30">
                🛵
            </div>
            <h1 class="text-2xl font-black tracking-tight text-white">OneStall Rider</h1>
            <p class="text-xs text-slate-400">Hyperlocal Delivery Partner Portal</p>
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

        <!-- Login Form Card -->
        <div class="bg-slate-800 border border-slate-700/80 rounded-3xl p-6 shadow-xl space-y-4">
            <form action="{{ route('rider.login.submit') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">
                        Phone Number or Email
                    </label>
                    <input 
                        type="text" 
                        name="login" 
                        value="{{ old('login') }}" 
                        required 
                        placeholder="+919876543210"
                        class="w-full p-3 text-sm rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
                    />
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">
                        Password
                    </label>
                    <input 
                        type="password" 
                        name="password" 
                        required 
                        placeholder="••••••••"
                        class="w-full p-3 text-sm rounded-xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500"
                    />
                </div>

                <div class="flex items-center justify-between text-xs text-slate-400">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" name="remember" class="rounded border-slate-700 bg-slate-900 text-orange-500 focus:ring-0">
                        <span>Remember Me</span>
                    </label>
                    <a href="{{ route('rider.support') }}" class="text-orange-400 hover:underline">Help & Support</a>
                </div>

                <button 
                    type="submit" 
                    class="w-full py-3.5 bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white text-sm font-extrabold rounded-xl shadow-lg shadow-orange-500/20 transition cursor-pointer"
                >
                    Sign In to Dashboard
                </button>
            </form>

            <div class="pt-2 border-t border-slate-700/60 text-center">
                <p class="text-xs text-slate-400">
                    Want to deliver with us?
                    <a href="{{ route('rider.register') }}" class="font-extrabold text-orange-400 hover:text-orange-300 transition underline underline-offset-2 ml-1">
                        Register as Delivery Partner
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
