<?php

declare(strict_types=1);

namespace App\Services\AI\Contracts;

use App\Models\SupportTicket;
use App\Models\TicketMessage;

interface SupportAiReplyInterface
{
    /**
     * Analyze a customer support ticket and generate an accurate, policy-aware auto reply or draft.
     *
     * @param SupportTicket $ticket
     * @param string|null $customerMessage Latest incoming message text (or ticket subject)
     * @param bool $autoSendIfConfident Whether to automatically post the message if confidence exceeds threshold
     * @return array{intent: string, confidence: float, reply_text: string, auto_posted: bool, message: ?TicketMessage}
     */
    public function generateReply(SupportTicket $ticket, ?string $customerMessage = null, bool $autoSendIfConfident = true): array;
}
