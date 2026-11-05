<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Seller;
use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Seller $seller,
        public ?Store $store = null
    ) {}

    /**
     * Get the notification delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $loginUrl = route('seller.login');
        $storeName = $this->store ? $this->store->name : $this->seller->business_name;

        return (new MailMessage)
            ->subject('Congratulations! Your OneStall Merchant Account is Approved')
            ->greeting("Hello {$this->seller->business_name},")
            ->line('Your merchant account application and KYC verification have been reviewed and approved by the OneStall onboarding team.')
            ->line("We have initialized your default digital storefront: **{$storeName}**.")
            ->line('You can now log in to the OneStall Seller Central Portal to configure your catalogue, set up delivery radius, and start selling.')
            ->action('Launch Seller Central', $loginUrl)
            ->line('Thank you for partnering with OneStall Smart Commerce Platform!');
    }

    /**
     * Get the array representation of the notification for database persistence.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'seller_approved',
            'seller_id' => $this->seller->id,
            'business_name' => $this->seller->business_name,
            'store_id' => $this->store?->id,
            'message' => 'Your merchant account has been approved and your storefront is live.',
        ];
    }
}
