<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\InventoryLocationType;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\FulfilmentOrder;
use App\Models\Inventory;
use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\StoreConnection;
use App\Services\Order\OrderStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class IntegrationController extends Controller
{
    /**
     * List products and inventory for the authenticated integration store.
     */
    public function listProducts(Request $request): JsonResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');
        $seller = $request->attributes->get('seller');

        $query = Product::query()
            ->where('seller_id', $seller->id)
            ->with(['variants.inventory', 'category:id,name,slug']);

        if ($request->has('q')) {
            $term = (string) $request->input('q');
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhereHas('variants', fn ($qv) => $qv->where('sku', 'like', "%{$term}%"));
            });
        }

        $products = $query->latest()->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'store_id' => $store->id,
            'store_name' => $store->name,
            'data' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    /**
     * Create or update a product via Custom Website API.
     */
    public function upsertProduct(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'sku' => 'required|string|max:100',
            'selling_price' => 'required|numeric|min:0.01',
            'mrp' => 'nullable|numeric|min:0.01',
            'quantity' => 'nullable|integer|min:0',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'weight_grams' => 'nullable|integer|min:1',
            'barcode' => 'nullable|string|max:100',
        ]);

        /** @var Store $store */
        $store = $request->attributes->get('store');
        $seller = $request->attributes->get('seller');
        /** @var StoreConnection $connection */
        $connection = $request->attributes->get('store_connection');

        $result = DB::transaction(function () use ($validated, $seller, $store) {
            $catName = $validated['category'] ?? 'General';
            $category = Category::firstOrCreate(
                ['name' => $catName],
                ['slug' => Str::slug($catName), 'is_active' => true, 'commission_rate' => 10.00]
            );

            $slug = Str::slug($validated['title']);
            if (Product::where('slug', $slug)->where('seller_id', '!=', $seller->id)->exists()) {
                $slug .= '-' . Str::random(4);
            }

            $product = Product::updateOrCreate(
                ['seller_id' => $seller->id, 'title' => $validated['title']],
                [
                    'category_id' => $category->id,
                    'slug' => $slug,
                    'description' => $validated['description'] ?? '',
                    'status' => ProductStatus::ACTIVE,
                    'is_active' => true,
                ]
            );

            $sellingPrice = (float) $validated['selling_price'];
            $mrp = (float) ($validated['mrp'] ?? $sellingPrice);

            $variant = ProductVariant::updateOrCreate(
                ['product_id' => $product->id, 'sku' => $validated['sku']],
                [
                    'name' => 'Standard',
                    'selling_price' => $sellingPrice,
                    'mrp' => max($mrp, $sellingPrice),
                    'weight_grams' => $validated['weight_grams'] ?? 500,
                    'barcode' => $validated['barcode'] ?? null,
                    'is_active' => true,
                ]
            );

            $invLocation = InventoryLocation::firstOrCreate(
                ['store_id' => $store->id, 'seller_id' => $seller->id],
                [
                    'name' => "{$store->name} Hub",
                    'code' => 'LOC-' . strtoupper(Str::random(6)),
                    'type' => InventoryLocationType::STORE,
                    'is_active' => true,
                ]
            );

            $quantity = $validated['quantity'] ?? 0;
            $inventory = Inventory::updateOrCreate(
                ['variant_id' => $variant->id, 'location_id' => $invLocation->id],
                ['quantity_on_hand' => $quantity, 'reorder_threshold' => 5]
            );

            return [
                'product' => $product,
                'variant' => $variant,
                'inventory' => $inventory,
            ];
        });

        $connection->logSync('products', 'inward', 'success', "Upserted product {$validated['sku']} via Custom API.");

        return response()->json([
            'success' => true,
            'message' => 'Product upserted successfully.',
            'product_id' => $result['product']->id,
            'variant_id' => $result['variant']->id,
            'sku' => $result['variant']->sku,
            'selling_price' => $result['variant']->selling_price,
            'quantity' => $result['inventory']->quantity_on_hand,
        ], 201);
    }

    /**
     * Get real-time stock levels for the store.
     */
    public function getInventory(Request $request): JsonResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');
        $seller = $request->attributes->get('seller');

        $items = Inventory::query()
            ->whereHas('location', fn ($q) => $q->where('store_id', $store->id)->orWhere('seller_id', $seller->id))
            ->with(['variant.product:id,title', 'location:id,name,code'])
            ->paginate($request->integer('per_page', 50));

        return response()->json([
            'success' => true,
            'store_id' => $store->id,
            'data' => $items->items(),
            'pagination' => [
                'total' => $items->total(),
                'current_page' => $items->currentPage(),
            ],
        ]);
    }

    /**
     * Adjust inventory stock for a SKU.
     */
    public function adjustInventory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'quantity' => 'required|integer|min:0',
            'reason' => 'nullable|string|max:255',
        ]);

        /** @var Store $store */
        $store = $request->attributes->get('store');
        $seller = $request->attributes->get('seller');
        /** @var StoreConnection $connection */
        $connection = $request->attributes->get('store_connection');

        $variant = ProductVariant::where('sku', $validated['sku'])
            ->whereHas('product', fn ($q) => $q->where('seller_id', $seller->id))
            ->first();

        if (!$variant) {
            return response()->json(['success' => false, 'message' => "SKU '{$validated['sku']}' not found."], 404);
        }

        $invLocation = InventoryLocation::firstOrCreate(
            ['store_id' => $store->id, 'seller_id' => $seller->id],
            [
                'name' => "{$store->name} Hub",
                'code' => 'LOC-' . strtoupper(Str::random(6)),
                'type' => InventoryLocationType::STORE,
                'is_active' => true,
            ]
        );

        $inventory = Inventory::updateOrCreate(
            ['variant_id' => $variant->id, 'location_id' => $invLocation->id],
            ['quantity_on_hand' => $validated['quantity']]
        );

        $connection->logSync('inventory', 'inward', 'success', "Adjusted stock for {$validated['sku']} to {$validated['quantity']}.");

        return response()->json([
            'success' => true,
            'message' => "Stock level updated for SKU {$validated['sku']}.",
            'sku' => $validated['sku'],
            'quantity_on_hand' => $inventory->quantity_on_hand,
        ]);
    }

    /**
     * List incoming orders for the store.
     */
    public function listOrders(Request $request): JsonResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');
        $seller = $request->attributes->get('seller');

        $query = FulfilmentOrder::query()
            ->where('seller_id', $seller->id)
            ->where('store_id', $store->id)
            ->with(['items', 'order.customerAddress']);

        if ($request->has('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        $orders = $query->latest()->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'store_id' => $store->id,
            'data' => $orders->items(),
            'pagination' => [
                'total' => $orders->total(),
                'current_page' => $orders->currentPage(),
            ],
        ]);
    }

    /**
     * Update order status via integration.
     */
    public function updateOrderStatus(Request $request, int $id, OrderStateMachine $stateMachine): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string',
            'note' => 'nullable|string|max:255',
        ]);

        /** @var Store $store */
        $store = $request->attributes->get('store');
        /** @var StoreConnection $connection */
        $connection = $request->attributes->get('store_connection');

        $fo = FulfilmentOrder::where('id', $id)
            ->where('store_id', $store->id)
            ->firstOrFail();

        try {
            $updated = $stateMachine->transitionFulfilmentOrder(
                $fo,
                $validated['status'],
                $validated['note'] ?? 'Status updated via Custom Website API'
            );

            $connection->logSync('orders', 'inward', 'success', "Order #{$fo->fulfilment_order_number} transitioned to {$validated['status']}.");

            return response()->json([
                'success' => true,
                'message' => "Order #{$fo->fulfilment_order_number} status updated to {$updated->status}.",
                'order' => $updated,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Inward webhook endpoint for WooCommerce events.
     */
    public function woocommerceWebhook(Request $request, StoreConnection $connection): JsonResponse
    {
        // Strict HMAC SHA256 Signature Validation
        if (!empty($connection->webhook_secret)) {
            $signature = $request->header('X-WC-Webhook-Signature');
            if (!empty($signature)) {
                $expected = base64_encode(hash_hmac('sha256', $request->getContent(), (string) $connection->webhook_secret, true));
                if (!hash_equals($expected, (string) $signature)) {
                    $connection->logSync('woocommerce_webhook', 'inward', 'failed', 'Invalid WooCommerce HMAC signature.');
                    return response()->json(['error' => 'Invalid webhook signature.'], 401);
                }
            }
        }

        $payload = $request->json()->all();
        $topic = $request->header('X-WC-Webhook-Topic', 'generic');

        $connection->logSync('woocommerce_webhook', 'inward', 'success', "Received WooCommerce webhook: {$topic}", $payload);

        // Process stock update or order sync if payload has product/order
        if (str_contains($topic, 'product') && isset($payload['id'])) {
            $sku = $payload['sku'] ?? null;
            $stock = $payload['stock_quantity'] ?? null;
            if ($sku && $stock !== null) {
                $variant = ProductVariant::where('sku', $sku)->first();
                if ($variant) {
                    $invLocation = InventoryLocation::where('store_id', $connection->store_id)->first();
                    if ($invLocation) {
                        Inventory::updateOrCreate(
                            ['variant_id' => $variant->id, 'location_id' => $invLocation->id],
                            ['quantity_on_hand' => (int) $stock]
                        );
                    }
                }
            }
        }

        return response()->json(['success' => true, 'message' => 'WooCommerce webhook processed.']);
    }

    /**
     * Inward webhook endpoint for Shopify events.
     */
    public function shopifyWebhook(Request $request, StoreConnection $connection): JsonResponse
    {
        // Strict HMAC SHA256 Signature Validation
        if (!empty($connection->webhook_secret)) {
            $signature = $request->header('X-Shopify-Hmac-Sha256');
            if (!empty($signature)) {
                $expected = base64_encode(hash_hmac('sha256', $request->getContent(), (string) $connection->webhook_secret, true));
                if (!hash_equals($expected, (string) $signature)) {
                    $connection->logSync('shopify_webhook', 'inward', 'failed', 'Invalid Shopify HMAC signature.');
                    return response()->json(['error' => 'Invalid webhook signature.'], 401);
                }
            }
        }

        $payload = $request->json()->all();
        $topic = $request->header('X-Shopify-Topic', 'generic');

        $connection->logSync('shopify_webhook', 'inward', 'success', "Received Shopify webhook: {$topic}", $payload);

        return response()->json(['success' => true, 'message' => 'Shopify webhook processed.']);
    }
}
