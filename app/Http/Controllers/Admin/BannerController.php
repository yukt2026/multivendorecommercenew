<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BannerController extends Controller
{
    /**
     * Display list of homepage & promotional banners.
     */
    public function index(Request $request): View
    {
        $query = Banner::query()->orderBy('position')->orderBy('sort');

        if ($position = $request->query('position')) {
            if ($position !== 'all') {
                $query->where('position', $position);
            }
        }

        if ($search = $request->query('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        $banners = $query->paginate(15)->withQueryString();

        $metrics = [
            'total_banners' => Banner::count(),
            'active_banners' => Banner::active()->count(),
            'hero_banners' => Banner::where('position', 'hero')->count(),
            'deal_banners' => Banner::where('position', 'deal_banner')->count(),
        ];

        return view('admin.banners.index', compact('banners', 'metrics'));
    }

    /**
     * Store new promotional banner.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'in:hero,deal_banner,category_strip,sidebar'],
            'image' => ['nullable', 'image', 'max:4096'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'link' => ['nullable', 'string', 'max:500'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $imagePath = $validated['image_url'] ?? null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('banners', 'public');
        }

        $banner = Banner::create([
            'title' => $validated['title'],
            'position' => $validated['position'],
            'image' => $imagePath ?? 'https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?w=1200&q=80',
            'link' => $validated['link'] ?? '#',
            'sort' => $validated['sort'] ?? 0,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        if (function_exists('activity')) {
            activity('banner')
                ->performedOn($banner)
                ->causedBy(auth()->user())
                ->log("Banner #{$banner->id} ({$banner->title}) created");
        }

        return redirect()->route('admin.banners.index')->with('success', "Banner '{$banner->title}' created successfully.");
    }

    /**
     * Update an existing banner.
     */
    public function update(Request $request, Banner $banner): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'in:hero,deal_banner,category_strip,sidebar'],
            'image' => ['nullable', 'image', 'max:4096'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'link' => ['nullable', 'string', 'max:500'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('banners', 'public');
        } elseif (!empty($validated['image_url'])) {
            $validated['image'] = $validated['image_url'];
        }

        unset($validated['image_url']);
        $validated['is_active'] = $request->boolean('is_active');

        $banner->update($validated);

        return redirect()->route('admin.banners.index')->with('success', "Banner '{$banner->title}' updated successfully.");
    }

    /**
     * Delete a banner.
     */
    public function destroy(Banner $banner): RedirectResponse
    {
        $title = $banner->title;
        $banner->delete();

        return redirect()->route('admin.banners.index')->with('success', "Banner '{$title}' deleted.");
    }

    /**
     * Toggle banner active state.
     */
    public function toggle(Banner $banner): JsonResponse
    {
        $banner->is_active = !$banner->is_active;
        $banner->save();

        return response()->json([
            'success' => true,
            'is_active' => $banner->is_active,
        ]);
    }
}
