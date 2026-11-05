<?php

declare(strict_types=1);

namespace App\Services\AI\SemanticSearch;

class ParsedSearchQuery
{
    /**
     * @param array<string, string> $attributes
     */
    public function __construct(
        public readonly string $rawQuery,
        public readonly string $cleanedKeyword,
        public readonly ?string $categoryHint = null,
        public readonly ?string $colour = null,
        public readonly ?string $size = null,
        public readonly ?float $minPrice = null,
        public readonly ?float $maxPrice = null,
        public readonly bool $isNearMe = false,
        public readonly bool $isQuickCommerce = false,
        public readonly ?string $brandHint = null,
        public readonly array $attributes = []
    ) {}

    /**
     * Transform extracted semantic parameters into standard search filters.
     *
     * @return array<string, mixed>
     */
    public function toFilterArray(): array
    {
        $filters = [];

        if ($this->minPrice !== null) {
            $filters['min_price'] = $this->minPrice;
        }

        if ($this->maxPrice !== null) {
            $filters['max_price'] = $this->maxPrice;
        }

        if ($this->isQuickCommerce || $this->isNearMe) {
            $filters['quick'] = true;
        }

        if ($this->brandHint !== null) {
            $filters['brand'] = $this->brandHint;
        }

        if ($this->categoryHint !== null) {
            $filters['category'] = $this->categoryHint;
        }

        if (!empty($this->attributes)) {
            $filters['attributes'] = $this->attributes;
        }

        return $filters;
    }

    /**
     * Get array representation of the parsed DTO.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'raw_query' => $this->rawQuery,
            'cleaned_keyword' => $this->cleanedKeyword,
            'category_hint' => $this->categoryHint,
            'colour' => $this->colour,
            'size' => $this->size,
            'min_price' => $this->minPrice,
            'max_price' => $this->maxPrice,
            'is_near_me' => $this->isNearMe,
            'is_quick_commerce' => $this->isQuickCommerce,
            'brand_hint' => $this->brandHint,
            'attributes' => $this->attributes,
            'resolved_filters' => $this->toFilterArray(),
        ];
    }
}
