<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'status',
        'email_verified_at',
        'phone_verified_at',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    public function isPhoneVerified(): bool
    {
        return $this->phone_verified_at !== null;
    }

    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Scope a query to only include active users.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UserStatus::ACTIVE);
    }

    /**
     * Customer profile relationship.
     */
    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    /**
     * Seller profile relationship.
     */
    public function seller(): HasOne
    {
        return $this->hasOne(Seller::class);
    }

    /**
     * Customer delivery addresses.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /**
     * Physical store staff assignments.
     */
    public function storeStaff(): HasMany
    {
        return $this->hasMany(StoreStaff::class);
    }

    /**
     * Stores followed by the user.
     */
    public function followedStores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_followers')->withTimestamps();
    }

    /**
     * Get collection of all stores this user can access (as owner or active staff).
     *
     * @return \Illuminate\Support\Collection<int, Store>
     */
    public function accessibleStores(): \Illuminate\Support\Collection
    {
        if ($this->seller) {
            return $this->seller->stores()->get();
        }

        return $this->storeStaff()
            ->active()
            ->with('store.location')
            ->get()
            ->pluck('store')
            ->filter();
    }

    /**
     * Check if user has permission in the context of a store (as seller owner or assigned staff).
     */
    public function hasStorePermission(string $permission, ?Store $store = null): bool
    {
        // Seller owner has all permissions across their stores
        if ($this->seller) {
            if (!$store || $store->seller_id === $this->seller->id) {
                return true;
            }
        }

        // Super admin has global permissions
        if ($this->hasRole('super_admin') || $this->can('stores.manage')) {
            return true;
        }

        // Check staff permissions
        $query = $this->storeStaff()->active();
        if ($store) {
            $query->where('store_id', $store->id);
        }

        $staffAssignments = $query->get();
        foreach ($staffAssignments as $staff) {
            if ($staff->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(UserNotificationPreference::class);
    }

    /**
     * Check if user has given stored consent for marketing and promotional broadcasts.
     */
    public function hasPromotionalConsent(): bool
    {
        $pref = $this->notificationPreferences()->firstWhere('category', 'promotions');
        return (bool) ($pref?->promotional_consent ?? false);
    }

    /**
     * Resolve effective notification channels for a given event category.
     *
     * @param string $category 'orders' | 'promotions' | 'security' | 'account'
     * @param array<string> $allowedChannels
     * @return array<string>
     */
    public function getEffectiveNotificationChannels(string $category, array $allowedChannels = ['database', 'mail', 'sms', 'whatsapp', 'push']): array
    {
        $pref = $this->notificationPreferences()->firstWhere('category', $category);

        if ($category === 'promotions' && (!$pref || !$pref->promotional_consent)) {
            // Strictly require stored opt-in consent for marketing per Blueprint Rule
            return [];
        }

        if (!$pref) {
            // Defaults: database and mail are on by default
            return array_intersect(['database', 'mail', 'sms'], $allowedChannels);
        }

        $active = [];
        if ($pref->channel_database && in_array('database', $allowedChannels, true)) $active[] = 'database';
        if ($pref->channel_mail && in_array('mail', $allowedChannels, true)) $active[] = 'mail';
        if ($pref->channel_sms && in_array('sms', $allowedChannels, true)) $active[] = 'sms';
        if ($pref->channel_whatsapp && in_array('whatsapp', $allowedChannels, true)) $active[] = 'whatsapp';
        if ($pref->channel_push && in_array('push', $allowedChannels, true)) $active[] = 'push';

        return $active;
    }
}
