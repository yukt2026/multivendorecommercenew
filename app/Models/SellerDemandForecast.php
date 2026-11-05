<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerDemandForecast extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'variant_id',
        'daily_sales_velocity',
        'days_to_stockout',
        'predicted_demand_30d',
        'recommended_reorder_qty',
        'stock_status',
        'forecast_metadata',
    ];

    protected $casts = [
        'daily_sales_velocity' => 'decimal:2',
        'days_to_stockout' => 'decimal:1',
        'predicted_demand_30d' => 'decimal:2',
        'recommended_reorder_qty' => 'integer',
        'forecast_metadata' => 'array',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
