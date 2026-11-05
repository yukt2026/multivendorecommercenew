<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\SellerStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthRedirectService
{
    /**
     * Complete user login post-actions (session regeneration, last_login_at, audit log)
     * and determine redirect target.
     */
    public function handleLoginSuccess(User $user, Request $request, string $authGuard = 'web'): string
    {
        $oldSessionId = $request->session()->getId();

        // 1. Merge guest session cart into user's account cart
        try {
            app(\App\Services\Storefront\CartService::class)->mergeSessionCart($oldSessionId, $user);
        } catch (\Throwable $e) {
            report($e);
        }

        // 2. Merge guest session wishlist into customer account
        try {
            $guestWishlist = $request->session()->get('guest_wishlist', []);
            if (!empty($guestWishlist)) {
                $customer = $user->customer ?: \App\Models\Customer::firstOrCreate(['user_id' => $user->id]);
                foreach ($guestWishlist as $pId) {
                    \App\Models\Wishlist::firstOrCreate([
                        'customer_id' => $customer->id,
                        'product_id' => (int) $pId,
                    ]);
                }
                $request->session()->forget('guest_wishlist');
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $request->session()->regenerate();

        // Update last login timestamp
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        // Spatie activitylog audit entry
        if (function_exists('activity')) {
            activity('auth')
                ->causedBy($user)
                ->withProperties([
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ])
                ->log('User successfully signed in');
        }

        return $this->getRedirectPath($user);
    }

    /**
     * Determine role-based redirect URL.
     */
    public function getRedirectPath(User $user): string
    {
        if ($user->hasAnyRole(['super_admin', 'admin', 'support_admin'])) {
            return route('admin.dashboard');
        }

        if ($user->hasAnyRole(['seller_owner', 'store_manager', 'inventory_staff', 'packing_staff'])) {
            $seller = $user->seller;
            if ($seller) {
                if (in_array($seller->status, [SellerStatus::PENDING, SellerStatus::KYC_SUBMITTED, SellerStatus::REJECTED])) {
                    return route('seller.onboarding');
                }
                if ($seller->status === SellerStatus::SUSPENDED) {
                    return route('seller.suspended');
                }
            }
            return route('seller.dashboard');
        }

        return session()->pull('url.intended', route('home'));
    }
}
