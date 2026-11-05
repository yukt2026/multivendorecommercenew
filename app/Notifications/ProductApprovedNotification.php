<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Product $product
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Product Approved: '{$this->product->title}' is Now Live")
            ->greeting("Hello {$this->product->seller->business_name},")
            ->line("Great news! Your product listing **'{$this->product->title}'** has been reviewed and approved by our catalogue moderation team.")
            ->line('It is now active and discoverable by consumers across the OneStall marketplace.')
            ->action('View Product in Catalogue', route('seller.dashboard'))
            ->line('Thank you for maintaining high listing standards on OneStall.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'product_approved',
            'product_id' => $this->product->id,
            'title' => $this->product->title,
            'slug' => $this->product->slug,
            'message' => "Product '{$this->product->title}' has been approved and is now live.",
        ];
    }
}
