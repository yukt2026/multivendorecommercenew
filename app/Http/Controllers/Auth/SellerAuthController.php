<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\SellerStatus;
use App\Enums\StoreStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CustomerLoginRequest;
use App\Http\Requests\Auth\SellerRegisterRequest;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use App\Services\Auth\AuthRedirectService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SellerAuthController extends Controller
{
    public function __construct(
        protected AuthRedirectService $redirectService
    ) {}

    public function showRegister(): View
    {
        return view('auth.seller-register');
    }

    public function register(SellerRegisterRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->input('owner_name'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
                'password' => Hash::make($request->input('password')),
                'status' => UserStatus::ACTIVE,
            ]);

            $user->assignRole('seller_owner');

            $seller = Seller::create([
                'user_id' => $user->id,
                'business_name' => $request->input('business_name'),
                'legal_name' => $request->input('business_name'),
                'seller_type' => $request->input('seller_type'),
                'status' => SellerStatus::PENDING,
            ]);

            // Create initial default Store shell for this seller
            Store::create([
                'seller_id' => $seller->id,
                'name' => $request->input('business_name'),
                'slug' => Str::slug($request->input('business_name')) . '-' . rand(100, 999),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
                'status' => StoreStatus::INACTIVE,
            ]);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        $this->redirectService->handleLoginSuccess($user, $request);

        return redirect()->route('seller.onboarding')->with('success', 'Your seller registration has been submitted. Please complete KYC onboarding.');
    }

    public function showLogin(): View
    {
        return view('auth.seller-login');
    }

    public function login(CustomerLoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = Auth::user();

        // Check if user has seller role or seller profile
        if (!$user->hasAnyRole(['seller_owner', 'store_manager', 'inventory_staff', 'packing_staff']) && !$user->seller) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'Access denied. This portal is exclusively for verified OneStall sellers and store staff.',
            ]);
        }

        $redirectUrl = $this->redirectService->handleLoginSuccess($user, $request);

        return redirect()->intended($redirectUrl);
    }

    public function onboarding(): View
    {
        $user = Auth::user();
        $seller = $user?->seller;

        return view('seller.onboarding', compact('user', 'seller'));
    }

    public function suspended(): View
    {
        $user = Auth::user();
        $seller = $user?->seller;

        return view('seller.suspended', compact('user', 'seller'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($user && function_exists('activity')) {
            activity('auth')->causedBy($user)->log('Seller signed out');
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('seller.login')->with('info', 'You have been signed out from the Seller Panel.');
    }
}
