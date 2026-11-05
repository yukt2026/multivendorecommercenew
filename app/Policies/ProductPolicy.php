<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('seller_owner') || (bool) $user->seller;
    }

    public function view(User $user, Product $product): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->seller !== null && $product->seller_id === $user->seller->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin') || ($user->seller !== null && $user->can('products.create'));
    }

    public function update(User $user, Product $product): bool
    {
        if ($user->hasRole('admin')) {
            return $user->can('products.edit');
        }

        return $user->seller !== null && $product->seller_id === $user->seller->id;
    }

    public function delete(User $user, Product $product): bool
    {
        if ($user->hasRole('admin')) {
            return $user->can('products.delete');
        }

        return $user->seller !== null && $product->seller_id === $user->seller->id;
    }

    public function approve(User $user, Product $product): bool
    {
        return $user->can('products.approve');
    }

    public function reject(User $user, Product $product): bool
    {
        return $user->can('products.approve');
    }
}
