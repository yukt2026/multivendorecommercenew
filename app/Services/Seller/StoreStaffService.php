<?php

declare(strict_types=1);

namespace App\Services\Seller;

use App\Enums\StoreStaffRole;
use App\Enums\UserStatus;
use App\Models\Seller;
use App\Models\Store;
use App\Models\StoreStaff;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StoreStaffService
{
    /**
     * Invite or assign staff member to a physical store outlet.
     */
    public function inviteStaff(Store $store, array $data, ?User $inviter = null): StoreStaff
    {
        return DB::transaction(function () use ($store, $data, $inviter) {
            $email = strtolower(trim((string) ($data['email'] ?? '')));
            $phone = !empty($data['phone']) ? preg_replace('/[^0-9]/', '', (string) $data['phone']) : null;
            $name = trim((string) ($data['name'] ?? 'Staff Member'));

            if (empty($email) && empty($phone)) {
                throw new InvalidArgumentException('Either email or phone number is required to invite staff.');
            }

            // Locate existing user or create a new user account
            $user = null;
            if (!empty($email)) {
                $user = User::where('email', $email)->first();
            }
            if (!$user && !empty($phone)) {
                $user = User::where('phone', $phone)->first();
            }

            if (!$user) {
                $tempPassword = Str::random(12);
                $fallbackEmail = $email ?: ('staff_' . $phone . '@onestall.internal');

                $user = User::create([
                    'name' => $name,
                    'email' => $fallbackEmail,
                    'phone' => $phone,
                    'password' => Hash::make($tempPassword),
                    'status' => UserStatus::ACTIVE,
                    'email_verified_at' => now(),
                ]);
            }

            // Prevent duplicate assignments
            $existing = StoreStaff::where('store_id', $store->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                throw new InvalidArgumentException("User {$user->email} is already assigned as staff for {$store->name}.");
            }

            // Parse Role and Permissions
            $roleInput = $data['role'] ?? 'manager';
            $role = is_string($roleInput) ? StoreStaffRole::tryFrom($roleInput) ?? StoreStaffRole::STORE_MANAGER : $roleInput;

            $permissions = isset($data['permissions']) && is_array($data['permissions'])
                ? array_values(array_filter($data['permissions']))
                : $role->defaultPermissions();

            return StoreStaff::create([
                'store_id' => $store->id,
                'user_id' => $user->id,
                'role' => $role,
                'status' => 'active',
                'invite_token' => Str::random(32),
                'permissions' => $permissions,
                'invited_by' => $inviter?->id,
            ]);
        });
    }

    /**
     * Update role and custom permission matrix for a store staff member.
     */
    public function updateStaff(StoreStaff $staff, array $data): StoreStaff
    {
        if (isset($data['role'])) {
            $role = is_string($data['role'])
                ? StoreStaffRole::tryFrom($data['role']) ?? $staff->role
                : $data['role'];
            $staff->role = $role;
        }

        if (isset($data['permissions']) && is_array($data['permissions'])) {
            $staff->permissions = array_values(array_filter($data['permissions']));
        } elseif (isset($data['role'])) {
            $staff->permissions = $staff->role->defaultPermissions();
        }

        if (isset($data['status'])) {
            $staff->status = (string) $data['status'];
        }

        $staff->save();

        return $staff;
    }

    /**
     * Remove / revoke store access for a staff member.
     */
    public function removeStaff(StoreStaff $staff): bool
    {
        return (bool) $staff->delete();
    }

    /**
     * Get all staff members across a seller's stores or filtered by a specific store.
     */
    public function getStaffForSeller(Seller $seller, ?int $storeId = null): Collection
    {
        $query = StoreStaff::query()
            ->with(['store', 'user', 'inviter'])
            ->whereHas('store', fn ($q) => $q->where('seller_id', $seller->id));

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->latest()->get();
    }
}
