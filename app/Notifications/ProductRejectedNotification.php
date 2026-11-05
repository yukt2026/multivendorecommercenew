<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Product $product,
        public string $reason
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Action Required: '{$this->product->title}' Listing Changes Requested")
            ->greeting("Hello {$this->product->seller->business_name},")
            ->line("Our catalogue moderation team reviewed your product listing **'{$this->product->title}'**.")
            ->line('Unfortunately, the listing does not meet current platform standards for the following reason:')
            ->line("> **{$this->reason}**")
            ->line('You can edit your listing, fix the identified issues, and re-submit it for moderation.')
            ->action('Edit Product Listing', route('seller.dashboard'))
            ->line('For listing guidelines, please review the OneStall Merchant Catalogue Policy.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'product_rejected',
            'product_id' => $this->product->id,
            'title' => $this->product->title,
            'reason' => $this->reason,
            'message' => "Product '{$this->product->title}' rejected: {$this->reason}",
        ];
    }
}
