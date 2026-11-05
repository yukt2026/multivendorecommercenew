<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreFollower extends Model
{
    use HasFactory;

    protected $table = 'store_followers';

    protected $fillable = [
        'store_id',
        'user_id',
    ];

    /**
     * Store relation.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * User follower relation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
