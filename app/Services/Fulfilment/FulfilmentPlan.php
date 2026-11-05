<?php

declare(strict_types=1);

namespace App\Services\Fulfilment;

class FulfilmentPlan
{
    /**
     * @param array<int, FulfilmentPlanGroup> $groups
     * @param array<int, array<string, mixed>> $unfulfillableItems
     */
    public function __construct(
        public array $groups = [],
        public array $unfulfillableItems = [],
        public float $totalDeliveryFee = 0.0,
        public bool $allQuick = false
    ) {}

    public function hasUnfulfillable(): bool
    {
        return !empty($this->unfulfillableItems);
    }

    public function toArray(): array
    {
        return [
            'groups' => array_map(fn (FulfilmentPlanGroup $g) => $g->toArray(), $this->groups),
            'unfulfillable_items' => $this->unfulfillableItems,
            'has_unfulfillable' => $this->hasUnfulfillable(),
            'total_delivery_fee' => $this->totalDeliveryFee,
            'formatted_total_delivery_fee' => $this->totalDeliveryFee == 0 ? 'FREE' : format_currency($this->totalDeliveryFee),
            'all_quick' => $this->allQuick,
            'package_count' => count($this->groups),
        ];
    }
}
