<?php

declare(strict_types=1);

namespace App\Services\Notification\Drivers;

interface PushGatewayInterface
{
    /**
     * Send a mobile push / web push notification.
     *
     * @return array{success: bool, message_id?: string, error?: string}
     */
    public function send(string $deviceToken, string $title, string $body, array $data = []): array;
}
