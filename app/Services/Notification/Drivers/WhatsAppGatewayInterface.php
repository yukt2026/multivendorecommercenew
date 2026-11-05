<?php

declare(strict_types=1);

namespace App\Services\Notification\Drivers;

interface WhatsAppGatewayInterface
{
    /**
     * Send a WhatsApp message / template.
     *
     * @return array{success: bool, message_id?: string, error?: string}
     */
    public function send(string $to, string $message, array $metadata = []): array;
}
