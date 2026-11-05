<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly int $inventoryId,
        public readonly int $requestedQuantity,
        public readonly int $availableQuantity,
        string $message = ''
    ) {
        $msg = $message ?: "Insufficient inventory for item #{$inventoryId}. Requested: {$requestedQuantity}, Available: {$availableQuantity}.";
        parent::__construct($msg, 422);
    }
}
