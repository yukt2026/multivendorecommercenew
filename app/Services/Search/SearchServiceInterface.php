<?php

declare(strict_types=1);

namespace App\Services\Search;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SearchServiceInterface
{
    /**
     * Search products with filters, sorting, location-aware ranking, and pagination.
     *
     * @param string $query
     * @param array $filters
     * @param string $sort
     * @param array $location
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchProducts(
        string $query = '',
        array $filters = [],
        string $sort = 'relevance',
        array $location = [],
        int $perPage = 16
    ): LengthAwarePaginator;

    /**
     * Search stores with location and filters.
     *
     * @param string $query
     * @param array $filters
     * @param array $location
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchStores(
        string $query = '',
        array $filters = [],
        array $location = [],
        int $perPage = 12
    ): LengthAwarePaginator;

    /**
     * Search brands with query.
     *
     * @param string $query
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchBrands(
        string $query = '',
        int $perPage = 18
    ): LengthAwarePaginator;

    /**
     * Suggest typo corrections / "Did you mean?".
     *
     * @param string $query
     * @return array{suggested_query: ?string, suggestions: array<int, string>}
     */
    public function suggestCorrections(string $query): array;

    /**
     * Get facet counts / aggregations for sidebar filters.
     *
     * @param string $query
     * @param array $currentFilters
     * @return array
     */
    public function getFilterFacets(string $query = '', array $currentFilters = []): array;
}
