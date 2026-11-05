<?php

declare(strict_types=1);

namespace App\Services\AI\Contracts;

use App\Services\AI\SemanticSearch\ParsedSearchQuery;

interface SemanticSearchParserInterface
{
    /**
     * Parse natural language user search queries into structured attributes, price bounds, and intent.
     *
     * @param string $query Natural language text, e.g. "black shirt under 1000 near me"
     * @param array<string, mixed> $userLocation Optional current customer coordinates
     * @return ParsedSearchQuery
     */
    public function parse(string $query, array $userLocation = []): ParsedSearchQuery;
}
