<?php

declare(strict_types=1);

namespace App\Services\Integration;

use App\Enums\InventoryLocationType;
use App\Enums\ProductStatus;
use App\Models\CatalogImport;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CatalogImportService
{
    /**
     * Generate standard CSV template string.
     */
    public function generateTemplateCsv(): string
    {
        $headers = ['sku', 'title', 'category', 'selling_price', 'mrp', 'quantity', 'weight_grams', 'barcode', 'description'];
        $sampleRows = [
            ['SKU-SHIRT-01', 'Classic Linen Casual Shirt', 'Apparel', '1299.00', '1999.00', '25', '450', '8901234567890', 'Premium breathable linen fabric'],
            ['SKU-TEA-02', 'Organic Darjeeling Green Tea 250g', 'Groceries', '349.00', '450.00', '50', '250', '8901234567891', 'Fresh first-flush Darjeeling green tea'],
        ];

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);
        foreach ($sampleRows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return (string) $csv;
    }

    /**
     * Handle uploaded CSV file, extract headers, and generate dry-run preview.
     *
     * @return array{import: CatalogImport, headers: array<string>, preview_rows: array<array>, auto_mapping: array<string, string>, total_rows: int}
     */
    public function uploadAndPreview(Store $store, UploadedFile $file): array
    {
        $path = $file->store('catalog_imports');

        $headers = [];
        $rows = [];
        $totalRows = 0;

        $handle = $this->openStream($path);
        if ($handle !== false) {
            $headers = fgetcsv($handle) ?: [];
            // Clean BOM from first header if present
            if (!empty($headers[0])) {
                $headers[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $headers[0]);
            }

            while (($data = fgetcsv($handle)) !== false) {
                if (count(array_filter($data)) > 0) {
                    $totalRows++;
                    if (count($rows) < 5) {
                        $rows[] = array_combine(
                            array_slice($headers, 0, count($data)),
                            array_slice($data, 0, count($headers))
                        );
                    }
                }
            }
            fclose($handle);
        }

        // Auto column mapping detection
        $autoMapping = $this->detectColumnMapping($headers);

        $catalogImport = CatalogImport::create([
            'seller_id' => $store->seller_id,
            'store_id' => $store->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'type' => 'products',
            'status' => 'pending',
            'total_rows' => $totalRows,
            'imported_rows' => 0,
            'failed_rows' => 0,
            'column_mapping' => $autoMapping,
            'errors' => [],
        ]);

        return [
            'import' => $catalogImport,
            'headers' => $headers,
            'preview_rows' => $rows,
            'auto_mapping' => $autoMapping,
            'total_rows' => $totalRows,
        ];
    }

    /**
     * Process catalog import from file.
     *
     * @param array<string, string>|null $customMapping
     * @return array{success: bool, imported: int, failed: int, errors: array}
     */
    public function processImport(CatalogImport $import, ?array $customMapping = null, bool $isDryRun = false): array
    {
        $handle = $this->openStream($import->file_path);
        if (!$handle) {
            $import->update(['status' => 'failed', 'errors' => ['File not found on storage.']]);
            return ['success' => false, 'imported' => 0, 'failed' => 0, 'errors' => ['File not found.']];
        }

        $mapping = $customMapping ?? $import->column_mapping ?? [];
        $store = $import->store;
        $seller = $import->seller;

        $inventoryLocation = InventoryLocation::firstOrCreate(
            ['store_id' => $store->id, 'seller_id' => $seller->id],
            [
                'name' => "{$store->name} Stock",
                'code' => 'LOC-' . strtoupper(Str::random(6)),
                'type' => InventoryLocationType::STORE,
                'is_active' => true,
            ]
        );

        $import->update(['status' => 'processing']);

        $importedCount = 0;
        $failedCount = 0;
        $errors = [];
        $rowNumber = 1;

        $headers = fgetcsv($handle) ?: [];
        if (!empty($headers[0])) {
            $headers[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $headers[0]);
        }

        while (($data = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (count(array_filter($data)) === 0) {
                continue;
            }

            $row = [];
            foreach ($headers as $idx => $header) {
                $row[$header] = $data[$idx] ?? '';
            }

            // Extract mapped attributes
            $sku = trim((string) ($this->getMappedValue($row, $mapping, 'sku') ?: ''));
            $title = trim((string) ($this->getMappedValue($row, $mapping, 'title') ?: ''));
            $categoryName = trim((string) ($this->getMappedValue($row, $mapping, 'category') ?: 'General'));
            $sellingPrice = (float) ($this->getMappedValue($row, $mapping, 'selling_price') ?: 0.0);
            $mrp = (float) ($this->getMappedValue($row, $mapping, 'mrp') ?: $sellingPrice);
            $qty = (int) ($this->getMappedValue($row, $mapping, 'quantity') ?: 0);
            $weight = (int) ($this->getMappedValue($row, $mapping, 'weight_grams') ?: 500);
            $description = (string) ($this->getMappedValue($row, $mapping, 'description') ?: '');

            // Validation checks
            if (empty($title)) {
                $errors[] = "Row {$rowNumber}: Product title is required.";
                $failedCount++;
                continue;
            }
            if ($sellingPrice <= 0) {
                $errors[] = "Row {$rowNumber} ({$title}): Selling price must be greater than zero.";
                $failedCount++;
                continue;
            }
            if ($mrp < $sellingPrice) {
                $mrp = $sellingPrice; // Auto fix or warn
            }

            if (empty($sku)) {
                $sku = 'SKU-' . strtoupper(Str::slug(substr($title, 0, 10))) . '-' . Str::random(4);
            }

            if ($isDryRun) {
                $importedCount++;
                continue;
            }

            try {
                DB::transaction(function () use ($seller, $title, $categoryName, $description, $sku, $sellingPrice, $mrp, $weight, $qty, $inventoryLocation) {
                    // Category resolution
                    $category = Category::firstOrCreate(
                        ['name' => $categoryName],
                        [
                            'slug' => Str::slug($categoryName),
                            'is_active' => true,
                            'commission_rate' => 10.00,
                        ]
                    );

                    // Product resolution
                    $productSlug = Str::slug($title);
                    if (Product::where('slug', $productSlug)->exists()) {
                        $productSlug .= '-' . Str::random(4);
                    }

                    $product = Product::updateOrCreate(
                        ['seller_id' => $seller->id, 'title' => $title],
                        [
                            'category_id' => $category->id,
                            'slug' => $productSlug,
                            'description' => $description,
                            'status' => ProductStatus::ACTIVE,
                            'is_active' => true,
                        ]
                    );

                    // Variant resolution
                    $variant = ProductVariant::updateOrCreate(
                        ['product_id' => $product->id, 'sku' => $sku],
                        [
                            'name' => 'Standard',
                            'selling_price' => $sellingPrice,
                            'mrp' => $mrp,
                            'weight_grams' => $weight,
                            'is_active' => true,
                        ]
                    );

                    // Stock inventory assignment
                    Inventory::updateOrCreate(
                        [
                            'variant_id' => $variant->id,
                            'location_id' => $inventoryLocation->id,
                        ],
                        [
                            'quantity_on_hand' => $qty,
                            'low_stock_threshold' => 5,
                        ]
                    );
                });

                $importedCount++;
            } catch (\Throwable $e) {
                $failedCount++;
                $errors[] = "Row {$rowNumber} ({$sku}): " . $e->getMessage();
            }
        }
        fclose($handle);

        $import->update([
            'status' => $failedCount > 0 && $importedCount === 0 ? 'failed' : 'completed',
            'imported_rows' => $importedCount,
            'failed_rows' => $failedCount,
            'errors' => array_slice($errors, 0, 50),
        ]);

        return [
            'success' => $importedCount > 0,
            'imported' => $importedCount,
            'failed' => $failedCount,
            'errors' => $errors,
        ];
    }

    /**
     * Helper to open stream from disk with fallback.
     */
    protected function openStream(string $path)
    {
        if (Storage::exists($path)) {
            $stream = Storage::readStream($path);
            if ($stream) {
                return $stream;
            }
        }

        $fullPath = Storage::path($path);
        if (file_exists($fullPath)) {
            return fopen($fullPath, 'r');
        }

        return false;
    }

    /**
     * Helper to auto-map CSV column names to standard fields.
     */
    protected function detectColumnMapping(array $headers): array
    {
        $mapping = [];
        $candidates = [
            'sku' => ['sku', 'item_code', 'code', 'product_sku', 'barcode'],
            'title' => ['title', 'name', 'product_name', 'item_name', 'product'],
            'category' => ['category', 'category_name', 'cat', 'department'],
            'selling_price' => ['selling_price', 'price', 'sale_price', 'unit_price', 'rate'],
            'mrp' => ['mrp', 'maximum_retail_price', 'retail_price', 'list_price'],
            'quantity' => ['quantity', 'qty', 'stock', 'stock_quantity', 'inventory', 'balance'],
            'weight_grams' => ['weight_grams', 'weight', 'weight_g', 'grams'],
            'description' => ['description', 'desc', 'short_description', 'details'],
        ];

        foreach ($candidates as $field => $matches) {
            foreach ($headers as $header) {
                $clean = strtolower(trim(str_replace([' ', '-'], '_', (string) $header)));
                if ($clean === $field || in_array($clean, $matches, true)) {
                    $mapping[$field] = $header;
                    break;
                }
            }
        }

        return $mapping;
    }

    protected function getMappedValue(array $row, array $mapping, string $field): mixed
    {
        $mappedHeader = $mapping[$field] ?? $field;
        return $row[$mappedHeader] ?? $row[$field] ?? null;
    }
}
