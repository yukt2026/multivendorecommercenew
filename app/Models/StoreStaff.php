<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StoreStaffRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreStaff extends Model
{
    use HasFactory;

    protected $table = 'store_staff';

    protected $fillable = [
        'store_id',
        'user_id',
        'role',
        'status',
        'invite_token',
        'permissions',
        'invited_by',
    ];

    protected function casts(): array
    {
        return [
            'role' => StoreStaffRole::class,
            'permissions' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if this staff assignment grants a specific granular permission.
     */
    public function hasPermission(string $permission): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        if (in_array($this->role, [StoreStaffRole::OWNER, StoreStaffRole::STORE_MANAGER])) {
            return true;
        }

        $granted = $this->permissions ?? $this->role->defaultPermissions();
        return in_array($permission, $granted, true);
    }
}
