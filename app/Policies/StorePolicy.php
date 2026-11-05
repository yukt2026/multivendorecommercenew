<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

class StorePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stores.view') || $user->seller !== null || $user->accessibleStores()->isNotEmpty();
    }

    public function view(User $user, Store $store): bool
    {
        if ($user->can('stores.manage')) {
            return true;
        }

        if ($store->seller_id === $user->seller?->id) {
            return true;
        }

        return $user->hasStorePermission('orders.view', $store) || $user->hasStorePermission('inventory.view', $store);
    }

    public function create(User $user): bool
    {
        return $user->can('stores.create') || (bool) $user->seller;
    }

    public function update(User $user, Store $store): bool
    {
        if ($user->can('stores.manage')) {
            return true;
        }

        if ($store->seller_id === $user->seller?->id) {
            return true;
        }

        return $user->hasStorePermission('store.settings', $store);
    }

    public function delete(User $user, Store $store): bool
    {
        if ($user->can('stores.manage')) {
            return true;
        }

        return $store->seller_id === $user->seller?->id;
    }

    public function manageStaff(User $user, Store $store): bool
    {
        return $user->hasStorePermission('staff.manage', $store);
    }

    public function manageConnections(User $user, Store $store): bool
    {
        return $user->hasStorePermission('connections.manage', $store);
    }

    public function viewReports(User $user, Store $store): bool
    {
        return $user->hasStorePermission('reports.view', $store);
    }
}
