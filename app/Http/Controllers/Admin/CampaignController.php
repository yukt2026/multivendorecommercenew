<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CampaignController extends Controller
{
    /**
     * Display promotional campaigns list.
     */
    public function index(Request $request): View
    {
        $query = Campaign::query()->latest('id');

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $campaigns = $query->paginate(15)->withQueryString();

        $metrics = [
            'total' => Campaign::count(),
            'active' => Campaign::active()->count(),
        ];

        return view('admin.campaigns.index', compact('campaigns', 'metrics'));
    }

    /**
     * Store new promotional campaign.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(4);
        $validated['is_active'] = $request->boolean('is_active', true);

        $campaign = Campaign::create($validated);

        if (function_exists('activity')) {
            activity('campaign')
                ->performedOn($campaign)
                ->causedBy(auth()->user())
                ->log("Campaign #{$campaign->id} ({$campaign->name}) created");
        }

        return redirect()->route('admin.campaigns.index')->with('success', "Campaign '{$campaign->name}' created successfully.");
    }

    /**
     * Update an existing campaign.
     */
    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $campaign->update($validated);

        return redirect()->route('admin.campaigns.index')->with('success', "Campaign '{$campaign->name}' updated successfully.");
    }

    /**
     * Delete a campaign.
     */
    public function destroy(Campaign $campaign): RedirectResponse
    {
        $name = $campaign->name;
        $campaign->delete();

        return redirect()->route('admin.campaigns.index')->with('success', "Campaign '{$name}' deleted.");
    }

    /**
     * Toggle active status.
     */
    public function toggle(Campaign $campaign): JsonResponse
    {
        $campaign->is_active = !$campaign->is_active;
        $campaign->save();

        return response()->json([
            'success' => true,
            'is_active' => $campaign->is_active,
        ]);
    }
}
