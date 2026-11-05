<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Services\QuickCommerce\QuickCommerceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuickCommerceController extends Controller
{
    public function __construct(
        protected QuickCommerceService $quickService
    ) {}

    /**
     * Quick Commerce Discovery Portal (/quick).
     */
    public function index(Request $request): View
    {
        $lat = (float) $request->session()->get('user_lat', setting('default_lat', 12.9716));
        $lng = (float) $request->session()->get('user_lng', setting('default_lng', 77.5946));

        if ($request->has('lat') && $request->has('lng')) {
            $lat = (float) $request->query('lat');
            $lng = (float) $request->query('lng');
            $request->session()->put('user_lat', $lat);
            $request->session()->put('user_lng', $lng);
        }

        $categorySlug = $request->query('category');
        $search = $request->query('q');

        $nearbyStores = $this->quickService->getNearbyQuickStores($lat, $lng);
        $products = $this->quickService->getQuickEligibleProducts($lat, $lng, $categorySlug, $search);

        $categories = Category::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->take(8)
            ->get();

        return view('storefront.quick.index', [
            'lat' => $lat,
            'lng' => $lng,
            'nearbyStores' => $nearbyStores,
            'products' => $products,
            'categories' => $categories,
            'selectedCategory' => $categorySlug,
            'searchQuery' => $search,
        ]);
    }

    /**
     * API: Nearby Quick Commerce Stores with dynamic ETA.
     */
    public function nearbyStoresApi(Request $request): JsonResponse
    {
        $lat = (float) $request->query('lat', setting('default_lat', 12.9716));
        $lng = (float) $request->query('lng', setting('default_lng', 77.5946));
        $radius = $request->has('radius') ? (float) $request->query('radius') : null;

        $stores = $this->quickService->getNearbyQuickStores($lat, $lng, $radius);

        return response()->json([
            'status' => 'success',
            'count' => $stores->count(),
            'data' => $stores,
        ]);
    }

    /**
     * Live Customer Order Tracking Page (/orders/{order}/live-tracking).
     */
    public function liveTracking(Request $request, Order $order): View
    {
        // Must belong to authenticated customer or matching session
        if (auth()->check()) {
            if ($order->customer?->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
                abort(403, 'Unauthorized.');
            }
        }

        $order->load([
            'fulfilmentOrders.store.location',
            'fulfilmentOrders.deliveryAssignment.deliveryPartner',
            'fulfilmentOrders.items',
            'customerAddress',
        ]);

        $quickFulfilment = $order->fulfilmentOrders->firstWhere('fulfilment_type', \App\Enums\FulfilmentType::QUICK)
            ?? $order->fulfilmentOrders->first();

        return view('storefront.orders.live-tracking', [
            'order' => $order,
            'fulfilmentOrder' => $quickFulfilment,
        ]);
    }

    /**
     * API: Real-time Telemetry & Order Status for Map Live Tracking.
     */
    public function liveLocationApi(Request $request, Order $order): JsonResponse
    {
        $quickFulfilment = $order->fulfilmentOrders()
            ->with(['store.location', 'deliveryAssignment.deliveryPartner'])
            ->where('fulfilment_type', \App\Enums\FulfilmentType::QUICK)
            ->first() ?? $order->fulfilmentOrders()->with(['store.location', 'deliveryAssignment.deliveryPartner'])->first();

        if (!$quickFulfilment) {
            return response()->json(['error' => 'No active fulfilment package found'], 404);
        }

        $store = $quickFulfilment->store;
        $storeLoc = $store?->location;
        $assignment = $quickFulfilment->deliveryAssignment;
        $rider = $assignment?->deliveryPartner;
        $address = $order->customerAddress;

        return response()->json([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'package_status' => $quickFulfilment->status,
            'delivery_otp' => $quickFulfilment->delivery_otp,
            'estimated_delivery_at' => $quickFulfilment->estimated_delivery_at?->toIso8601String(),
            'store' => [
                'name' => $store?->name ?? 'OneStall Store Hub',
                'lat' => $storeLoc?->lat ? (float) $storeLoc->lat : 12.9716,
                'lng' => $storeLoc?->lng ? (float) $storeLoc->lng : 77.5946,
                'address' => $storeLoc?->address ?? '',
            ],
            'customer' => [
                'lat' => $address?->lat ? (float) $address->lat : 12.9352,
                'lng' => $address?->lng ? (float) $address->lng : 77.6245,
                'address' => $address?->line1 ?? '',
            ],
            'rider' => $rider ? [
                'id' => $rider->id,
                'name' => $rider->name,
                'phone' => $rider->phone,
                'vehicle' => $rider->vehicle ?? 'Motorcycle',
                'vehicle_number' => $rider->vehicle_number ?? 'KA-01-EQ-1234',
                'lat' => $rider->current_lat ? (float) $rider->current_lat : (float) ($storeLoc?->lat ?? 12.9716),
                'lng' => $rider->current_lng ? (float) $rider->current_lng : (float) ($storeLoc?->lng ?? 77.5946),
                'assignment_status' => $assignment->status->value,
            ] : null,
        ]);
    }
}
