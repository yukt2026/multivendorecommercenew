<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('orders.view');
    }

    public function view(User $user, Order $order): bool
    {
        if ($user->can('orders.view') && $user->hasAnyRole(['admin', 'support_admin'])) {
            return true;
        }

        return $order->customer_id === $user->customer?->id;
    }

    public function cancel(User $user, Order $order): bool
    {
        if ($user->can('orders.cancel')) {
            return true;
        }

        return $order->customer_id === $user->customer?->id;
    }
}
