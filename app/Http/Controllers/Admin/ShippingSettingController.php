<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CODRemittanceStatus;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Jobs\Cargo\CreateShipmentJob;
use App\Jobs\Cargo\TrackingSyncJob;
use App\Models\CodRemittance;
use App\Models\NdrCase;
use App\Models\RtoCase;
use App\Models\Setting;
use App\Models\Shipment;
use App\Services\Cargo\Contracts\LogisticsProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShippingSettingController extends Controller
{
    public function __construct(
        protected LogisticsProvider $logisticsProvider
    ) {}

    /**
     * Display shipping logistics overview and configuration console.
     */
    public function index(): View
    {
        $settings = [
            'courier_priorities' => Setting::get('shipping_courier_priorities', ['onestall_cargo', 'delhivery', 'bluedart', 'shadowfax']),
            'default_weight_kg' => (float) Setting::get('shipping_default_weight_kg', 0.5),
            'cod_max_limit' => (float) Setting::get('shipping_cod_max_limit', 5000.00),
            'free_shipping_threshold' => (float) Setting::get('shipping_free_threshold', 999.00),
            'quick_max_radius_km' => (float) Setting::get('shipping_quick_max_radius_km', 8.00),
            'standard_base_fee' => (float) Setting::get('shipping_standard_base_fee', 40.00),
            'rto_max_attempts' => (int) Setting::get('shipping_rto_max_attempts', 3),
            // OneStall Cargo API
            'cargo_base_url' => Setting::get('cargo_base_url', config('services.cargo.base_url', 'https://api.onestallcargo.internal/v1')),
            'cargo_api_key' => Setting::get('cargo_api_key', config('services.cargo.api_key', 'osc_test_api_key_sec123')),
            'cargo_api_secret' => Setting::get('cargo_api_secret', config('services.cargo.api_secret', 'osc_test_secret_abc456')),
            'cargo_webhook_secret' => Setting::get('cargo_webhook_secret', config('services.cargo.webhook_secret', 'osc_webhook_secret_xyz789')),
            'cargo_fake' => (bool) (Setting::get('cargo_fake') ?? config('services.cargo.fake', true)),
        ];

        $metrics = [
            'total_shipments' => Shipment::count(),
            'in_transit' => Shipment::whereIn('status', [ShipmentStatus::IN_TRANSIT, ShipmentStatus::OUT_FOR_DELIVERY, ShipmentStatus::PICKED_UP])->count(),
            'ndr_unresolved' => NdrCase::where('status', 'pending')->count(),
            'rto_total' => RtoCase::count(),
            'cod_pending_remittance' => (float) CodRemittance::where('status', CODRemittanceStatus::COLLECTED)->sum('amount'),
            'failed_consignments' => Shipment::where('status', ShipmentStatus::FAILED)->orWhereNull('awb')->count(),
        ];

        $recentShipments = Shipment::with(['fulfilmentOrder.order', 'fulfilmentOrder.seller'])
            ->latest('id')
            ->limit(10)
            ->get();

        return view('admin.shipping.index', compact('settings', 'metrics', 'recentShipments'));
    }

    /**
     * Display failed shipment queue with retry action.
     */
    public function failedQueue(Request $request): View
    {
        $failedShipments = Shipment::query()
            ->where(function ($q) {
                $q->where('status', ShipmentStatus::FAILED)
                  ->orWhereNull('awb');
            })
            ->with(['fulfilmentOrder.seller', 'fulfilmentOrder.order'])
            ->latest('id')
            ->paginate(15);

        return view('admin.shipping.failed-queue', compact('failedShipments'));
    }

    /**
     * Retry failed shipment booking or AWB generation.
     */
    public function retryShipment(Shipment $shipment): RedirectResponse
    {
        try {
            if (!$shipment->awb) {
                $this->logisticsProvider->generateAwb($shipment);
            }

            if ($shipment->status === ShipmentStatus::FAILED) {
                $shipment->status = ShipmentStatus::MANIFEST_CREATED;
                $shipment->save();
            }

            return back()->with('success', "Shipment #{$shipment->shipment_number} retried successfully. AWB: {$shipment->awb}");
        } catch (\Throwable $e) {
            return back()->with('error', "Retry failed: " . $e->getMessage());
        }
    }

    /**
     * Trigger bulk tracking sync for all in-transit shipments.
     */
    public function syncAllTracking(): RedirectResponse
    {
        TrackingSyncJob::dispatch();

        return back()->with('success', 'Bulk logistics tracking synchronization job has been dispatched.');
    }

    /**
     * Update shipping and courier configurations.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'default_weight_kg' => ['required', 'numeric', 'min:0.01'],
            'cod_max_limit' => ['required', 'numeric', 'min:0'],
            'free_shipping_threshold' => ['required', 'numeric', 'min:0'],
            'quick_max_radius_km' => ['required', 'numeric', 'min:1'],
            'standard_base_fee' => ['required', 'numeric', 'min:0'],
            'rto_max_attempts' => ['required', 'integer', 'min:1', 'max:5'],
            'courier_priorities' => ['required', 'array'],
            'cargo_base_url' => ['nullable', 'string', 'max:255'],
            'cargo_api_key' => ['nullable', 'string', 'max:255'],
            'cargo_api_secret' => ['nullable', 'string', 'max:255'],
            'cargo_webhook_secret' => ['nullable', 'string', 'max:255'],
            'cargo_fake' => ['nullable', 'boolean'],
        ]);

        Setting::set('shipping_default_weight_kg', $validated['default_weight_kg'], 'shipping');
        Setting::set('shipping_cod_max_limit', $validated['cod_max_limit'], 'shipping');
        Setting::set('shipping_free_threshold', $validated['free_shipping_threshold'], 'shipping');
        Setting::set('shipping_quick_max_radius_km', $validated['quick_max_radius_km'], 'shipping');
        Setting::set('shipping_standard_base_fee', $validated['standard_base_fee'], 'shipping');
        Setting::set('shipping_rto_max_attempts', $validated['rto_max_attempts'], 'shipping');
        Setting::set('shipping_courier_priorities', $validated['courier_priorities'], 'shipping');

        // OneStall Cargo Platform Settings
        if (!empty($validated['cargo_base_url'])) {
            Setting::set('cargo_base_url', $validated['cargo_base_url'], 'cargo');
        }
        if (!empty($validated['cargo_api_key'])) {
            Setting::set('cargo_api_key', $validated['cargo_api_key'], 'cargo');
        }
        if (!empty($validated['cargo_api_secret'])) {
            Setting::set('cargo_api_secret', $validated['cargo_api_secret'], 'cargo');
        }
        if (!empty($validated['cargo_webhook_secret'])) {
            Setting::set('cargo_webhook_secret', $validated['cargo_webhook_secret'], 'cargo');
        }
        Setting::set('cargo_fake', $request->boolean('cargo_fake'), 'cargo');

        if (function_exists('activity')) {
            activity('settings')
                ->causedBy(auth()->user())
                ->log("Shipping and courier routing settings updated");
        }

        return back()->with('success', 'Shipping and OneStall Cargo logistics configuration updated successfully.');
    }
}
