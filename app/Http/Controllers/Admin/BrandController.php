<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandRequest;
use App\Models\Brand;
use App\Models\Seller;
use App\Services\Admin\AdminCatalogueService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function __construct(
        protected AdminCatalogueService $catalogueService
    ) {}

    /**
     * Display brands index with search and filters.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Brand::class);

        $filters = [
            'search' => $request->query('search'),
            'verified' => $request->query('verified'),
            'seller_id' => $request->query('seller_id'),
        ];

        $brands = $this->catalogueService->getFilteredBrands($filters, 15);
        $sellers = Seller::orderBy('business_name')->get();

        $stats = [
            'total' => Brand::count(),
            'verified' => Brand::where('is_verified', true)->count(),
            'unverified' => Brand::where('is_verified', false)->count(),
            'merchant_owned' => Brand::whereNotNull('seller_id')->count(),
        ];

        return view('admin.catalogue.brands.index', [
            'brands' => $brands,
            'filters' => $filters,
            'sellers' => $sellers,
            'stats' => $stats,
        ]);
    }

    /**
     * Store new brand.
     */
    public function store(BrandRequest $request): RedirectResponse
    {
        $this->authorize('create', Brand::class);

        $data = $request->validated();
        if ($request->hasFile('logo_file')) {
            $data['logo'] = $request->file('logo_file')->store('brands', 'public');
        }

        $brand = $this->catalogueService->createBrand($data);

        return redirect()
            ->route('admin.catalogue.brands.index')
            ->with('success', "Brand '{$brand->name}' created successfully.");
    }

    /**
     * Update existing brand.
     */
    public function update(BrandRequest $request, Brand $brand): RedirectResponse
    {
        $this->authorize('update', $brand);

        $data = $request->validated();
        if ($request->hasFile('logo_file')) {
            $data['logo'] = $request->file('logo_file')->store('brands', 'public');
        }

        $this->catalogueService->updateBrand($brand, $data);

        return redirect()
            ->route('admin.catalogue.brands.index')
            ->with('success', "Brand '{$brand->name}' updated successfully.");
    }

    /**
     * Delete a brand.
     */
    public function destroy(Brand $brand): RedirectResponse
    {
        $this->authorize('delete', $brand);

        $name = $brand->name;
        $this->catalogueService->deleteBrand($brand);

        return redirect()
            ->route('admin.catalogue.brands.index')
            ->with('success', "Brand '{$name}' deleted successfully.");
    }

    /**
     * Toggle brand verification status.
     */
    public function toggleVerify(Brand $brand): RedirectResponse
    {
        $this->authorize('update', $brand);

        $isVerified = $this->catalogueService->toggleBrandVerification($brand);
        $msg = $isVerified ? "Brand '{$brand->name}' marked as verified." : "Brand '{$brand->name}' verification revoked.";

        return redirect()
            ->route('admin.catalogue.brands.index')
            ->with('success', $msg);
    }
}
