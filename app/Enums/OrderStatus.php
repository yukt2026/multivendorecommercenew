<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case PLACED = 'placed';
    case CONFIRMED = 'confirmed';
    case PROCESSING = 'processing';
    case READY_FOR_PICKUP = 'ready_for_pickup';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Payment',
            self::PLACED => 'Order Placed',
            self::CONFIRMED => 'Order Confirmed',
            self::PROCESSING => 'Processing',
            self::READY_FOR_PICKUP => 'Ready for Pickup',
            self::OUT_FOR_DELIVERY => 'Out for Delivery',
            self::DELIVERED => 'Delivered',
            self::CANCELLED => 'Cancelled',
            self::REFUNDED => 'Refunded',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'bg-amber-100 text-amber-800 border-amber-300',
            self::PLACED => 'bg-sky-100 text-sky-800 border-sky-300',
            self::CONFIRMED => 'bg-blue-100 text-blue-800 border-blue-300',
            self::PROCESSING => 'bg-indigo-100 text-indigo-800 border-indigo-300',
            self::READY_FOR_PICKUP => 'bg-purple-100 text-purple-800 border-purple-300',
            self::OUT_FOR_DELIVERY => 'bg-orange-100 text-orange-800 border-orange-300',
            self::DELIVERED => 'bg-emerald-100 text-emerald-800 border-emerald-300',
            self::CANCELLED => 'bg-rose-100 text-rose-800 border-rose-300',
            self::REFUNDED => 'bg-slate-100 text-slate-800 border-slate-300',
        };
    }
}
