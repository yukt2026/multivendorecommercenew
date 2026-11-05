<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\SellerStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSellerStatus
{
    /**
     * Handle an incoming request.
     * Enforce that pending sellers can only see onboarding, suspended sellers see blocked page,
     * and approved sellers can access the full seller portal.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('seller.login');
        }

        $seller = $user->seller;

        if (!$seller) {
            abort(403, 'User account is not linked to an active seller organisation.');
        }

        $currentRoute = $request->route()?->getName() ?? '';

        // Suspended seller check
        if ($seller->status === SellerStatus::SUSPENDED) {
            if ($currentRoute === 'seller.suspended' || $currentRoute === 'seller.logout') {
                return $next($request);
            }
            return redirect()->route('seller.suspended');
        }

        // Pending or unapproved KYC check
        if (in_array($seller->status, [SellerStatus::PENDING, SellerStatus::KYC_SUBMITTED, SellerStatus::REJECTED])) {
            if (str_starts_with($currentRoute, 'seller.onboarding') || $currentRoute === 'seller.logout') {
                return $next($request);
            }
            return redirect()->route('seller.onboarding');
        }

        // Suspended route blocked for approved sellers
        if ($seller->status === SellerStatus::APPROVED) {
            if ($currentRoute === 'seller.suspended') {
                return redirect()->route('seller.dashboard');
            }
        }

        return $next($request);
    }
}
