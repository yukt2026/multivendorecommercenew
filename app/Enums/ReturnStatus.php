<?php

declare(strict_types=1);

namespace App\Enums;

enum ReturnStatus: string
{
    case REQUESTED = 'requested';
    case APPROVED = 'approved';
    case REVERSE_PICKUP_SCHEDULED = 'reverse_pickup_scheduled';
    case PICKUP_SCHEDULED = 'pickup_scheduled'; // backward compatibility
    case PICKED_UP = 'picked_up';
    case RECEIVED = 'received';
    case QC_PASSED = 'qc_passed';
    case QC_FAILED = 'qc_failed';
    case REFUNDED = 'refunded';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::REQUESTED => 'Return Requested',
            self::APPROVED => 'Return Approved',
            self::REVERSE_PICKUP_SCHEDULED, self::PICKUP_SCHEDULED => 'Reverse Pickup Scheduled',
            self::PICKED_UP => 'Item Picked Up',
            self::RECEIVED => 'Item Received at Facility',
            self::QC_PASSED => 'QC Passed',
            self::QC_FAILED => 'QC Failed',
            self::REFUNDED => 'Refund Issued',
            self::REJECTED => 'Return Rejected',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::REQUESTED => 'warning',
            self::APPROVED => 'info',
            self::REVERSE_PICKUP_SCHEDULED, self::PICKUP_SCHEDULED => 'indigo',
            self::PICKED_UP => 'purple',
            self::RECEIVED => 'sky',
            self::QC_PASSED => 'emerald',
            self::QC_FAILED => 'rose',
            self::REFUNDED => 'success',
            self::REJECTED => 'danger',
            self::CANCELLED => 'slate',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::REQUESTED => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-400 dark:border-amber-800',
            self::APPROVED => 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/60 dark:text-sky-400 dark:border-sky-800',
            self::REVERSE_PICKUP_SCHEDULED, self::PICKUP_SCHEDULED => 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/60 dark:text-indigo-400 dark:border-indigo-800',
            self::PICKED_UP => 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/60 dark:text-purple-400 dark:border-purple-800',
            self::RECEIVED => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/60 dark:text-blue-400 dark:border-blue-800',
            self::QC_PASSED => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-400 dark:border-emerald-800',
            self::QC_FAILED => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/60 dark:text-rose-400 dark:border-rose-800',
            self::REFUNDED => 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-900/60 dark:text-emerald-300 dark:border-emerald-700',
            self::REJECTED => 'bg-red-50 text-red-700 border-red-200 dark:bg-red-950/60 dark:text-red-400 dark:border-red-800',
            self::CANCELLED => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::REFUNDED, self::REJECTED, self::CANCELLED], true);
    }
}
