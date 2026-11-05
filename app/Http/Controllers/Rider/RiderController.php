<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rider;

use App\Enums\DeliveryAssignmentStatus;
use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryPartner;
use App\Services\Order\OrderStateMachine;
use App\Services\QuickCommerce\QuickCommerceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RiderController extends Controller
{
    public function __construct(
        protected QuickCommerceService $quickService,
        protected OrderStateMachine $stateMachine
    ) {}

    /**
     * Rider Mobile-Friendly Web Dashboard (/rider).
     */
    public function dashboard(Request $request): View
    {
        // Resolve active rider profile by session, auth user, or demo rider ID
        $rider = $this->resolveRider($request);

        $activeAssignments = DeliveryAssignment::query()
            ->with(['fulfilmentOrder.store.location', 'fulfilmentOrder.order.customerAddress', 'fulfilmentOrder.items'])
            ->where('delivery_partner_id', $rider->id)
            ->whereIn('status', [DeliveryAssignmentStatus::ASSIGNED, DeliveryAssignmentStatus::ACCEPTED, DeliveryAssignmentStatus::PICKED_UP])
            ->latest('id')
            ->get();

        $completedToday = DeliveryAssignment::query()
            ->where('delivery_partner_id', $rider->id)
            ->where('status', DeliveryAssignmentStatus::DELIVERED)
            ->whereDate('delivered_at', now())
            ->count();

        $allRiders = DeliveryPartner::query()->where('is_active', true)->get();

        return view('rider.index', [
            'rider' => $rider,
            'allRiders' => $allRiders,
            'activeAssignments' => $activeAssignments,
            'completedToday' => $completedToday,
        ]);
    }

    /**
     * Detailed mobile assignment view (/rider/orders/{assignment}).
     */
    public function showAssignment(Request $request, DeliveryAssignment $assignment): View
    {
        $assignment->load([
            'fulfilmentOrder.store.location',
            'fulfilmentOrder.order.customerAddress',
            'fulfilmentOrder.items',
            'deliveryPartner',
        ]);

        return view('rider.assignment', [
            'assignment' => $assignment,
            'fulfilmentOrder' => $assignment->fulfilmentOrder,
            'store' => $assignment->fulfilmentOrder?->store,
            'customerAddress' => $assignment->fulfilmentOrder?->order?->customerAddress,
        ]);
    }

    /**
     * Accept assigned delivery job.
     */
    public function accept(Request $request, DeliveryAssignment $assignment): JsonResponse|RedirectResponse
    {
        $assignment->status = DeliveryAssignmentStatus::ACCEPTED;
        $assignment->accepted_at = now();
        $assignment->save();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Assignment accepted!']);
        }

        return back()->with('success', 'Assignment accepted! Navigate to store for pickup.');
    }

    /**
     * Mark order picked up from store (starts out_for_delivery).
     */
    public function pickup(Request $request, DeliveryAssignment $assignment): JsonResponse|RedirectResponse
    {
        $assignment->status = DeliveryAssignmentStatus::PICKED_UP;
        $assignment->picked_up_at = now();
        $assignment->save();

        $fo = $assignment->fulfilmentOrder;
        if ($fo) {
            if ($this->stateMachine->canTransitionFulfilment($fo, 'picked_up')) {
                $this->stateMachine->transitionFulfilmentOrder($fo, 'picked_up', 'Rider picked up order from store');
            }
            if ($this->stateMachine->canTransitionFulfilment($fo, 'out_for_delivery')) {
                $this->stateMachine->transitionFulfilmentOrder($fo, 'out_for_delivery', 'Order is out for delivery with rider');
            }
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Order marked picked up!']);
        }

        return back()->with('success', 'Order picked up! Navigate to customer drop-off location.');
    }

    /**
     * Complete delivery with Customer OTP handshake verification.
     */
    public function deliver(Request $request, DeliveryAssignment $assignment): JsonResponse|RedirectResponse
    {
        $request->validate([
            'otp' => 'required|string|size:6',
        ]);

        $otp = $request->input('otp');
        $fo = $assignment->fulfilmentOrder;

        if (!$fo) {
            abort(404, 'Fulfilment order not found.');
        }

        $result = $this->quickService->verifyDeliveryOtp($fo, $otp);

        if (!$result['success']) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $result['message']], 422);
            }
            return back()->with('error', $result['message']);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $result['message']]);
        }

        return redirect()->route('rider.dashboard')->with('success', 'Order delivered successfully! Great job.');
    }

    /**
     * Telemetry Endpoint: Mobile GPS Location Ping (/api/v1/rider/location).
     */
    public function updateLocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rider_id' => 'required|exists:delivery_partners,id',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $partner = DeliveryPartner::findOrFail($validated['rider_id']);
        $partner->current_lat = (float) $validated['lat'];
        $partner->current_lng = (float) $validated['lng'];
        $partner->last_location_updated_at = now();
        $partner->save();

        return response()->json([
            'success' => true,
            'message' => 'Rider location updated.',
            'rider_id' => $partner->id,
            'lat' => $partner->current_lat,
            'lng' => $partner->current_lng,
            'updated_at' => $partner->last_location_updated_at->toIso8601String(),
        ]);
    }

    /**
     * Switch current active demo rider for simulation.
     */
    public function switchRider(Request $request): RedirectResponse
    {
        $request->validate(['rider_id' => 'required|exists:delivery_partners,id']);
        session(['active_rider_id' => $request->input('rider_id')]);
        return back()->with('success', 'Active rider switched.');
    }

    protected function resolveRider(Request $request): DeliveryPartner
    {
        if (session('active_rider_id')) {
            $partner = DeliveryPartner::find(session('active_rider_id'));
            if ($partner) {
                return $partner;
            }
        }

        if (auth()->check()) {
            $partner = DeliveryPartner::where('user_id', auth()->id())->first();
            if ($partner) {
                return $partner;
            }
        }

        // Return first active partner or create demo partner
        return DeliveryPartner::firstOrCreate(
            ['phone' => '9876543210'],
            [
                'name' => 'Kavitha (Quick Rider)',
                'vehicle' => 'Electric Scooter',
                'vehicle_number' => 'KA-05-EV-9821',
                'is_active' => true,
                'status' => 'available',
                'current_lat' => 12.9716,
                'current_lng' => 77.5946,
            ]
        );
    }
}
