<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category',
        'channel_database',
        'channel_mail',
        'channel_sms',
        'channel_whatsapp',
        'channel_push',
        'promotional_consent',
        'promotional_consent_at',
    ];

    protected $casts = [
        'channel_database' => 'boolean',
        'channel_mail' => 'boolean',
        'channel_sms' => 'boolean',
        'channel_whatsapp' => 'boolean',
        'channel_push' => 'boolean',
        'promotional_consent' => 'boolean',
        'promotional_consent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
