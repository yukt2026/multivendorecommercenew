<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectProductRequest;
use App\Http\Requests\Admin\UpdateProductOnBehalfRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Services\Admin\AdminCatalogueService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductModerationController extends Controller
{
    public function __construct(
        protected AdminCatalogueService $catalogueService
    ) {}

    /**
     * Display the product moderation queue with filters and counters.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        $filters = [
            'status' => $request->query('status', 'pending_review'),
            'search' => $request->query('search'),
            'category_id' => $request->query('category_id'),
            'seller_id' => $request->query('seller_id'),
        ];

        $products = $this->catalogueService->getModerationQueue($filters, 15);
        $stats = $this->catalogueService->getModerationStats();
        $categories = Category::orderBy('name')->get();
        $sellers = Seller::orderBy('business_name')->get();

        return view('admin.catalogue.moderation.index', [
            'products' => $products,
            'filters' => $filters,
            'stats' => $stats,
            'categories' => $categories,
            'sellers' => $sellers,
        ]);
    }

    /**
     * Side-by-side inspection and preview of a submitted product.
     */
    public function show(Product $product): View
    {
        $this->authorize('view', $product);

        $product->load([
            'seller.user',
            'category',
            'brand',
            'variants.inventories.location',
            'media',
            'specifications',
        ]);

        return view('admin.catalogue.moderation.show', [
            'product' => $product,
        ]);
    }

    /**
     * Edit-on-behalf: Admin adjusts catalogue details before approval.
     */
    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        $product->load(['category', 'brand', 'defaultVariant', 'variants']);
        $categories = Category::orderBy('name')->get();
        $brands = Brand::orderBy('name')->get();

        return view('admin.catalogue.moderation.edit', [
            'product' => $product,
            'categories' => $categories,
            'brands' => $brands,
        ]);
    }

    /**
     * Save admin changes to the product listing.
     */
    public function update(UpdateProductOnBehalfRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $this->catalogueService->updateProductOnBehalf($product, $request->validated(), $request->user());

        return redirect()
            ->route('admin.catalogue.moderation.show', $product)
            ->with('success', "Product '{$product->title}' updated on behalf of merchant.");
    }

    /**
     * Approve listing into active live catalogue.
     */
    public function approve(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('approve', $product);

        $this->catalogueService->approveProduct($product, $request->user());

        return redirect()
            ->route('admin.catalogue.moderation.index')
            ->with('success', "Product '{$product->title}' approved! Listing is now active on marketplace.");
    }

    /**
     * Reject listing with mandatory reason and feedback.
     */
    public function reject(RejectProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('reject', $product);

        $this->catalogueService->rejectProduct($product, (string) $request->validated('reason'), $request->user());

        return redirect()
            ->route('admin.catalogue.moderation.index')
            ->with('success', "Product '{$product->title}' rejected. Feedback sent to merchant.");
    }

    /**
     * Bulk approve multiple pending products.
     */
    public function bulkApprove(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Product::class);

        $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        $approvedCount = $this->catalogueService->bulkApproveProducts((array) $request->input('product_ids'), $request->user());

        return redirect()
            ->route('admin.catalogue.moderation.index')
            ->with('success', "Bulk approved {$approvedCount} product listing(s).");
    }
}
