<?php

declare(strict_types=1);

namespace App\Services\Order\Exceptions;

use RuntimeException;

class IllegalOrderTransitionException extends RuntimeException
{
    public function __construct(
        public readonly string $entityType,
        public readonly int $entityId,
        public readonly string $fromStatus,
        public readonly string $toStatus,
        string $message = ''
    ) {
        $msg = $message ?: "Illegal order transition on {$entityType} #{$entityId}: Cannot transition from '{$fromStatus}' to '{$toStatus}'.";
        parent::__construct($msg);
    }
}
