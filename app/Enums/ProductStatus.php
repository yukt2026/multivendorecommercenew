<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductStatus: string
{
    case DRAFT = 'draft';
    case PENDING_REVIEW = 'pending_review';
    case ACTIVE = 'active';
    case REJECTED = 'rejected';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING_REVIEW => 'Pending QC Review',
            self::ACTIVE => 'Live in Catalog',
            self::REJECTED => 'Rejected by Admin',
            self::INACTIVE => 'Inactive / Archived',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::PENDING_REVIEW => 'warning',
            self::ACTIVE => 'success',
            self::REJECTED => 'danger',
            self::INACTIVE => 'secondary',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-slate-100 text-slate-800 border-slate-300 dark:bg-slate-900/60 dark:text-slate-300 dark:border-slate-800',
            self::PENDING_REVIEW => 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800',
            self::ACTIVE => 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800',
            self::REJECTED => 'bg-rose-100 text-rose-800 border-rose-300 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800',
            self::INACTIVE => 'bg-slate-200 text-slate-700 border-slate-300 dark:bg-slate-900/80 dark:text-slate-400 dark:border-slate-800',
        };
    }
}
