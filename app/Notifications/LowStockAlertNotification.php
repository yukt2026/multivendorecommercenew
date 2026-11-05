<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Inventory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Inventory $inventory
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $product = $this->inventory->variant->product;
        $variant = $this->inventory->variant;
        $location = $this->inventory->location;
        $available = $this->inventory->available_quantity;
        $threshold = $this->inventory->low_stock_threshold;

        return (new MailMessage)
            ->subject("⚠️ Low Stock Alert: '{$product->title}' ({$variant->sku})")
            ->greeting("Attention {$product->seller->business_name},")
            ->line("Inventory for **{$product->title}** (SKU: `{$variant->sku}`) at location **{$location->name}** has reached critical threshold.")
            ->line("Current Available Units: **{$available}** (Alert Threshold: **{$threshold}**)")
            ->action('Restock Inventory in Portal', route('seller.inventory.index'))
            ->line('Please replenish inventory immediately to avoid order cancellations and stock-outs.');
    }

    public function toArray(object $notifiable): array
    {
        $product = $this->inventory->variant->product;
        $variant = $this->inventory->variant;

        return [
            'type' => 'low_stock_alert',
            'inventory_id' => $this->inventory->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'sku' => $variant->sku,
            'product_title' => $product->title,
            'location_name' => $this->inventory->location->name,
            'available_quantity' => $this->inventory->available_quantity,
            'threshold' => $this->inventory->low_stock_threshold,
            'message' => "SKU {$variant->sku} ({$product->title}) is low on stock ({$this->inventory->available_quantity} remaining at {$this->inventory->location->name}).",
        ];
    }
}
