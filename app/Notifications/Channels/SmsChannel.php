<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\NotificationLog;
use App\Services\Notification\Drivers\MockSmsDriver;
use App\Services\Notification\Drivers\SmsGatewayInterface;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class SmsChannel
{
    public function __construct(
        protected ?SmsGatewayInterface $gateway = null
    ) {
        $this->gateway = $gateway ?? app()->makeWith(SmsGatewayInterface::class, []);
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toSms')) {
            return;
        }

        $phone = $notifiable->phone ?? (method_exists($notifiable, 'routeNotificationFor') ? $notifiable->routeNotificationFor('sms') : null);
        if (empty($phone)) {
            return;
        }

        $smsContent = $notification->toSms($notifiable);
        $message = is_array($smsContent) ? ($smsContent['message'] ?? '') : (string) $smsContent;
        $eventKey = property_exists($notification, 'eventKey') ? $notification->eventKey : 'sms_alert';

        try {
            $result = $this->gateway->send((string) $phone, $message, [
                'event_key' => $eventKey,
                'notifiable_id' => $notifiable->id ?? null,
            ]);

            NotificationLog::create([
                'notifiable_type' => get_class($notifiable),
                'notifiable_id' => $notifiable->id ?? null,
                'event_key' => $eventKey,
                'channel' => 'sms',
                'recipient' => (string) $phone,
                'content' => $message,
                'status' => $result['success'] ? 'sent' : 'failed',
                'error_message' => $result['error'] ?? null,
                'sent_at' => $result['success'] ? now() : null,
            ]);
        } catch (\Throwable $e) {
            Log::error("SmsChannel failure: {$e->getMessage()}", ['phone' => $phone]);

            NotificationLog::create([
                'notifiable_type' => get_class($notifiable),
                'notifiable_id' => $notifiable->id ?? null,
                'event_key' => $eventKey,
                'channel' => 'sms',
                'recipient' => (string) $phone,
                'content' => $message,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'retry_count' => 0,
            ]);
        }
    }
}
