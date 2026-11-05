<?php

declare(strict_types=1);

namespace App\Services\Fulfilment;

use App\Enums\FulfilmentType;

class CandidateMatch
{
    public function __construct(
        public readonly string $strategyIdentifier,
        public readonly string $sourceType,
        public readonly int $sellerId,
        public readonly ?int $storeId,
        public readonly ?string $storeName,
        public readonly int $inventoryLocationId,
        public readonly string $locationName,
        public readonly int $inventoryId,
        public readonly FulfilmentType $fulfilmentType,
        public readonly float $distanceKm,
        public readonly int $estimatedMinutes,
        public readonly int $allocatedQuantity,
        public readonly int $priorityScore = 100
    ) {}
}
