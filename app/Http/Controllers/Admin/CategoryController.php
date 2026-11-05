<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Services\Admin\AdminCatalogueService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(
        protected AdminCatalogueService $catalogueService
    ) {}

    /**
     * Display the visual category tree with drag-and-drop sort.
     */
    public function index(): View
    {
        $this->authorize('viewAny', Category::class);

        $categories = $this->catalogueService->getCategoryTree();
        $flatCategories = $this->catalogueService->getAllCategoriesList();

        return view('admin.catalogue.categories.index', [
            'categoryTree' => $categories,
            'flatCategories' => $flatCategories,
        ]);
    }

    /**
     * Store newly created category.
     */
    public function store(CategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', Category::class);

        $category = $this->catalogueService->createCategory($request->validated());

        return redirect()
            ->route('admin.catalogue.categories.index')
            ->with('success', "Category '{$category->name}' created successfully.");
    }

    /**
     * Update existing category.
     */
    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $this->catalogueService->updateCategory($category, $request->validated());

        return redirect()
            ->route('admin.catalogue.categories.index')
            ->with('success', "Category '{$category->name}' updated successfully.");
    }

    /**
     * Delete a category.
     */
    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        $name = $category->name;
        $this->catalogueService->deleteCategory($category);

        return redirect()
            ->route('admin.catalogue.categories.index')
            ->with('success', "Category '{$name}' deleted successfully.");
    }

    /**
     * Save drag-and-drop sort order and hierarchy.
     */
    public function reorder(Request $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        $request->validate([
            'categories' => ['required', 'array'],
            'categories.*.id' => ['required', 'integer', 'exists:categories,id'],
            'categories.*.sort_order' => ['required', 'integer', 'min:0'],
            'categories.*.parent_id' => ['nullable', 'integer'],
        ]);

        $this->catalogueService->reorderCategories((array) $request->input('categories'));

        return response()->json([
            'success' => true,
            'message' => 'Category ordering saved successfully.',
        ]);
    }
}
