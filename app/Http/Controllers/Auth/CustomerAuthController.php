<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CustomerLoginRequest;
use App\Http\Requests\Auth\CustomerRegisterRequest;
use App\Models\Customer;
use App\Models\User;
use App\Services\Auth\AuthRedirectService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class CustomerAuthController extends Controller
{
    public function __construct(
        protected AuthRedirectService $redirectService
    ) {}

    public function showLogin(): View
    {
        return view('auth.customer-login');
    }

    public function login(CustomerLoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = Auth::user();
        $redirectUrl = $this->redirectService->handleLoginSuccess($user, $request);

        return redirect()->intended($redirectUrl);
    }

    public function showRegister(): View
    {
        return view('auth.customer-register');
    }

    public function register(CustomerRegisterRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
                'password' => Hash::make($request->input('password')),
                'status' => UserStatus::ACTIVE,
            ]);

            $user->assignRole('customer');

            Customer::create([
                'user_id' => $user->id,
            ]);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        $redirectUrl = $this->redirectService->handleLoginSuccess($user, $request);

        return redirect($redirectUrl)->with('success', 'Welcome to OneStall.in! Your account has been created.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($user && function_exists('activity')) {
            activity('auth')->causedBy($user)->log('User signed out');
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('info', 'You have been successfully signed out.');
    }
}
