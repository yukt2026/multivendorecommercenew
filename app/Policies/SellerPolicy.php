<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Seller;
use App\Models\User;

class SellerPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sellers.view');
    }

    public function view(User $user, Seller $seller): bool
    {
        if ($user->can('sellers.view')) {
            return true;
        }

        return $seller->user_id === $user->id;
    }

    public function update(User $user, Seller $seller): bool
    {
        if ($user->can('sellers.manage')) {
            return true;
        }

        return $seller->user_id === $user->id;
    }

    public function approve(User $user, Seller $seller): bool
    {
        return $user->can('sellers.approve');
    }

    public function reject(User $user, Seller $seller): bool
    {
        return $user->can('sellers.approve');
    }

    public function suspend(User $user, Seller $seller): bool
    {
        return $user->can('sellers.suspend');
    }

    public function reactivate(User $user, Seller $seller): bool
    {
        return $user->can('sellers.suspend');
    }

    public function updateCommission(User $user, Seller $seller): bool
    {
        return $user->can('sellers.manage') || $user->can('commissions.manage');
    }

    public function reviewDocument(User $user, Seller $seller): bool
    {
        return $user->can('sellers.approve');
    }

    public function verifyBank(User $user, Seller $seller): bool
    {
        return $user->can('sellers.approve');
    }
}
