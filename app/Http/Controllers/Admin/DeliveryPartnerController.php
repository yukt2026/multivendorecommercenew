<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryPartner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliveryPartnerController extends Controller
{
    /**
     * Build shared query for fleet list and CSV export.
     */
    protected function buildQuery(Request $request)
    {
        $query = DeliveryPartner::query()->withCount('assignments');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('vehicle_number', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        if ($vehicle = $request->query('vehicle')) {
            if ($vehicle !== 'all') {
                $query->where('vehicle', $vehicle);
            }
        }

        return $query;
    }

    /**
     * Delivery partners fleet management (/admin/delivery-partners).
     */
    public function index(Request $request): View
    {
        $query = $this->buildQuery($request);

        // Metrics Summary for quick commerce fleet operations
        $metrics = [
            'total_riders' => DeliveryPartner::count(),
            'available' => DeliveryPartner::where('status', 'available')->where('is_active', true)->count(),
            'busy' => DeliveryPartner::where('status', 'busy')->count(),
            'offline' => DeliveryPartner::where('status', 'offline')->orWhere('is_active', false)->count(),
            'completed_deliveries' => DeliveryAssignment::where('status', 'delivered')->count(),
        ];

        $riders = $query->latest('id')->paginate(15)->withQueryString();

        return view('admin.delivery-partners.index', [
            'riders' => $riders,
            'metrics' => $metrics,
            'search' => $request->query('search', ''),
            'status' => $request->query('status', 'all'),
            'vehicle' => $request->query('vehicle', 'all'),
        ]);
    }

    /**
     * Store a new delivery partner / rider.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20', 'unique:delivery_partners,phone'],
            'vehicle' => ['required', 'string', 'max:50'],
            'vehicle_number' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string', 'in:available,busy,offline'],
            'current_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'current_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $status = $validated['status'] ?? 'available';
        $isActive = $request->boolean('is_active', $status !== 'offline');

        $rider = DeliveryPartner::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'vehicle' => $validated['vehicle'],
            'vehicle_number' => $validated['vehicle_number'] ?? null,
            'current_lat' => $validated['current_lat'] ?? 12.9716,
            'current_lng' => $validated['current_lng'] ?? 77.5946,
            'is_active' => $isActive,
            'status' => $status,
            'active_orders_count' => 0,
            'last_location_updated_at' => now(),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Delivery partner '{$rider->name}' registered successfully.",
                'rider' => $rider,
            ]);
        }

        return redirect()->route('admin.delivery_partners.index')->with('success', "Delivery partner '{$rider->name}' registered successfully.");
    }

    /**
     * Update an existing delivery partner.
     */
    public function update(Request $request, DeliveryPartner $deliveryPartner): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20', Rule::unique('delivery_partners', 'phone')->ignore($deliveryPartner->id)],
            'vehicle' => ['required', 'string', 'max:50'],
            'vehicle_number' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string', 'in:available,busy,offline'],
            'current_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'current_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $status = $validated['status'] ?? $deliveryPartner->status;
        $isActive = $request->has('is_active') ? $request->boolean('is_active') : ($status !== 'offline');

        $deliveryPartner->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'vehicle' => $validated['vehicle'],
            'vehicle_number' => $validated['vehicle_number'] ?? null,
            'status' => $status,
            'is_active' => $isActive,
            'current_lat' => $validated['current_lat'] ?? $deliveryPartner->current_lat,
            'current_lng' => $validated['current_lng'] ?? $deliveryPartner->current_lng,
            'last_location_updated_at' => now(),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Rider '{$deliveryPartner->name}' profile updated successfully.",
                'rider' => $deliveryPartner,
            ]);
        }

        return redirect()->route('admin.delivery_partners.index')->with('success', "Rider '{$deliveryPartner->name}' profile updated successfully.");
    }

    /**
     * Delete / Retire a delivery partner from fleet.
     */
    public function destroy(DeliveryPartner $deliveryPartner): RedirectResponse
    {
        // Check if rider has active assignments currently in progress
        $hasActiveJobs = $deliveryPartner->assignments()
            ->whereIn('status', ['assigned', 'accepted', 'picked_up'])
            ->exists();

        if ($hasActiveJobs || $deliveryPartner->active_orders_count > 0) {
            return back()->with('error', "Cannot delete rider '{$deliveryPartner->name}' because they have active delivery assignments in progress.");
        }

        $name = $deliveryPartner->name;
        $deliveryPartner->delete();

        return redirect()->route('admin.delivery_partners.index')->with('success', "Delivery partner '{$name}' removed from fleet.");
    }

    /**
     * Toggle active/offline status of a delivery partner.
     */
    public function toggleStatus(Request $request, DeliveryPartner $deliveryPartner): RedirectResponse|JsonResponse
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

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Rider '{$deliveryPartner->name}' status changed to " . ucfirst($deliveryPartner->status),
                'status' => $deliveryPartner->status,
                'is_active' => $deliveryPartner->is_active,
            ]);
        }

        return back()->with('success', "Status updated to " . ucfirst($deliveryPartner->status) . " for rider {$deliveryPartner->name}.");
    }

    /**
     * Stream CSV export of delivery fleet matching current filters.
     */
    public function export(Request $request): StreamedResponse
    {
        $riders = $this->buildQuery($request)->get();
        $fileName = 'delivery_partners_' . now()->format('Y_m_d_His') . '.csv';

        return response()->streamDownload(function () use ($riders) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'Rider Name',
                'Phone',
                'Vehicle Type',
                'Vehicle Number',
                'Duty Status',
                'Is Active',
                'Active Orders',
                'Total Completed',
                'Latitude',
                'Longitude',
                'Registered At',
            ]);

            foreach ($riders as $rider) {
                fputcsv($handle, [
                    $rider->id,
                    $rider->name,
                    $rider->phone,
                    $rider->vehicle,
                    $rider->vehicle_number ?? 'N/A',
                    ucfirst((string) $rider->status),
                    $rider->is_active ? 'Yes' : 'No',
                    $rider->active_orders_count,
                    $rider->assignments_count,
                    $rider->current_lat ?? 'N/A',
                    $rider->current_lng ?? 'N/A',
                    $rider->created_at?->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }
}
