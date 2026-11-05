<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\ProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Seller\SellerProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    public function __construct(
        protected SellerProductService $productService
    ) {}

    /**
     * Display seller product list with search, filters, pagination, and bulk actions.
     */
    public function index(Request $request): View
    {
        $seller = Auth::user()?->seller;
        abort_if(!$seller, 403, 'No seller profile attached.');

        $filters = $request->only(['search', 'category_id', 'brand_id', 'status', 'stock', 'quick_capable']);
        $products = $this->productService->listProducts($seller, $filters, 15);

        // Load categories and brands for filter dropdowns
        $categories = Category::orderBy('name')->get(['id', 'name']);
        $brands = Brand::where(function ($q) use ($seller) {
            $q->whereNull('seller_id')->orWhere('seller_id', $seller->id);
        })->orderBy('name')->get(['id', 'name']);

        // Count chips
        $counts = [
            'all' => Product::where('seller_id', $seller->id)->count(),
            'active' => Product::where('seller_id', $seller->id)->where('status', ProductStatus::ACTIVE)->count(),
            'pending' => Product::where('seller_id', $seller->id)->where('status', ProductStatus::PENDING_REVIEW)->count(),
            'draft' => Product::where('seller_id', $seller->id)->where('status', ProductStatus::DRAFT)->count(),
            'rejected' => Product::where('seller_id', $seller->id)->where('status', ProductStatus::REJECTED)->count(),
        ];

        return view('seller.products.index', [
            'products' => $products,
            'filters' => $filters,
            'categories' => $categories,
            'brands' => $brands,
            'counts' => $counts,
        ]);
    }

    /**
     * Show form to create a new product.
     */
    public function create(): View
    {
        $seller = Auth::user()?->seller;
        abort_if(!$seller, 403);

        $categories = $this->getNestedCategories();
        $brands = Brand::where(function ($q) use ($seller) {
            $q->whereNull('seller_id')->orWhere('seller_id', $seller->id);
        })->orderBy('name')->get(['id', 'name', 'is_verified']);

        return view('seller.products.create', [
            'seller' => $seller,
            'categories' => $categories,
            'brands' => $brands,
        ]);
    }

    /**
     * Store new product in database.
     */
    public function store(ProductRequest $request): RedirectResponse
    {
        $seller = Auth::user()?->seller;
        abort_if(!$seller, 403);

        $uploadedMedia = $request->file('media', []);
        $product = $this->productService->createProduct($seller, $request->validated(), is_array($uploadedMedia) ? $uploadedMedia : []);

        $msg = $product->status === ProductStatus::PENDING_REVIEW
            ? 'Product created and submitted for admin review successfully!'
            : 'Product saved as draft successfully!';

        return redirect()->route('seller.products.index')->with('success', $msg);
    }

    /**
     * Show edit form with sticky save bar and unsaved-changes warning.
     */
    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        $product->load([
            'variants.inventories',
            'media' => fn ($q) => $q->orderBy('sort_order'),
            'specifications',
            'category',
            'brand',
        ]);

        $seller = Auth::user()->seller;
        $categories = $this->getNestedCategories();
        $brands = Brand::where(function ($q) use ($seller) {
            $q->whereNull('seller_id')->orWhere('seller_id', $seller->id);
        })->orderBy('name')->get(['id', 'name', 'is_verified']);

        return view('seller.products.edit', [
            'product' => $product,
            'seller' => $seller,
            'categories' => $categories,
            'brands' => $brands,
        ]);
    }

    /**
     * Update product details, variants, media, and specifications.
     */
    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $uploadedMedia = $request->file('media', []);
        $result = $this->productService->updateProduct(
            $product,
            $request->validated(),
            is_array($uploadedMedia) ? $uploadedMedia : []
        );

        $updatedProduct = $result['product'];
        $retriggered = $result['retriggered_review'];

        if ($retriggered) {
            $msg = 'Sensitive changes detected (pricing, taxes, or core details). Product has been resubmitted for admin review.';
        } elseif ($updatedProduct->status === ProductStatus::PENDING_REVIEW) {
            $msg = 'Product changes saved and submitted for admin review!';
        } else {
            $msg = 'Product details and inventory updated successfully!';
        }

        return redirect()->route('seller.products.edit', $updatedProduct)
            ->with($retriggered ? 'info' : 'success', $msg);
    }

    /**
     * Soft delete a product.
     */
    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $product->delete();

        return redirect()->route('seller.products.index')
            ->with('success', "Product '{$product->title}' has been deleted.");
    }

    /**
     * Inline price & stock quick-edit AJAX handler.
     */
    public function quickUpdateVariant(Request $request, ProductVariant $variant): JsonResponse
    {
        $seller = Auth::user()?->seller;
        abort_if(!$seller, 403);

        $validated = $request->validate([
            'mrp' => ['nullable', 'numeric', 'min:0.01'],
            'selling_price' => ['nullable', 'numeric', 'min:0.01'],
            'quantity_on_hand' => ['nullable', 'integer', 'min:0'],
        ]);

        $updated = $this->productService->quickUpdateVariant($seller, $variant, $validated);

        return response()->json([
            'success' => true,
            'message' => "Quick update applied for SKU: {$updated->sku}",
            'variant' => [
                'id' => $updated->id,
                'mrp' => number_format((float) $updated->mrp, 2),
                'selling_price' => number_format((float) $updated->selling_price, 2),
                'stock' => $updated->inventories->sum('quantity_on_hand'),
            ],
        ]);
    }

    /**
     * Bulk actions handler (activate, deactivate, delete).
     */
    public function bulkAction(Request $request): RedirectResponse
    {
        $seller = Auth::user()?->seller;
        abort_if(!$seller, 403);

        $request->validate([
            'action' => ['required', 'in:activate,deactivate,delete'],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        $count = $this->productService->bulkAction($seller, $request->action, $request->product_ids);

        return back()->with('success', "Successfully processed {$count} product(s).");
    }

    /**
     * Export filtered products catalogue as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $seller = Auth::user()?->seller;
        abort_if(!$seller, 403);

        $filters = $request->only(['category_id', 'brand_id', 'status']);
        return $this->productService->exportCsv($seller, $filters);
    }

    /**
     * AJAX live SKU unique check.
     */
    public function checkSku(Request $request): JsonResponse
    {
        $seller = Auth::user()?->seller;
        abort_if(!$seller, 403);

        $sku = (string) $request->input('sku', '');
        $excludeId = $request->input('exclude_variant_id') ? (int) $request->input('exclude_variant_id') : null;

        $result = $this->productService->checkSkuAvailability($sku, $excludeId);

        return response()->json($result);
    }

    /**
     * Helper to retrieve nested categories formatted with hierarchy path.
     *
     * @return array<int, array{id: int, name: string, path: string}>
     */
    protected function getNestedCategories(): array
    {
        $rootCategories = Category::with('children.children')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        $flattened = [];

        foreach ($rootCategories as $root) {
            $flattened[] = [
                'id' => $root->id,
                'name' => $root->name,
                'path' => $root->name,
            ];

            foreach ($root->children as $child) {
                $flattened[] = [
                    'id' => $child->id,
                    'name' => $child->name,
                    'path' => "{$root->name} &rarr; {$child->name}",
                ];

                foreach ($child->children as $subChild) {
                    $flattened[] = [
                        'id' => $subChild->id,
                        'name' => $subChild->name,
                        'path' => "{$root->name} &rarr; {$child->name} &rarr; {$subChild->name}",
                    ];
                }
            }
        }

        return $flattened;
    }
}
