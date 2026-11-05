<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\User;
use App\Notifications\ProductApprovedNotification;
use App\Notifications\ProductRejectedNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminCatalogueService
{
    // =========================================================================
    // CATEGORIES
    // =========================================================================

    /**
     * Get nested category tree for visual hierarchy & drag-and-drop sort.
     *
     * @return Collection<int, Category>
     */
    public function getCategoryTree(): Collection
    {
        return Category::whereNull('parent_id')
            ->with(['children.children'])
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get all categories in a flat list for select dropdowns.
     *
     * @return Collection<int, Category>
     */
    public function getAllCategoriesList(): Collection
    {
        return Category::orderBy('name')->get();
    }

    /**
     * Create a category.
     *
     * @param array<string, mixed> $data
     */
    public function createCategory(array $data): Category
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        // Unique slug fallback
        $baseSlug = $data['slug'];
        $counter = 1;
        while (Category::where('slug', $data['slug'])->exists()) {
            $data['slug'] = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $data['is_active'] = !empty($data['is_active']);
        $data['is_quick_eligible'] = !empty($data['is_quick_eligible']);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return Category::create($data);
    }

    /**
     * Update an existing category.
     *
     * @param array<string, mixed> $data
     */
    public function updateCategory(Category $category, array $data): Category
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $data['is_active'] = !empty($data['is_active']);
        $data['is_quick_eligible'] = !empty($data['is_quick_eligible']);

        $category->update($data);

        return $category;
    }

    /**
     * Delete a category.
     */
    public function deleteCategory(Category $category): void
    {
        // Re-assign direct children to root so tree doesn't break
        $category->children()->update(['parent_id' => $category->parent_id]);
        $category->delete();
    }

    /**
     * Reorder categories via drag-and-drop sortable payload.
     *
     * @param array<int, array{id: int, sort_order: int, parent_id: ?int}> $reorderList
     */
    public function reorderCategories(array $reorderList): void
    {
        DB::transaction(function () use ($reorderList) {
            foreach ($reorderList as $item) {
                Category::where('id', $item['id'])->update([
                    'sort_order' => (int) $item['sort_order'],
                    'parent_id' => !empty($item['parent_id']) ? (int) $item['parent_id'] : null,
                ]);
            }
        });
    }

    // =========================================================================
    // BRANDS
    // =========================================================================

    /**
     * Get paginated filtered brands list.
     *
     * @param array<string, mixed> $filters
     */
    public function getFilteredBrands(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Brand::query()->with('seller')->withCount('products');

        if (!empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            $query->where(function (Builder $sub) use ($term) {
                $sub->where('name', 'like', "%{$term}%")
                    ->orWhere('slug', 'like', "%{$term}%");
            });
        }

        if (isset($filters['verified']) && $filters['verified'] !== '') {
            $query->where('is_verified', (bool) $filters['verified']);
        }

        if (!empty($filters['seller_id'])) {
            $query->where('seller_id', $filters['seller_id']);
        }

        return $query->latest('id')->paginate($perPage)->withQueryString();
    }

    /**
     * Create brand.
     *
     * @param array<string, mixed> $data
     */
    public function createBrand(array $data): Brand
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $baseSlug = $data['slug'];
        $counter = 1;
        while (Brand::where('slug', $data['slug'])->exists()) {
            $data['slug'] = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $data['is_verified'] = !empty($data['is_verified']);

        return Brand::create($data);
    }

    /**
     * Update brand.
     *
     * @param array<string, mixed> $data
     */
    public function updateBrand(Brand $brand, array $data): Brand
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $data['is_verified'] = !empty($data['is_verified']);
        $brand->update($data);

        return $brand;
    }

    /**
     * Delete brand.
     */
    public function deleteBrand(Brand $brand): void
    {
        $brand->delete();
    }

    /**
     * Toggle brand verification badge.
     */
    public function toggleBrandVerification(Brand $brand): bool
    {
        $brand->update(['is_verified' => !$brand->is_verified]);

        return $brand->is_verified;
    }

    // =========================================================================
    // ATTRIBUTES & VALUES
    // =========================================================================

    /**
     * Get all attributes with their values.
     *
     * @return Collection<int, ProductAttribute>
     */
    public function getAttributesWithValues(): Collection
    {
        return ProductAttribute::with('values')->withCount('values')->latest('id')->get();
    }

    /**
     * Create attribute.
     *
     * @param array<string, mixed> $data
     */
    public function createAttribute(array $data): ProductAttribute
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return ProductAttribute::create($data);
    }

    /**
     * Update attribute.
     *
     * @param array<string, mixed> $data
     */
    public function updateAttribute(ProductAttribute $attribute, array $data): ProductAttribute
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $attribute->update($data);

        return $attribute;
    }

    /**
     * Delete attribute.
     */
    public function deleteAttribute(ProductAttribute $attribute): void
    {
        $attribute->delete();
    }

    /**
     * Add an attribute value (e.g. "XL", "500g", "Crimson Red" with code "#DC2626").
     *
     * @param array<string, mixed> $data
     */
    public function addAttributeValue(ProductAttribute $attribute, array $data): ProductAttributeValue
    {
        return $attribute->values()->create([
            'value' => trim((string) $data['value']),
            'code' => !empty($data['code']) ? trim((string) $data['code']) : null,
        ]);
    }

    /**
     * Delete attribute value.
     */
    public function deleteAttributeValue(ProductAttributeValue $value): void
    {
        $value->delete();
    }

    // =========================================================================
    // PRODUCT MODERATION QUEUE
    // =========================================================================

    /**
     * Get paginated moderation queue products.
     *
     * @param array<string, mixed> $filters
     */
    public function getModerationQueue(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Product::query()
            ->with(['seller.user', 'category', 'brand', 'variants', 'media']);

        // Default status is pending_review if not specified
        $status = $filters['status'] ?? 'pending_review';
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if (!empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            $query->where(function (Builder $sub) use ($term) {
                $sub->where('title', 'like', "%{$term}%")
                    ->orWhere('slug', 'like', "%{$term}%")
                    ->orWhere('hsn_code', 'like', "%{$term}%")
                    ->orWhereHas('seller', function (Builder $sSub) use ($term) {
                        $sSub->where('business_name', 'like', "%{$term}%");
                    })
                    ->orWhereHas('variants', function (Builder $vSub) use ($term) {
                        $vSub->where('sku', 'like', "%{$term}%")
                             ->orWhere('barcode', 'like', "%{$term}%");
                    });
            });
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['seller_id'])) {
            $query->where('seller_id', $filters['seller_id']);
        }

        return $query->latest('id')->paginate($perPage)->withQueryString();
    }

    /**
     * Get moderation queue counters.
     *
     * @return array<string, int>
     */
    public function getModerationStats(): array
    {
        return [
            'pending_count' => Product::where('status', ProductStatus::PENDING_REVIEW)->count(),
            'approved_today' => Product::where('status', ProductStatus::ACTIVE)
                ->whereDate('updated_at', today())
                ->count(),
            'rejected_count' => Product::where('status', ProductStatus::REJECTED)->count(),
            'total_active' => Product::where('status', ProductStatus::ACTIVE)->count(),
        ];
    }

    /**
     * Approve product in moderation queue.
     */
    public function approveProduct(Product $product, User $admin): void
    {
        $product->loadMissing('seller.user');
        DB::transaction(function () use ($product, $admin) {
            $oldStatus = $product->status->value;

            $product->update([
                'status' => ProductStatus::ACTIVE,
                'rejection_reason' => null,
            ]);

            activity()
                ->performedOn($product)
                ->causedBy($admin)
                ->withProperties([
                    'action' => 'approve_product',
                    'old' => ['status' => $oldStatus],
                    'new' => ['status' => ProductStatus::ACTIVE->value],
                ])
                ->log("Approved product #{$product->id} ('{$product->title}') into live catalogue.");

            // Notify seller
            if ($product->seller?->user) {
                $product->seller->user->notify(new ProductApprovedNotification($product));
            }
        });
    }

    /**
     * Reject product with mandatory reason.
     */
    public function rejectProduct(Product $product, string $reason, User $admin): void
    {
        $product->loadMissing('seller.user');
        DB::transaction(function () use ($product, $reason, $admin) {
            $oldStatus = $product->status->value;

            $product->update([
                'status' => ProductStatus::REJECTED,
                'rejection_reason' => $reason,
            ]);

            activity()
                ->performedOn($product)
                ->causedBy($admin)
                ->withProperties([
                    'action' => 'reject_product',
                    'reason' => $reason,
                    'old' => ['status' => $oldStatus],
                    'new' => ['status' => ProductStatus::REJECTED->value, 'rejection_reason' => $reason],
                ])
                ->log("Rejected product #{$product->id} ('{$product->title}'). Reason: {$reason}");

            // Notify seller
            if ($product->seller?->user) {
                $product->seller->user->notify(new ProductRejectedNotification($product, $reason));
            }
        });
    }

    /**
     * Bulk approve multiple products.
     *
     * @param array<int> $productIds
     */
    public function bulkApproveProducts(array $productIds, User $admin): int
    {
        $products = Product::whereIn('id', $productIds)
            ->where('status', '!=', ProductStatus::ACTIVE)
            ->with('seller.user')
            ->get();

        $count = 0;
        foreach ($products as $product) {
            $this->approveProduct($product, $admin);
            $count++;
        }

        return $count;
    }

    /**
     * Edit product details on behalf of the merchant.
     *
     * @param array<string, mixed> $data
     */
    public function updateProductOnBehalf(Product $product, array $data, User $admin): Product
    {
        return DB::transaction(function () use ($product, $data, $admin) {
            $oldAttributes = $product->only(['title', 'category_id', 'brand_id', 'hsn_code', 'tax_percent']);

            $product->update([
                'title' => $data['title'],
                'category_id' => $data['category_id'],
                'brand_id' => $data['brand_id'] ?? null,
                'short_desc' => $data['short_desc'] ?? null,
                'description' => $data['description'] ?? null,
                'hsn_code' => $data['hsn_code'] ?? null,
                'tax_percent' => (float) ($data['tax_percent'] ?? 0.00),
                'is_quick_capable' => !empty($data['is_quick_capable']),
                'is_cod_allowed' => !empty($data['is_cod_allowed']),
                'is_returnable' => !empty($data['is_returnable']),
                'return_window_days' => (int) ($data['return_window_days'] ?? 7),
            ]);

            // Update default variant pricing if provided
            if (isset($data['mrp']) || isset($data['selling_price'])) {
                $defaultVariant = $product->defaultVariant ?: $product->variants()->first();
                if ($defaultVariant) {
                    $defaultVariant->update([
                        'mrp' => isset($data['mrp']) ? (float) $data['mrp'] : $defaultVariant->mrp,
                        'selling_price' => isset($data['selling_price']) ? (float) $data['selling_price'] : $defaultVariant->selling_price,
                    ]);
                }
            }

            activity()
                ->performedOn($product)
                ->causedBy($admin)
                ->withProperties([
                    'action' => 'edit_on_behalf',
                    'old' => $oldAttributes,
                    'new' => $product->only(['title', 'category_id', 'brand_id', 'hsn_code', 'tax_percent']),
                ])
                ->log("Admin edited product #{$product->id} on behalf of seller #{$product->seller_id}.");

            return $product;
        });
    }
}
