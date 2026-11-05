<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;

class BrandPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('brands.manage') || $user->can('products.view');
    }

    public function view(User $user, Brand $brand): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('brands.manage');
    }

    public function update(User $user, Brand $brand): bool
    {
        return $user->can('brands.manage');
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $user->can('brands.manage');
    }
}
