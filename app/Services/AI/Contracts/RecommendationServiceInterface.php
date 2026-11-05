<?php

declare(strict_types=1);

namespace App\Services\AI\Contracts;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;

interface RecommendationServiceInterface
{
    /**
     * Get similar products based on category, brand, price tier, and attributes.
     *
     * @return Collection<int, Product>
     */
    public function getSimilarProducts(Product $product, int $limit = 6): Collection;

    /**
     * Get products frequently bought together with the given product.
     *
     * @return Collection<int, Product>
     */
    public function getFrequentlyBoughtTogether(Product $product, int $limit = 4): Collection;

    /**
     * Get personalized product recommendations for a customer.
     *
     * @return Collection<int, Product>
     */
    public function getPersonalizedRecommendations(?User $user, int $limit = 8): Collection;
}
