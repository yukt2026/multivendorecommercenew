<?php

declare(strict_types=1);

namespace App\Services\Notification\Drivers;

interface SmsGatewayInterface
{
    /**
     * Send an SMS text message.
     *
     * @return array{success: bool, message_id?: string, error?: string}
     */
    public function send(string $to, string $message, array $metadata = []): array;
}
