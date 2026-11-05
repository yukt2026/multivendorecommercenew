<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryPartner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeliveryPartnerController extends Controller
{
    /**
     * Delivery partners fleet management (/admin/delivery-partners).
     */
    public function index(Request $request): View
    {
        $query = DeliveryPartner::query()->withCount('assignments');

        if ($request->has('search')) {
            $s = $request->query('search');
            $query->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%"));
        }

        if ($request->has('status') && $request->query('status') !== 'all') {
            $query->where('status', $request->query('status'));
        }

        $riders = $query->latest('id')->paginate(15);

        return view('admin.delivery-partners.index', [
            'riders' => $riders,
        ]);
    }

    /**
     * Store a new delivery partner / rider.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'required|string|max:20|unique:delivery_partners,phone',
            'vehicle' => 'required|string|max:50',
            'vehicle_number' => 'nullable|string|max:30',
            'current_lat' => 'nullable|numeric',
            'current_lng' => 'nullable|numeric',
            'is_active' => 'nullable|boolean',
        ]);

        DeliveryPartner::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'vehicle' => $validated['vehicle'],
            'vehicle_number' => $validated['vehicle_number'] ?? null,
            'current_lat' => $validated['current_lat'] ?? 12.9716,
            'current_lng' => $validated['current_lng'] ?? 77.5946,
            'is_active' => $request->has('is_active') ? (bool) $validated['is_active'] : true,
            'status' => 'available',
            'active_orders_count' => 0,
            'last_location_updated_at' => now(),
        ]);

        return back()->with('success', "Delivery partner {$validated['name']} registered successfully!");
    }

    /**
     * Toggle active/offline status of a delivery partner.
     */
    public function toggleStatus(Request $request, DeliveryPartner $deliveryPartner): RedirectResponse
    {
        $newStatus = $request->input('status');
        if (in_array($newStatus, ['available', 'busy', 'offline'], true)) {
            $deliveryPartner->status = $newStatus;
            $deliveryPartner->is_active = ($newStatus !== 'offline');
        } else {
            $deliveryPartner->is_active = !$deliveryPartner->is_active;
            $deliveryPartner->status = $deliveryPartner->is_active ? 'available' : 'offline';
        }

        $deliveryPartner->save();

        return back()->with('success', "Status updated for rider {$deliveryPartner->name}.");
    }
}
