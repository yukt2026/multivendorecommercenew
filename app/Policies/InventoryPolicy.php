<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Inventory;
use App\Models\User;

class InventoryPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') 
            || $user->hasRole('seller_owner') 
            || (bool) $user->seller
            || $user->hasStorePermission('inventory.view');
    }

    public function view(User $user, Inventory $inventory): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->seller !== null && $inventory->location?->seller_id === $user->seller->id) {
            return true;
        }

        return $user->hasStorePermission('inventory.view', $inventory->location?->store);
    }

    public function update(User $user, Inventory $inventory): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->seller !== null && $inventory->location?->seller_id === $user->seller->id) {
            return true;
        }

        return $user->hasStorePermission('inventory.manage', $inventory->location?->store);
    }
}
