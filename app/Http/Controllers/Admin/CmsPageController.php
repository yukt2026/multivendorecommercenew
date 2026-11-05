<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CmsPageController extends Controller
{
    /**
     * Display list of static CMS pages.
     */
    public function index(Request $request): View
    {
        $query = CmsPage::query()->latest('id');

        if ($search = $request->query('search')) {
            $query->where('title', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%");
        }

        $pages = $query->paginate(15)->withQueryString();

        return view('admin.cms.index', compact('pages'));
    }

    /**
     * Show form to create new CMS page.
     */
    public function create(): View
    {
        return view('admin.cms.form', ['page' => new CmsPage()]);
    }

    /**
     * Store new CMS page.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:cms_pages,slug'],
            'content' => ['required', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['slug'] = !empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['title']);
        $validated['is_active'] = $request->boolean('is_active', true);

        $page = CmsPage::create($validated);

        if (function_exists('activity')) {
            activity('cms')
                ->performedOn($page)
                ->causedBy(auth()->user())
                ->log("CMS Page #{$page->id} ({$page->title}) created");
        }

        return redirect()->route('admin.cms.index')->with('success', "Page '{$page->title}' created successfully.");
    }

    /**
     * Show form to edit CMS page.
     */
    public function edit(CmsPage $page): View
    {
        return view('admin.cms.form', ['page' => $page]);
    }

    /**
     * Update CMS page.
     */
    public function update(Request $request, CmsPage $page): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:cms_pages,slug,' . $page->id],
            'content' => ['required', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['slug'] = Str::slug($validated['slug']);
        $validated['is_active'] = $request->boolean('is_active');

        $page->update($validated);

        if (function_exists('activity')) {
            activity('cms')
                ->performedOn($page)
                ->causedBy(auth()->user())
                ->log("CMS Page #{$page->id} ({$page->title}) updated");
        }

        return redirect()->route('admin.cms.index')->with('success', "Page '{$page->title}' updated successfully.");
    }

    /**
     * Delete CMS page.
     */
    public function destroy(CmsPage $page): RedirectResponse
    {
        $title = $page->title;
        $page->delete();

        return redirect()->route('admin.cms.index')->with('success', "Page '{$title}' deleted.");
    }
}
