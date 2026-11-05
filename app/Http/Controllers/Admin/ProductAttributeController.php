<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductAttributeRequest;
use App\Http\Requests\Admin\ProductAttributeValueRequest;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Services\Admin\AdminCatalogueService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ProductAttributeController extends Controller
{
    public function __construct(
        protected AdminCatalogueService $catalogueService
    ) {}

    /**
     * Display attributes & variant options manager.
     */
    public function index(): View
    {
        $attributes = $this->catalogueService->getAttributesWithValues();

        return view('admin.catalogue.attributes.index', [
            'attributes' => $attributes,
        ]);
    }

    /**
     * Store new attribute.
     */
    public function store(ProductAttributeRequest $request): RedirectResponse
    {
        $attribute = $this->catalogueService->createAttribute($request->validated());

        return redirect()
            ->route('admin.catalogue.attributes.index')
            ->with('success', "Attribute '{$attribute->name}' created successfully.");
    }

    /**
     * Update attribute.
     */
    public function update(ProductAttributeRequest $request, ProductAttribute $attribute): RedirectResponse
    {
        $this->catalogueService->updateAttribute($attribute, $request->validated());

        return redirect()
            ->route('admin.catalogue.attributes.index')
            ->with('success', "Attribute '{$attribute->name}' updated successfully.");
    }

    /**
     * Delete attribute and all associated values.
     */
    public function destroy(ProductAttribute $attribute): RedirectResponse
    {
        $name = $attribute->name;
        $this->catalogueService->deleteAttribute($attribute);

        return redirect()
            ->route('admin.catalogue.attributes.index')
            ->with('success', "Attribute '{$name}' deleted successfully.");
    }

    /**
     * Add an attribute value (e.g. Size: "XL", Color: "Navy Blue" with code "#000080").
     */
    public function storeValue(ProductAttributeValueRequest $request, ProductAttribute $attribute): RedirectResponse
    {
        $val = $this->catalogueService->addAttributeValue($attribute, $request->validated());

        return redirect()
            ->route('admin.catalogue.attributes.index')
            ->with('success', "Value '{$val->value}' added to {$attribute->name}.");
    }

    /**
     * Delete an individual attribute value.
     */
    public function destroyValue(ProductAttribute $attribute, ProductAttributeValue $value): RedirectResponse
    {
        $valName = $value->value;
        $this->catalogueService->deleteAttributeValue($value);

        return redirect()
            ->route('admin.catalogue.attributes.index')
            ->with('success', "Value '{$valName}' removed from {$attribute->name}.");
    }
}
