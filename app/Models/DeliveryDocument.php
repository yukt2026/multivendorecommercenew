<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryDocument extends Model
{
    use HasFactory;

    public const TYPE_DRIVING_LICENSE = 'driving_license_front';
    public const TYPE_DRIVING_LICENSE_BACK = 'driving_license_back';
    public const TYPE_VEHICLE_RC = 'vehicle_rc';
    public const TYPE_AADHAAR = 'aadhaar_card';
    public const TYPE_INSURANCE = 'insurance';
    public const TYPE_PAN = 'pan_card';

    public const TYPES = [
        self::TYPE_DRIVING_LICENSE => 'Driving License (Front)',
        self::TYPE_DRIVING_LICENSE_BACK => 'Driving License (Back)',
        self::TYPE_VEHICLE_RC => 'Vehicle Registration (RC)',
        self::TYPE_AADHAAR => 'Aadhaar Card / National ID',
        self::TYPE_INSURANCE => 'Vehicle Insurance Policy',
        self::TYPE_PAN => 'PAN Card',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'delivery_partner_id',
        'document_type',
        'document_number',
        'file_path',
        'verification_status',
        'rejection_reason',
        'expires_at',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'expires_at' => 'date',
        'verified_at' => 'datetime',
    ];

    public function deliveryPartner(): BelongsTo
    {
        return $this->belongsTo(DeliveryPartner::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    protected function documentTypeLabel(): Attribute
    {
        return Attribute::get(fn () => self::TYPES[$this->document_type] ?? ucwords(str_replace('_', ' ', (string) $this->document_type)));
    }

    public function isPending(): bool
    {
        return $this->verification_status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->verification_status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->verification_status === self::STATUS_REJECTED;
    }
}
