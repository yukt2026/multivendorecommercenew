<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case COD_PENDING = 'cod_pending';
    case AUTHORIZED = 'authorized';
    case PAID = 'paid';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::COD_PENDING => 'COD Pending',
            self::AUTHORIZED => 'Authorized',
            self::PAID => 'Paid',
            self::FAILED => 'Failed',
            self::REFUNDED => 'Refunded',
            self::PARTIALLY_REFUNDED => 'Partially Refunded',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING, self::COD_PENDING => 'bg-amber-100 text-amber-800 border-amber-300',
            self::AUTHORIZED => 'bg-cyan-100 text-cyan-800 border-cyan-300',
            self::PAID => 'bg-emerald-100 text-emerald-800 border-emerald-300',
            self::FAILED => 'bg-rose-100 text-rose-800 border-rose-300',
            self::REFUNDED, self::PARTIALLY_REFUNDED => 'bg-purple-100 text-purple-800 border-purple-300',
        };
    }
}
