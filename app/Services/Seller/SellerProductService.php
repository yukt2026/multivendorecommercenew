<?php

declare(strict_types=1);

namespace App\Services\Seller;

use App\Enums\InventoryLocationType;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Jobs\OptimizeProductMediaJob;
use App\Models\Inventory;
use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductSpecification;
use App\Models\ProductVariant;
use App\Models\Seller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SellerProductService
{
    /**
     * Whitelist of safe HTML tags for product description sanitization.
     */
    protected const ALLOWED_TAGS = '<p><br><b><strong><i><em><u><ul><ol><li><h2><h3><h4><h5><h6><blockquote><table><thead><tbody><tr><th><td><a><span><div><code><pre><hr>';

    /**
     * List products strictly scoped to the seller with search, filters, and inventory aggregates.
     *
     * @param array<string, mixed> $filters
     */
    public function listProducts(Seller $seller, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Product::query()
            ->where('seller_id', $seller->id)
            ->with([
                'category:id,name,slug',
                'brand:id,name,slug,is_verified',
                'media' => fn ($q) => $q->orderBy('sort_order')->limit(1),
                'variants.inventories',
                'defaultVariant.inventories',
            ])
            ->latest('id');

        // Search by title, slug, SKU, or barcode
        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhereHas('variants', function ($vq) use ($search) {
                        $vq->where('sku', 'like', "%{$search}%")
                            ->orWhere('barcode', 'like', "%{$search}%");
                    });
            });
        }

        // Category filter (includes subcategories)
        if (!empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        // Brand filter
        if (!empty($filters['brand_id'])) {
            $query->where('brand_id', (int) $filters['brand_id']);
        }

        // Status filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        // Quick Commerce capable filter
        if (isset($filters['quick_capable']) && $filters['quick_capable'] !== '') {
            $query->where('is_quick_capable', filter_var($filters['quick_capable'], FILTER_VALIDATE_BOOLEAN));
        }

        // Stock status filter
        if (!empty($filters['stock']) && $filters['stock'] !== 'all') {
            $stock = $filters['stock'];
            if ($stock === 'out_of_stock') {
                $query->whereHas('variants', function ($vq) {
                    $vq->whereDoesntHave('inventories')
                        ->orWhereHas('inventories', function ($iq) {
                            $iq->select(DB::raw('SUM(quantity_on_hand)'))
                                ->havingRaw('SUM(quantity_on_hand) <= 0');
                        });
                });
            } elseif ($stock === 'low_stock') {
                $query->whereHas('variants.inventories', function ($iq) {
                    $iq->whereColumn('quantity_on_hand', '<=', 'low_stock_threshold')
                        ->where('quantity_on_hand', '>', 0);
                });
            } elseif ($stock === 'in_stock') {
                $query->whereHas('variants.inventories', function ($iq) {
                    $iq->whereColumn('quantity_on_hand', '>', 'low_stock_threshold');
                });
            }
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Create a new product with variants, media, specifications, and initial inventory.
     *
     * @param array<string, mixed> $data
     * @param array<int, UploadedFile> $uploadedMedia
     */
    public function createProduct(Seller $seller, array $data, array $uploadedMedia = []): Product
    {
        return DB::transaction(function () use ($seller, $data, $uploadedMedia) {
            $slug = $this->generateUniqueSlug($data['title']);
            $status = ($data['submit_action'] ?? '') === 'submit_review'
                ? ProductStatus::PENDING_REVIEW
                : ProductStatus::DRAFT;

            $product = Product::create([
                'seller_id' => $seller->id,
                'category_id' => (int) $data['category_id'],
                'brand_id' => !empty($data['brand_id']) ? (int) $data['brand_id'] : null,
                'title' => $data['title'],
                'slug' => $slug,
                'short_desc' => $data['short_desc'] ?? null,
                'description' => $this->sanitizeHtml($data['description'] ?? ''),
                'status' => $status,
                'product_type' => $data['product_type'] ?? ProductType::SIMPLE->value,
                'hsn_code' => $data['hsn_code'] ?? null,
                'tax_percent' => (float) ($data['tax_percent'] ?? 0.00),
                'return_window_days' => (int) ($data['return_window_days'] ?? 7),
                'is_returnable' => (bool) ($data['is_returnable'] ?? true),
                'is_cod_allowed' => (bool) ($data['is_cod_allowed'] ?? true),
                'is_quick_capable' => (bool) ($data['is_quick_capable'] ?? false),
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
            ]);

            // 1. Resolve or create seller inventory location
            $location = $this->getOrCreatePrimaryInventoryLocation($seller);

            // 2. Process Variants & Inventory
            $isSimple = ($product->product_type === ProductType::SIMPLE);
            if ($isSimple) {
                $sku = !empty($data['simple_sku']) ? trim((string) $data['simple_sku']) : $this->suggestSku($product->title);
                $mrp = (float) $data['simple_mrp'];
                $sellingPrice = (float) $data['simple_selling_price'];

                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $sku,
                    'barcode' => $data['simple_barcode'] ?? null,
                    'name' => $product->title,
                    'attributes' => null,
                    'mrp' => $mrp,
                    'selling_price' => $sellingPrice,
                    'cost_price' => !empty($data['simple_cost_price']) ? (float) $data['simple_cost_price'] : null,
                    'weight_grams' => (int) ($data['simple_weight_grams'] ?? 0),
                    'length' => !empty($data['simple_length']) ? (float) $data['simple_length'] : null,
                    'width' => !empty($data['simple_width']) ? (float) $data['simple_width'] : null,
                    'height' => !empty($data['simple_height']) ? (float) $data['simple_height'] : null,
                    'is_default' => true,
                    'is_active' => true,
                ]);

                $initialStock = (int) ($data['simple_stock'] ?? 0);
                Inventory::create([
                    'variant_id' => $variant->id,
                    'location_id' => $location->id,
                    'quantity_on_hand' => $initialStock,
                    'low_stock_threshold' => (int) ($data['simple_low_stock_threshold'] ?? 5),
                    'is_quick_available' => $product->is_quick_capable,
                ]);
            } else {
                $variantsData = $data['variants'] ?? [];
                $first = true;

                foreach ($variantsData as $vData) {
                    $vSku = trim((string) $vData['sku']);
                    $vMrp = (float) $vData['mrp'];
                    $vPrice = (float) $vData['selling_price'];
                    $vAttributes = isset($vData['attributes']) ? (is_array($vData['attributes']) ? $vData['attributes'] : json_decode($vData['attributes'], true)) : [];

                    $variant = ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $vSku,
                        'barcode' => $vData['barcode'] ?? null,
                        'name' => $vData['name'] ?? ($product->title . ' - ' . implode(' / ', array_values($vAttributes))),
                        'attributes' => $vAttributes,
                        'mrp' => $vMrp,
                        'selling_price' => $vPrice,
                        'cost_price' => !empty($vData['cost_price']) ? (float) $vData['cost_price'] : null,
                        'weight_grams' => (int) ($vData['weight_grams'] ?? 0),
                        'length' => !empty($vData['length']) ? (float) $vData['length'] : null,
                        'width' => !empty($vData['width']) ? (float) $vData['width'] : null,
                        'height' => !empty($vData['height']) ? (float) $vData['height'] : null,
                        'is_default' => !empty($vData['is_default']) || $first,
                        'is_active' => (bool) ($vData['is_active'] ?? true),
                    ]);

                    $first = false;

                    $initialStock = (int) ($vData['stock'] ?? 0);
                    Inventory::create([
                        'variant_id' => $variant->id,
                        'location_id' => $location->id,
                        'quantity_on_hand' => $initialStock,
                        'low_stock_threshold' => (int) ($vData['low_stock_threshold'] ?? 5),
                        'is_quick_available' => $product->is_quick_capable,
                    ]);
                }
            }

            // 3. Process Specifications
            if (!empty($data['specifications']) && is_array($data['specifications'])) {
                foreach ($data['specifications'] as $spec) {
                    if (!empty($spec['key']) && !empty($spec['value'])) {
                        ProductSpecification::create([
                            'product_id' => $product->id,
                            'group' => !empty($spec['group']) ? trim((string) $spec['group']) : 'General',
                            'key' => trim((string) $spec['key']),
                            'value' => trim((string) $spec['value']),
                        ]);
                    }
                }
            }

            // 4. Process Uploaded Media
            $this->storeProductMedia($product, $uploadedMedia);

            return $product;
        });
    }

    /**
     * Update an existing product.
     * If an already APPROVED / ACTIVE product changes sensitive fields,
     * re-trigger moderation review (status -> pending_review).
     *
     * @param array<string, mixed> $data
     * @param array<int, UploadedFile> $uploadedMedia
     * @return array{product: Product, retriggered_review: bool}
     */
    public function updateProduct(Product $product, array $data, array $uploadedMedia = []): array
    {
        return DB::transaction(function () use ($product, $data, $uploadedMedia) {
            $wasActive = ($product->status === ProductStatus::ACTIVE);
            $sensitiveChangeDetected = false;

            // Check sensitive base product fields
            if (isset($data['title']) && trim((string) $data['title']) !== trim((string) $product->title)) {
                $sensitiveChangeDetected = true;
            }
            if (isset($data['category_id']) && (int) $data['category_id'] !== (int) $product->category_id) {
                $sensitiveChangeDetected = true;
            }
            if (isset($data['brand_id']) && (int) $data['brand_id'] !== (int) $product->brand_id) {
                $sensitiveChangeDetected = true;
            }
            if (isset($data['tax_percent']) && abs((float) $data['tax_percent'] - (float) $product->tax_percent) > 0.001) {
                $sensitiveChangeDetected = true;
            }
            if (isset($data['hsn_code']) && trim((string) $data['hsn_code']) !== trim((string) $product->hsn_code)) {
                $sensitiveChangeDetected = true;
            }
            if (isset($data['return_window_days']) && (int) $data['return_window_days'] !== (int) $product->return_window_days) {
                $sensitiveChangeDetected = true;
            }
            if (isset($data['is_returnable']) && (bool) $data['is_returnable'] !== (bool) $product->is_returnable) {
                $sensitiveChangeDetected = true;
            }

            // Check if description meaningfully changed
            if (!empty($data['description']) && !empty($product->description)) {
                $sanitizedNewDesc = $this->sanitizeHtml($data['description']);
                if ($sanitizedNewDesc !== trim((string) $product->description)) {
                    $sensitiveChangeDetected = true;
                }
            }

            // Target status computation
            $newStatus = $product->status;
            $retriggeredReview = false;

            $action = $data['submit_action'] ?? 'save_changes';
            if ($action === 'submit_review') {
                $newStatus = ProductStatus::PENDING_REVIEW;
            } elseif ($action === 'save_draft' && in_array($product->status, [ProductStatus::DRAFT, ProductStatus::REJECTED], true)) {
                $newStatus = ProductStatus::DRAFT;
            } elseif ($wasActive && $sensitiveChangeDetected) {
                $newStatus = ProductStatus::PENDING_REVIEW;
                $retriggeredReview = true;
            }

            $product->update([
                'category_id' => (int) $data['category_id'],
                'brand_id' => !empty($data['brand_id']) ? (int) $data['brand_id'] : null,
                'title' => $data['title'],
                'short_desc' => $data['short_desc'] ?? null,
                'description' => $this->sanitizeHtml($data['description'] ?? ''),
                'status' => $newStatus,
                'product_type' => $data['product_type'] ?? $product->product_type->value,
                'hsn_code' => $data['hsn_code'] ?? null,
                'tax_percent' => (float) ($data['tax_percent'] ?? 0.00),
                'return_window_days' => (int) ($data['return_window_days'] ?? 7),
                'is_returnable' => (bool) ($data['is_returnable'] ?? true),
                'is_cod_allowed' => (bool) ($data['is_cod_allowed'] ?? true),
                'is_quick_capable' => (bool) ($data['is_quick_capable'] ?? false),
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
            ]);

            // Sync Inventory location
            $location = $this->getOrCreatePrimaryInventoryLocation($product->seller);

            // Sync Variants
            $isSimple = ($product->product_type === ProductType::SIMPLE);
            if ($isSimple) {
                $defaultVariant = $product->defaultVariant ?: $product->variants()->first();
                $sku = !empty($data['simple_sku']) ? trim((string) $data['simple_sku']) : ($defaultVariant?->sku ?: $this->suggestSku($product->title));
                $mrp = (float) $data['simple_mrp'];
                $sellingPrice = (float) $data['simple_selling_price'];

                if ($wasActive && $defaultVariant && ((float) $defaultVariant->selling_price !== $sellingPrice || (float) $defaultVariant->mrp !== $mrp)) {
                    $sensitiveChangeDetected = true;
                    if (!$retriggeredReview) {
                        $product->update(['status' => ProductStatus::PENDING_REVIEW]);
                        $retriggeredReview = true;
                    }
                }

                $variantData = [
                    'sku' => $sku,
                    'barcode' => $data['simple_barcode'] ?? null,
                    'name' => $product->title,
                    'mrp' => $mrp,
                    'selling_price' => $sellingPrice,
                    'cost_price' => !empty($data['simple_cost_price']) ? (float) $data['simple_cost_price'] : null,
                    'weight_grams' => (int) ($data['simple_weight_grams'] ?? 0),
                    'length' => !empty($data['simple_length']) ? (float) $data['simple_length'] : null,
                    'width' => !empty($data['simple_width']) ? (float) $data['simple_width'] : null,
                    'height' => !empty($data['simple_height']) ? (float) $data['simple_height'] : null,
                    'is_default' => true,
                    'is_active' => true,
                ];

                if ($defaultVariant) {
                    $defaultVariant->update($variantData);
                    $variant = $defaultVariant;
                } else {
                    $variant = $product->variants()->create($variantData);
                }

                if (isset($data['simple_stock'])) {
                    $stockQty = (int) $data['simple_stock'];
                    Inventory::updateOrCreate(
                        ['variant_id' => $variant->id, 'location_id' => $location->id],
                        [
                            'quantity_on_hand' => $stockQty,
                            'low_stock_threshold' => (int) ($data['simple_low_stock_threshold'] ?? 5),
                            'is_quick_available' => $product->is_quick_capable,
                        ]
                    );
                }
            } else {
                // Variable variants update
                $variantsInput = $data['variants'] ?? [];
                $keptVariantIds = [];

                foreach ($variantsInput as $vData) {
                    $vId = !empty($vData['id']) ? (int) $vData['id'] : null;
                    $vSku = trim((string) $vData['sku']);
                    $vMrp = (float) $vData['mrp'];
                    $vPrice = (float) $vData['selling_price'];
                    $vAttributes = isset($vData['attributes']) ? (is_array($vData['attributes']) ? $vData['attributes'] : json_decode($vData['attributes'], true)) : [];

                    $vAttributesPayload = [
                        'sku' => $vSku,
                        'barcode' => $vData['barcode'] ?? null,
                        'name' => $vData['name'] ?? ($product->title . ' - ' . implode(' / ', array_values($vAttributes))),
                        'attributes' => $vAttributes,
                        'mrp' => $vMrp,
                        'selling_price' => $vPrice,
                        'cost_price' => !empty($vData['cost_price']) ? (float) $vData['cost_price'] : null,
                        'weight_grams' => (int) ($vData['weight_grams'] ?? 0),
                        'length' => !empty($vData['length']) ? (float) $vData['length'] : null,
                        'width' => !empty($vData['width']) ? (float) $vData['width'] : null,
                        'height' => !empty($vData['height']) ? (float) $vData['height'] : null,
                        'is_default' => !empty($vData['is_default']),
                        'is_active' => (bool) ($vData['is_active'] ?? true),
                    ];

                    if ($vId && ($existing = $product->variants()->find($vId))) {
                        if ($wasActive && ((float) $existing->selling_price !== $vPrice || (float) $existing->mrp !== $vMrp)) {
                            $sensitiveChangeDetected = true;
                            if (!$retriggeredReview) {
                                $product->update(['status' => ProductStatus::PENDING_REVIEW]);
                                $retriggeredReview = true;
                            }
                        }
                        $existing->update($vAttributesPayload);
                        $variant = $existing;
                    } else {
                        $variant = $product->variants()->create($vAttributesPayload);
                    }

                    $keptVariantIds[] = $variant->id;

                    if (isset($vData['stock'])) {
                        $stockQty = (int) $vData['stock'];
                        Inventory::updateOrCreate(
                            ['variant_id' => $variant->id, 'location_id' => $location->id],
                            [
                                'quantity_on_hand' => $stockQty,
                                'low_stock_threshold' => (int) ($vData['low_stock_threshold'] ?? 5),
                                'is_quick_available' => $product->is_quick_capable,
                            ]
                        );
                    }
                }

                // Delete variants no longer present
                if (!empty($keptVariantIds)) {
                    $product->variants()->whereNotIn('id', $keptVariantIds)->delete();
                }
            }

            // Sync Specifications
            $product->specifications()->delete();
            if (!empty($data['specifications']) && is_array($data['specifications'])) {
                foreach ($data['specifications'] as $spec) {
                    if (!empty($spec['key']) && !empty($spec['value'])) {
                        ProductSpecification::create([
                            'product_id' => $product->id,
                            'group' => !empty($spec['group']) ? trim((string) $spec['group']) : 'General',
                            'key' => trim((string) $spec['key']),
                            'value' => trim((string) $spec['value']),
                        ]);
                    }
                }
            }

            // Process Media Deletions & Order
            if (!empty($data['deleted_media_ids']) && is_array($data['deleted_media_ids'])) {
                $product->media()->whereIn('id', $data['deleted_media_ids'])->delete();
            }

            if (!empty($data['media_sort']) && is_array($data['media_sort'])) {
                foreach ($data['media_sort'] as $sortOrder => $mediaId) {
                    $product->media()->where('id', $mediaId)->update(['sort_order' => (int) $sortOrder]);
                }
            }

            // Upload additional media
            $this->storeProductMedia($product, $uploadedMedia);

            return [
                'product' => $product->fresh(['variants.inventories', 'media', 'specifications']),
                'retriggered_review' => $retriggeredReview,
            ];
        });
    }

    /**
     * Inline price and stock quick update for catalogue list page.
     *
     * @param array<string, mixed> $data
     */
    public function quickUpdateVariant(Seller $seller, ProductVariant $variant, array $data): ProductVariant
    {
        abort_if($variant->product->seller_id !== $seller->id, 403, 'Unauthorized product access.');

        return DB::transaction(function () use ($seller, $variant, $data) {
            $mrp = isset($data['mrp']) ? (float) $data['mrp'] : (float) $variant->mrp;
            $sellingPrice = isset($data['selling_price']) ? (float) $data['selling_price'] : (float) $variant->selling_price;

            if ($sellingPrice > $mrp) {
                abort(422, 'Selling price cannot exceed Maximum Retail Price (MRP).');
            }

            $variant->update([
                'mrp' => $mrp,
                'selling_price' => $sellingPrice,
            ]);

            if (isset($data['quantity_on_hand'])) {
                $location = $this->getOrCreatePrimaryInventoryLocation($seller);
                Inventory::updateOrCreate(
                    ['variant_id' => $variant->id, 'location_id' => $location->id],
                    [
                        'quantity_on_hand' => (int) $data['quantity_on_hand'],
                        'is_quick_available' => $variant->product->is_quick_capable,
                    ]
                );
            }

            return $variant->fresh(['inventories']);
        });
    }

    /**
     * Bulk action on selected products (activate, deactivate, delete).
     *
     * @param array<int> $productIds
     */
    public function bulkAction(Seller $seller, string $action, array $productIds): int
    {
        $products = Product::where('seller_id', $seller->id)
            ->whereIn('id', $productIds)
            ->get();

        $count = 0;

        foreach ($products as $product) {
            match ($action) {
                'activate' => $product->update(['status' => ProductStatus::ACTIVE]),
                'deactivate' => $product->update(['status' => ProductStatus::INACTIVE]),
                'delete' => $product->delete(),
                default => null,
            };
            $count++;
        }

        return $count;
    }

    /**
     * Stream filtered products as CSV.
     *
     * @param array<string, mixed> $filters
     */
    public function exportCsv(Seller $seller, array $filters): StreamedResponse
    {
        $filename = 'products-export-' . date('Y-m-d-His') . '.csv';

        $query = Product::where('seller_id', $seller->id)
            ->with(['category', 'brand', 'variants.inventories'])
            ->latest('id');

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }
        if (!empty($filters['brand_id'])) {
            $query->where('brand_id', (int) $filters['brand_id']);
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Product ID',
                'Title',
                'Slug',
                'Status',
                'Type',
                'Category',
                'Brand',
                'SKU',
                'MRP (INR)',
                'Selling Price (INR)',
                'Stock Total',
                'Quick Capable',
                'Returnable',
                'Created At',
            ]);

            $query->chunk(100, function ($products) use ($handle) {
                foreach ($products as $product) {
                    $defaultVariant = $product->variants->firstWhere('is_default', true) ?: $product->variants->first();
                    $totalStock = $product->variants->sum(fn ($v) => $v->inventories->sum('quantity_on_hand'));

                    fputcsv($handle, [
                        $product->id,
                        $product->title,
                        $product->slug,
                        $product->status->label(),
                        $product->product_type->value,
                        $product->category?->name ?? 'N/A',
                        $product->brand?->name ?? 'N/A',
                        $defaultVariant?->sku ?? 'N/A',
                        $defaultVariant?->mrp ?? '0.00',
                        $defaultVariant?->selling_price ?? '0.00',
                        $totalStock,
                        $product->is_quick_capable ? 'YES' : 'NO',
                        $product->is_returnable ? 'YES' : 'NO',
                        $product->created_at?->format('Y-m-d H:i') ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Check if a proposed SKU is available and unique.
     *
     * @return array{available: bool, message: string}
     */
    public function checkSkuAvailability(string $sku, ?int $excludeVariantId = null): array
    {
        $cleanSku = strtoupper(trim($sku));

        if (empty($cleanSku)) {
            return ['available' => false, 'message' => 'SKU cannot be empty.'];
        }

        $query = ProductVariant::where('sku', $cleanSku);
        if ($excludeVariantId) {
            $query->where('id', '!=', $excludeVariantId);
        }

        $exists = $query->exists();

        return [
            'available' => !$exists,
            'sku' => $cleanSku,
            'message' => $exists ? "SKU '{$cleanSku}' is already registered in the system." : "SKU '{$cleanSku}' is unique and ready.",
        ];
    }

    /**
     * Store and queue media files.
     *
     * @param array<int, UploadedFile> $uploadedMedia
     */
    protected function storeProductMedia(Product $product, array $uploadedMedia): void
    {
        $currentMaxSort = (int) $product->media()->max('sort_order') ?: 0;

        foreach ($uploadedMedia as $index => $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $mime = $file->getMimeType() ?: '';
            $type = str_starts_with($mime, 'video/') ? 'video' : 'image';
            $ext = $file->getClientOriginalExtension() ?: ($type === 'video' ? 'mp4' : 'jpg');
            $fileName = 'prod-' . $product->id . '-' . time() . '-' . Str::random(6) . '.' . $ext;
            $path = $file->storeAs('products/' . ($type === 'video' ? 'videos' : 'images'), $fileName, 'public');

            $media = ProductMedia::create([
                'product_id' => $product->id,
                'variant_id' => null,
                'type' => $type,
                'path' => Storage::url($path),
                'sort_order' => $currentMaxSort + $index + 1,
            ]);

            // Dispatch background optimization job
            dispatch(new OptimizeProductMediaJob($media->id));
        }
    }

    /**
     * Get or create a primary inventory location for the seller.
     */
    protected function getOrCreatePrimaryInventoryLocation(Seller $seller): InventoryLocation
    {
        $location = InventoryLocation::where('seller_id', $seller->id)->first();

        if (!$location) {
            $store = $seller->stores()->first();
            $location = InventoryLocation::create([
                'seller_id' => $seller->id,
                'store_id' => $store?->id,
                'name' => ($store?->name ?: $seller->business_name) . ' Primary Hub',
                'type' => InventoryLocationType::STORE,
                'is_active' => true,
                'lat' => $store?->location?->lat ?: 12.9716,
                'lng' => $store?->location?->lng ?: 77.5946,
            ]);
        }

        return $location;
    }

    /**
     * Generate an auto-suggested unique SKU based on product title.
     */
    public function suggestSku(string $title): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $title) ?: 'PROD', 0, 4));
        $unique = strtoupper(Str::random(5));
        return "{$prefix}-{$unique}";
    }

    /**
     * Generate unique slug for product.
     */
    protected function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $count = 1;

        while (Product::where('slug', $slug)->exists()) {
            $slug = "{$base}-" . Str::random(4) . '-' . $count;
            $count++;
        }

        return $slug;
    }

    /**
     * Clean and sanitize HTML rich-text description.
     */
    public function sanitizeHtml(string $html): string
    {
        // 1. Strip all disallowed tags
        $stripped = strip_tags($html, self::ALLOWED_TAGS);

        // 2. Remove dangerous JavaScript attributes and schemas
        $cleaned = preg_replace('/(javascript:|data:|vbscript:)/i', '', $stripped);
        $cleaned = preg_replace('/on[a-z]+\s*=\s*(["\'])(.*?)\1/i', '', (string) $cleaned);
        $cleaned = preg_replace('/on[a-z]+\s*=\s*[^>\s]+/i', '', (string) $cleaned);

        return trim((string) $cleaned);
    }
}
