<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BannerController extends Controller
{
    /**
     * Normalize incoming position aliases to database standard positions.
     */
    private function normalizePosition(string $position): string
    {
        return match ($position) {
            'hero', 'hero_carousel', 'top_slider' => 'home_hero',
            'deal_banner', 'homepage_strip', 'promo_strip' => 'home_sub',
            'category_top' => 'category_strip',
            default => $position,
        };
    }

    /**
     * Display list of homepage & promotional banners.
     */
    public function index(Request $request): View
    {
        $query = Banner::query()->orderBy('position')->orderBy('sort');

        if ($position = $request->query('position')) {
            if ($position !== 'all') {
                if (in_array($position, ['hero', 'home_hero', 'hero_carousel', 'top_slider'], true)) {
                    $query->whereIn('position', ['home_hero', 'hero', 'hero_carousel', 'top_slider']);
                } elseif (in_array($position, ['deal_banner', 'home_sub', 'homepage_strip', 'promo_strip'], true)) {
                    $query->whereIn('position', ['home_sub', 'deal_banner', 'homepage_strip', 'promo_strip']);
                } elseif (in_array($position, ['category_strip', 'category_top'], true)) {
                    $query->whereIn('position', ['category_strip', 'category_top']);
                } else {
                    $query->where('position', $position);
                }
            }
        }

        if ($search = $request->query('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        $banners = $query->paginate(15)->withQueryString();

        $metrics = [
            'total_banners' => Banner::count(),
            'active_banners' => Banner::active()->count(),
            'hero_banners' => Banner::whereIn('position', ['home_hero', 'hero', 'hero_carousel', 'top_slider'])->count(),
            'deal_banners' => Banner::whereIn('position', ['home_sub', 'deal_banner', 'homepage_strip', 'promo_strip'])->count(),
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
            'position' => ['required', 'string', 'in:hero,home_hero,deal_banner,home_sub,category_strip,category_top,sidebar'],
            'image' => ['nullable', 'image', 'max:5120'],
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

        $position = $this->normalizePosition($validated['position']);

        $banner = Banner::create([
            'title' => $validated['title'],
            'position' => $position,
            'image' => $imagePath ?? 'https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?w=1200&q=80',
            'link' => $validated['link'] ?? '#',
            'sort' => $validated['sort'] ?? 0,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        Cache::forget('storefront.hero_banners');
        Cache::forget('storefront.promo_banners');

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
            'position' => ['required', 'string', 'in:hero,home_hero,deal_banner,home_sub,category_strip,category_top,sidebar'],
            'image' => ['nullable', 'image', 'max:5120'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'link' => ['nullable', 'string', 'max:500'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($request->hasFile('image')) {
            if ($banner->image && !str_starts_with($banner->image, 'http') && Storage::disk('public')->exists($banner->image)) {
                Storage::disk('public')->delete($banner->image);
            }
            $validated['image'] = $request->file('image')->store('banners', 'public');
        } elseif (!empty($validated['image_url'])) {
            $validated['image'] = $validated['image_url'];
        } else {
            // Keep current image if neither new file nor explicit new URL was submitted
            unset($validated['image']);
        }

        unset($validated['image_url']);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['position'] = $this->normalizePosition($validated['position']);

        $banner->update($validated);

        Cache::forget('storefront.hero_banners');
        Cache::forget('storefront.promo_banners');

        return redirect()->route('admin.banners.index')->with('success', "Banner '{$banner->title}' updated successfully.");
    }

    /**
     * Delete a banner.
     */
    public function destroy(Banner $banner): RedirectResponse
    {
        $title = $banner->title;

        if ($banner->image && !str_starts_with($banner->image, 'http') && Storage::disk('public')->exists($banner->image)) {
            Storage::disk('public')->delete($banner->image);
        }

        $banner->delete();

        Cache::forget('storefront.hero_banners');
        Cache::forget('storefront.promo_banners');

        return redirect()->route('admin.banners.index')->with('success', "Banner '{$title}' deleted.");
    }

    /**
     * Toggle banner active state.
     */
    public function toggle(Request $request, Banner $banner): JsonResponse|RedirectResponse
    {
        $banner->is_active = !$banner->is_active;
        $banner->save();

        Cache::forget('storefront.hero_banners');
        Cache::forget('storefront.promo_banners');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'is_active' => $banner->is_active,
                'message' => "Banner status updated to " . ($banner->is_active ? 'Active' : 'Inactive'),
            ]);
        }

        return back()->with('success', "Banner '{$banner->title}' status toggled to " . ($banner->is_active ? 'Active' : 'Inactive') . '.');
    }
}
