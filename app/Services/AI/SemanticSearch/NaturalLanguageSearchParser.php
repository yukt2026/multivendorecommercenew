<?php

declare(strict_types=1);

namespace App\Services\AI\SemanticSearch;

use App\Services\AI\AiManager;
use App\Services\AI\Contracts\SemanticSearchParserInterface;

class NaturalLanguageSearchParser implements SemanticSearchParserInterface
{
    protected const COLOURS = [
        'black', 'white', 'blue', 'navy', 'red', 'green', 'yellow', 'pink',
        'grey', 'gray', 'gold', 'silver', 'brown', 'purple', 'orange', 'beige',
        'maroon', 'teal', 'olive', 'coral'
    ];

    protected const SIZES = [
        'extra small' => 'XS',
        'small' => 'S',
        'medium' => 'M',
        'large' => 'L',
        'extra large' => 'XL',
        'xxl' => 'XXL',
        'xxxl' => 'XXXL',
        'xs' => 'XS',
        'xl' => 'XL',
        '2xl' => 'XXL',
    ];

    protected const LOCATION_TERMS = [
        'near me', 'nearby', 'around me', 'close to me', 'close by', 'in my area', 'hyperlocal'
    ];

    protected const SPEED_TERMS = [
        'quick delivery', 'quick', 'fast delivery', 'fast', 'express', 'instant',
        'in 30 mins', 'in 15 mins', 'today delivery', 'today'
    ];

    public function __construct(
        protected AiManager $aiManager
    ) {}

    public function parse(string $query, array $userLocation = []): ParsedSearchQuery
    {
        $start = microtime(true);
        $trimmed = trim($query);

        if (empty($trimmed)) {
            return new ParsedSearchQuery(
                rawQuery: '',
                cleanedKeyword: ''
            );
        }

        // If feature flag is disabled, return untouched
        if (!$this->aiManager->isFeatureEnabled('semantic_search')) {
            $parsed = new ParsedSearchQuery(
                rawQuery: $trimmed,
                cleanedKeyword: $trimmed
            );

            $this->aiManager->log(
                feature: 'semantic_search',
                action: 'parse_query',
                promptSummary: "Parsed query (flag disabled): {$trimmed}",
                input: ['query' => $trimmed],
                output: $parsed->toArray(),
                latencyMs: (microtime(true) - $start) * 1000,
                status: 'flag_disabled'
            );

            return $parsed;
        }

        $workingText = ' ' . strtolower($trimmed) . ' ';
        $minPrice = null;
        $maxPrice = null;
        $isNearMe = false;
        $isQuick = false;
        $colour = null;
        $size = null;
        $attributes = [];

        // 1. Detect Location Intent ("near me")
        foreach (self::LOCATION_TERMS as $locTerm) {
            if (str_contains($workingText, " {$locTerm} ")) {
                $isNearMe = true;
                $workingText = str_replace(" {$locTerm} ", ' ', $workingText);
            }
        }

        // 2. Detect Speed Intent ("quick", "fast")
        foreach (self::SPEED_TERMS as $speedTerm) {
            if (str_contains($workingText, " {$speedTerm} ")) {
                $isQuick = true;
                $workingText = str_replace(" {$speedTerm} ", ' ', $workingText);
            }
        }

        // 3. Detect Price Ranges ("under 1000", "below 500", "between 500 and 1500", "above 2000")
        // "between X and Y"
        if (preg_match('/\bbetween\s+(\d+(?:\.\d+)?)\s+(?:and|to)\s+(\d+(?:\.\d+)?)\b/i', $workingText, $matches)) {
            $minPrice = (float) min($matches[1], $matches[2]);
            $maxPrice = (float) max($matches[1], $matches[2]);
            $workingText = str_replace($matches[0], ' ', $workingText);
        }
        // "under X", "below X", "less than X", "upto X"
        elseif (preg_match('/\b(?:under|below|less than|upto|up to|within)\s+(?:rs\.?|inr|₹)?\s*(\d+(?:\.\d+)?)\b/i', $workingText, $matches)) {
            $maxPrice = (float) $matches[1];
            $workingText = str_replace($matches[0], ' ', $workingText);
        }
        // "above X", "more than X", "over X"
        elseif (preg_match('/\b(?:above|over|more than|exceeding)\s+(?:rs\.?|inr|₹)?\s*(\d+(?:\.\d+)?)\b/i', $workingText, $matches)) {
            $minPrice = (float) $matches[1];
            $workingText = str_replace($matches[0], ' ', $workingText);
        }

        // 4. Detect Colour
        foreach (self::COLOURS as $col) {
            if (preg_match("/\b{$col}\b/i", $workingText)) {
                $colour = ucfirst($col);
                $attributes['colour'] = $colour;
                $workingText = preg_replace("/\b{$col}\b/i", ' ', $workingText);
                break;
            }
        }

        // 5. Detect Size
        foreach (self::SIZES as $rawSize => $stdSize) {
            if (preg_match("/\b(?:size\s+)?{$rawSize}\b/i", $workingText)) {
                $size = $stdSize;
                $attributes['size'] = $size;
                $workingText = preg_replace("/\b(?:size\s+)?{$rawSize}\b/i", ' ', $workingText);
                break;
            }
        }

        // 6. Clean remaining keywords
        $cleanedKeyword = trim(preg_replace('/\s+/', ' ', $workingText));

        // Fallback: If stripping parsed everything away, keep original keyword
        if (empty($cleanedKeyword) && $colour !== null) {
            $cleanedKeyword = $colour;
        }

        $parsed = new ParsedSearchQuery(
            rawQuery: $trimmed,
            cleanedKeyword: $cleanedKeyword,
            colour: $colour,
            size: $size,
            minPrice: $minPrice,
            maxPrice: $maxPrice,
            isNearMe: $isNearMe,
            isQuickCommerce: $isQuick,
            attributes: $attributes
        );

        $duration = (microtime(true) - $start) * 1000;

        $this->aiManager->log(
            feature: 'semantic_search',
            action: 'parse_query',
            promptSummary: "Parsed '{$trimmed}' => keyword: '{$cleanedKeyword}', max_price: {$maxPrice}, near_me: " . ($isNearMe ? 'yes' : 'no'),
            input: ['query' => $trimmed, 'location' => $userLocation],
            output: $parsed->toArray(),
            latencyMs: $duration,
            status: 'success'
        );

        return $parsed;
    }
}
