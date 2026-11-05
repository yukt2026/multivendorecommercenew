<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\OtpSendRequest;
use App\Http\Requests\Auth\OtpVerifyRequest;
use App\Models\Customer;
use App\Models\User;
use App\Services\Auth\AuthRedirectService;
use App\Services\Otp\OtpServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OtpAuthController extends Controller
{
    public function __construct(
        protected OtpServiceInterface $otpService,
        protected AuthRedirectService $redirectService
    ) {}

    public function showOtpForm(): View
    {
        return view('auth.otp-login');
    }

    public function sendOtp(OtpSendRequest $request): RedirectResponse
    {
        $phone = $request->input('phone');
        $result = $this->otpService->send($phone);

        if (!$result['success']) {
            throw ValidationException::withMessages([
                'phone' => $result['message'],
            ]);
        }

        return redirect()->route('login.otp')
            ->with('phone', $phone)
            ->with('otp_sent', true)
            ->with('success', $result['message']);
    }

    public function verifyOtp(OtpVerifyRequest $request): RedirectResponse
    {
        $phone = $request->input('phone');
        $code = $request->input('otp');

        $isValid = $this->otpService->verify($phone, $code);

        if (!$isValid) {
            throw ValidationException::withMessages([
                'otp' => 'The provided OTP is invalid or has expired (validity is 5 minutes).',
            ]);
        }

        // Find user by normalized phone or create new customer
        $normalizedPhone = preg_replace('/[^0-9]/', '', $phone);
        $user = User::where('phone', $phone)
            ->orWhere('phone', '+' . $normalizedPhone)
            ->orWhere('phone', 'like', "%{$normalizedPhone}")
            ->first();

        if (!$user) {
            // Instant sign-up for quick commerce customers via OTP
            $user = DB::transaction(function () use ($phone) {
                $user = User::create([
                    'name' => 'Customer ' . substr($phone, -4),
                    'email' => 'user_' . Str::random(8) . '@onestall.local',
                    'phone' => $phone,
                    'password' => Hash::make(Str::random(32)),
                    'status' => UserStatus::ACTIVE,
                ]);

                $user->assignRole('customer');

                Customer::create([
                    'user_id' => $user->id,
                ]);

                return $user;
            });
        }

        Auth::login($user);

        $redirectUrl = $this->redirectService->handleLoginSuccess($user, $request);

        return redirect()->intended($redirectUrl)->with('success', 'Logged in successfully via Phone OTP.');
    }
}
