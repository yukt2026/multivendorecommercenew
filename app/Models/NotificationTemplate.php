<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_key',
        'channel',
        'subject',
        'body',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * The accessors to append to the model's array and JSON form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'supported_variables',
    ];

    /**
     * Standard dictionary of dynamic placeholder variables by event key.
     *
     * @return array<string>
     */
    public function getSupportedVariablesAttribute(): array
    {
        $known = [
            'otp' => ['otp', 'name', 'expires_minutes'],
            'order_confirmed' => ['order_number', 'amount', 'store_name', 'action_url', 'name'],
            'payment_confirmed' => ['order_number', 'amount', 'payment_method', 'transaction_id', 'name'],
            'order_packing' => ['order_number', 'store_name', 'item_count', 'name'],
            'shipment_update' => ['order_number', 'tracking_number', 'courier_name', 'action_url', 'name'],
            'out_for_delivery' => ['order_number', 'rider_name', 'eta', 'action_url', 'name'],
            'order_delivered' => ['order_number', 'store_name', 'action_url', 'rating_url', 'name'],
            'delivered' => ['order_number', 'store_name', 'action_url', 'name'],
            'return_refund' => ['order_number', 'return_id', 'refund_amount', 'status', 'name'],
            'seller_new_order' => ['order_number', 'store_name', 'item_count', 'amount', 'action_url', 'name'],
            'low_stock_alert' => ['product_name', 'sku', 'remaining_quantity', 'store_name'],
            'settlement_paid' => ['settlement_id', 'amount', 'utr', 'period', 'name'],
            'promotional' => ['coupon', 'discount', 'action_url', 'name'],
        ];

        $vars = $known[$this->event_key] ?? ['name', 'action_url'];

        // Also extract any explicitly defined {placeholders} present in subject or body
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', ($this->subject ?? '') . ' ' . ($this->body ?? ''), $matches);
        if (!empty($matches[1])) {
            $vars = array_values(array_unique(array_merge($vars, $matches[1])));
        }

        return $vars;
    }

    /**
     * Provide realistic sample data dictionary for live preview rendering.
     *
     * @return array<string, string>
     */
    public static function sampleVariables(): array
    {
        return [
            'name' => 'Rahul Sharma',
            'order_number' => 'ORD-89421',
            'amount' => '1,499.00',
            'store_name' => 'OneStall SuperMart',
            'action_url' => 'http://127.0.0.1:8000/orders/ORD-89421',
            'rating_url' => 'http://127.0.0.1:8000/orders/ORD-89421/rate',
            'otp' => '592810',
            'expires_minutes' => '10',
            'rider_name' => 'Vikram Singh',
            'eta' => '14 mins',
            'tracking_number' => 'TRK-IN-99482',
            'courier_name' => 'OneStall Express Logistics',
            'payment_method' => 'Razorpay UPI',
            'transaction_id' => 'pay_PtN82910482',
            'coupon' => 'MEGA30',
            'discount' => '30%',
            'item_count' => '4',
            'product_name' => 'Premium Royal Basmati Rice (5kg)',
            'sku' => 'RICE-BASM-5KG',
            'remaining_quantity' => '3',
            'settlement_id' => 'SETTLE-2026-11-12',
            'utr' => 'HDFC00294819284',
            'period' => 'Nov 1 - Nov 7, 2026',
            'return_id' => 'RET-1092',
            'refund_amount' => '499.00',
            'status' => 'approved',
        ];
    }

    /**
     * Replace template placeholders with dynamic values.
     * e.g. "Hello {name}, your order #{order_number} is placed."
     *
     * @param array<string, mixed> $variables
     * @return array{subject: ?string, body: string}
     */
    public function render(array $variables = []): array
    {
        $subject = $this->subject;
        $body = $this->body;

        foreach ($variables as $key => $val) {
            $placeholder = '{' . trim((string) $key) . '}';
            $replacement = (string) (is_array($val) ? json_encode($val) : $val);

            if ($subject !== null) {
                $subject = str_replace($placeholder, $replacement, $subject);
            }
            $body = str_replace($placeholder, $replacement, $body);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    /**
     * Render the template populated with sample test variables.
     *
     * @return array{subject: ?string, body: string}
     */
    public function renderSample(): array
    {
        return $this->render(static::sampleVariables());
    }
}
