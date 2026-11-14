<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Actions\Order\PlaceOrderAction;
use App\Enums\AddressType;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\StoreLocation;
use App\Services\Fulfilment\FulfilmentService;
use App\Services\Storefront\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected FulfilmentService $fulfilmentService,
        protected PlaceOrderAction $placeOrderAction
    ) {}

    /**
     * Single-Page Checkout Screen (/checkout).
     */
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->guest(route('login'))->with('notice', 'Please sign in to proceed with checkout.');
        }

        $customer = $user->customer ?: Customer::firstOrCreate(['user_id' => $user->id]);
        $sessionId = $request->session()->getId();
        $cart = $this->cartService->getActiveCart($user, $sessionId);

        // Ensure cart has active items
        if ($cart->activeItems->isEmpty()) {
            return redirect()->route('cart.index')->with('notice', 'Your cart is empty. Add items before checking out.');
        }

        // Addresses
        $addresses = $customer->addresses()->latest('is_default')->latest('id')->get();
        $selectedAddressId = (int) $request->query('address_id', $addresses->firstWhere('is_default', true)?->id ?? $addresses->first()?->id ?? 0);
        $selectedAddress = $addresses->firstWhere('id', $selectedAddressId) ?? $addresses->first();

        // Fulfilment Plan
        $location = $selectedAddress ? [
            'lat' => $selectedAddress->lat,
            'lng' => $selectedAddress->lng,
            'city' => $selectedAddress->city,
            'state' => $selectedAddress->state,
            'pincode' => $selectedAddress->pincode,
        ] : [
            'lat' => (float) setting('default_lat', 12.9716),
            'lng' => (float) setting('default_lng', 77.5946),
            'city' => 'Bengaluru',
            'pincode' => '560001',
        ];

        $fulfilmentPlan = $this->fulfilmentService->createPlan($cart, $location);
        $summary = $this->cartService->getSummary($cart, $user);

        // Adjust summary delivery fee with plan's computed delivery fee
        $summary['delivery_fee'] = $fulfilmentPlan->totalDeliveryFee;
        $summary['formatted_delivery_fee'] = $fulfilmentPlan->totalDeliveryFee == 0 ? 'FREE' : format_currency($fulfilmentPlan->totalDeliveryFee);
        $summary['total_payable'] = max(0.0, round($summary['subtotal'] - $summary['coupon_discount'] + $fulfilmentPlan->totalDeliveryFee, 2));
        $summary['formatted_total_payable'] = format_currency($summary['total_payable']);

        // Check COD Eligibility
        $codEligible = true;
        $codReason = null;
        $maxCodAmount = (float) setting('max_cod_amount', 5000.00);

        if (!(bool) setting('cod_enabled', true)) {
            $codEligible = false;
            $codReason = 'Cash on Delivery is currently disabled by platform.';
        } elseif ($summary['total_payable'] > $maxCodAmount) {
            $codEligible = false;
            $codReason = 'Order total of ' . format_currency($summary['total_payable']) . ' exceeds the maximum COD limit of ' . format_currency($maxCodAmount) . '.';
        } else {
            foreach ($cart->activeItems as $item) {
                if (!$item->variant?->product?->is_cod_allowed) {
                    $codEligible = false;
                    $codReason = "Item '{$item->variant?->product?->title}' is not eligible for Cash on Delivery.";
                    break;
                }
            }
        }

        return view('storefront.checkout', [
            'customer' => $customer,
            'cart' => $cart,
            'summary' => $summary,
            'addresses' => $addresses,
            'selectedAddress' => $selectedAddress,
            'fulfilmentPlan' => $fulfilmentPlan,
            'codEligible' => $codEligible,
            'codReason' => $codReason,
            'maxCodAmount' => $maxCodAmount,
        ]);
    }

    /**
     * Add new delivery address from checkout modal.
     */
    public function addAddress(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $customer = $user->customer ?: Customer::firstOrCreate(['user_id' => $user->id]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'type' => ['nullable', 'string', 'in:home,work,other'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'pincode' => ['required', 'string', 'regex:/^[1-9][0-9]{5}$/'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if (!empty($validated['is_default'])) {
            $customer->addresses()->update(['is_default' => false]);
        }

        $address = CustomerAddress::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'type' => $validated['type'] ?? AddressType::HOME->value,
            'line1' => $validated['line1'],
            'line2' => $validated['line2'] ?? null,
            'landmark' => $validated['landmark'] ?? null,
            'city' => $validated['city'],
            'state' => $validated['state'],
            'pincode' => $validated['pincode'],
            'lat' => !empty($validated['lat']) ? (float) $validated['lat'] : null,
            'lng' => !empty($validated['lng']) ? (float) $validated['lng'] : null,
            'is_default' => !empty($validated['is_default']),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Delivery address added successfully',
                'address' => $address,
            ]);
        }

        return redirect()->route('checkout.index', ['address_id' => $address->id])->with('success', 'Address added successfully.');
    }

    /**
     * Place order action.
     */
    public function placeOrder(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $customer = $user->customer ?: Customer::firstOrCreate(['user_id' => $user->id]);
        $sessionId = $request->session()->getId();
        $cart = $this->cartService->getActiveCart($user, $sessionId);

        $validated = $request->validate([
            'address_id' => ['required', 'integer', 'exists:customer_addresses,id'],
            'payment_method' => ['required', 'string', 'in:online,cod,upi,card,netbanking'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $address = CustomerAddress::where('customer_id', $customer->id)
            ->where('id', $validated['address_id'])
            ->firstOrFail();

        try {
            $order = $this->placeOrderAction->execute(
                customer: $customer,
                address: $address,
                cart: $cart,
                paymentMethod: $validated['payment_method'],
                idempotencyKey: $validated['idempotency_key'],
                notes: $validated['notes'] ?? null
            );

            $targetUrl = strtolower($validated['payment_method']) === 'cod'
                ? route('checkout.success', $order->order_number)
                : route('payment.pay', $order->order_number);

            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'success',
                    'order_number' => $order->order_number,
                    'redirect_url' => $targetUrl,
                ]);
            }

            return redirect($targetUrl);
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Order confirmation & tracking screen.
     */
    public function success(Request $request, string $orderNumber): View
    {
        $order = Order::where('order_number', $orderNumber)
            ->with(['fulfilmentOrders.items.variant.product', 'fulfilmentOrders.seller', 'customer.user'])
            ->firstOrFail();

        return view('storefront.order-success', [
            'order' => $order,
        ]);
    }

    /**
     * Rapid lookup of city, state, and district by 6-digit Indian PIN code.
     */
    public function lookupPincode(string $pincode): JsonResponse
    {
        $pincode = trim($pincode);
        if (!preg_match('/^[1-9][0-9]{5}$/', $pincode)) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid 6-digit PIN code.',
            ], 422);
        }

        // 1. Check local StoreLocation
        $localStore = StoreLocation::where('pincode', $pincode)->first();
        if ($localStore && $localStore->city && $localStore->state) {
            return response()->json([
                'success' => true,
                'city' => $localStore->city,
                'state' => $localStore->state,
                'district' => $localStore->city,
                'source' => 'store_hub',
            ]);
        }

        // 2. Check existing customer addresses
        $localAddr = CustomerAddress::where('pincode', $pincode)->first();
        if ($localAddr && $localAddr->city && $localAddr->state) {
            return response()->json([
                'success' => true,
                'city' => $localAddr->city,
                'state' => $localAddr->state,
                'district' => $localAddr->city,
                'source' => 'verified_history',
            ]);
        }

        // 3. Cache lookup
        $cacheKey = 'pincode_lookup_' . $pincode;
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return response()->json($cached);
        }

        // 4. Server-side query to Postal PIN code API (No CORS, 2s fast timeout)
        try {
            $resp = Http::timeout(2)->get("https://api.postalpincode.in/pincode/{$pincode}");
            if ($resp->successful()) {
                $data = $resp->json();
                if (!empty($data[0]) && ($data[0]['Status'] ?? '') === 'Success' && !empty($data[0]['PostOffice'][0])) {
                    $po = $data[0]['PostOffice'][0];
                    $city = $po['District'] ?? $po['Name'] ?? null;
                    $state = $po['State'] ?? null;
                    $district = $po['District'] ?? $city;

                    if ($city && $state) {
                        $payload = [
                            'success' => true,
                            'city' => trim((string)$city),
                            'state' => trim((string)$state),
                            'district' => trim((string)$district),
                            'source' => 'postal_network',
                        ];
                        Cache::put($cacheKey, $payload, now()->addDays(30));
                        return response()->json($payload);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Log or ignore and fall through to dictionary
        }

        // 5. Intelligent Indian Postal Circle & District mapping
        $prefix3 = substr($pincode, 0, 3);
        $prefix2 = substr($pincode, 0, 2);

        $specificMap = [
            '110' => ['city' => 'Delhi', 'state' => 'Delhi'],
            '201' => ['city' => 'Noida', 'state' => 'Uttar Pradesh'],
            '202' => ['city' => 'Aligarh', 'state' => 'Uttar Pradesh'],
            '208' => ['city' => 'Kanpur', 'state' => 'Uttar Pradesh'],
            '221' => ['city' => 'Varanasi', 'state' => 'Uttar Pradesh'],
            '223' => ['city' => 'Noida', 'state' => 'Uttar Pradesh'],
            '226' => ['city' => 'Lucknow', 'state' => 'Uttar Pradesh'],
            '248' => ['city' => 'Dehradun', 'state' => 'Uttarakhand'],
            '302' => ['city' => 'Jaipur', 'state' => 'Rajasthan'],
            '380' => ['city' => 'Ahmedabad', 'state' => 'Gujarat'],
            '395' => ['city' => 'Surat', 'state' => 'Gujarat'],
            '400' => ['city' => 'Mumbai', 'state' => 'Maharashtra'],
            '403' => ['city' => 'Goa', 'state' => 'Goa'],
            '411' => ['city' => 'Pune', 'state' => 'Maharashtra'],
            '452' => ['city' => 'Indore', 'state' => 'Madhya Pradesh'],
            '462' => ['city' => 'Bhopal', 'state' => 'Madhya Pradesh'],
            '500' => ['city' => 'Hyderabad', 'state' => 'Telangana'],
            '520' => ['city' => 'Vijayawada', 'state' => 'Andhra Pradesh'],
            '530' => ['city' => 'Visakhapatnam', 'state' => 'Andhra Pradesh'],
            '560' => ['city' => 'Bengaluru', 'state' => 'Karnataka'],
            '570' => ['city' => 'Mysuru', 'state' => 'Karnataka'],
            '600' => ['city' => 'Chennai', 'state' => 'Tamil Nadu'],
            '641' => ['city' => 'Coimbatore', 'state' => 'Tamil Nadu'],
            '682' => ['city' => 'Kochi', 'state' => 'Kerala'],
            '695' => ['city' => 'Thiruvananthapuram', 'state' => 'Kerala'],
            '700' => ['city' => 'Kolkata', 'state' => 'West Bengal'],
            '751' => ['city' => 'Bhubaneswar', 'state' => 'Odisha'],
            '781' => ['city' => 'Guwahati', 'state' => 'Assam'],
            '800' => ['city' => 'Patna', 'state' => 'Bihar'],
            '834' => ['city' => 'Ranchi', 'state' => 'Jharkhand'],
        ];

        if (isset($specificMap[$prefix3])) {
            $m = $specificMap[$prefix3];
            return response()->json([
                'success' => true,
                'city' => $m['city'],
                'state' => $m['state'],
                'district' => $m['city'],
                'source' => 'regional_dispatch',
            ]);
        }

        $regionMap = [
            '11' => ['city' => 'Delhi', 'state' => 'Delhi'],
            '12' => ['city' => 'Gurugram', 'state' => 'Haryana'],
            '13' => ['city' => 'Ambala', 'state' => 'Haryana'],
            '14' => ['city' => 'Ludhiana', 'state' => 'Punjab'],
            '15' => ['city' => 'Bathinda', 'state' => 'Punjab'],
            '16' => ['city' => 'Chandigarh', 'state' => 'Chandigarh'],
            '17' => ['city' => 'Shimla', 'state' => 'Himachal Pradesh'],
            '18' => ['city' => 'Jammu', 'state' => 'Jammu & Kashmir'],
            '19' => ['city' => 'Srinagar', 'state' => 'Jammu & Kashmir'],
            '20' => ['city' => 'Ghaziabad', 'state' => 'Uttar Pradesh'],
            '21' => ['city' => 'Prayagraj', 'state' => 'Uttar Pradesh'],
            '22' => ['city' => 'Lucknow', 'state' => 'Uttar Pradesh'],
            '23' => ['city' => 'Mirzapur', 'state' => 'Uttar Pradesh'],
            '24' => ['city' => 'Bareilly', 'state' => 'Uttar Pradesh'],
            '25' => ['city' => 'Meerut', 'state' => 'Uttar Pradesh'],
            '26' => ['city' => 'Pilibhit', 'state' => 'Uttar Pradesh'],
            '27' => ['city' => 'Gorakhpur', 'state' => 'Uttar Pradesh'],
            '28' => ['city' => 'Jhansi', 'state' => 'Uttar Pradesh'],
            '30' => ['city' => 'Jaipur', 'state' => 'Rajasthan'],
            '31' => ['city' => 'Udaipur', 'state' => 'Rajasthan'],
            '32' => ['city' => 'Kota', 'state' => 'Rajasthan'],
            '33' => ['city' => 'Bikaner', 'state' => 'Rajasthan'],
            '34' => ['city' => 'Jodhpur', 'state' => 'Rajasthan'],
            '36' => ['city' => 'Rajkot', 'state' => 'Gujarat'],
            '37' => ['city' => 'Kutch', 'state' => 'Gujarat'],
            '38' => ['city' => 'Ahmedabad', 'state' => 'Gujarat'],
            '39' => ['city' => 'Surat', 'state' => 'Gujarat'],
            '40' => ['city' => 'Mumbai', 'state' => 'Maharashtra'],
            '41' => ['city' => 'Pune', 'state' => 'Maharashtra'],
            '42' => ['city' => 'Nashik', 'state' => 'Maharashtra'],
            '43' => ['city' => 'Aurangabad', 'state' => 'Maharashtra'],
            '44' => ['city' => 'Nagpur', 'state' => 'Maharashtra'],
            '45' => ['city' => 'Indore', 'state' => 'Madhya Pradesh'],
            '46' => ['city' => 'Bhopal', 'state' => 'Madhya Pradesh'],
            '47' => ['city' => 'Gwalior', 'state' => 'Madhya Pradesh'],
            '48' => ['city' => 'Jabalpur', 'state' => 'Madhya Pradesh'],
            '49' => ['city' => 'Raipur', 'state' => 'Chhattisgarh'],
            '50' => ['city' => 'Hyderabad', 'state' => 'Telangana'],
            '51' => ['city' => 'Kurnool', 'state' => 'Andhra Pradesh'],
            '52' => ['city' => 'Vijayawada', 'state' => 'Andhra Pradesh'],
            '53' => ['city' => 'Visakhapatnam', 'state' => 'Andhra Pradesh'],
            '56' => ['city' => 'Bengaluru', 'state' => 'Karnataka'],
            '57' => ['city' => 'Mangalore', 'state' => 'Karnataka'],
            '58' => ['city' => 'Hubballi', 'state' => 'Karnataka'],
            '59' => ['city' => 'Belagavi', 'state' => 'Karnataka'],
            '60' => ['city' => 'Chennai', 'state' => 'Tamil Nadu'],
            '61' => ['city' => 'Thanjavur', 'state' => 'Tamil Nadu'],
            '62' => ['city' => 'Madurai', 'state' => 'Tamil Nadu'],
            '63' => ['city' => 'Vellore', 'state' => 'Tamil Nadu'],
            '64' => ['city' => 'Coimbatore', 'state' => 'Tamil Nadu'],
            '67' => ['city' => 'Kozhikode', 'state' => 'Kerala'],
            '68' => ['city' => 'Kochi', 'state' => 'Kerala'],
            '69' => ['city' => 'Thiruvananthapuram', 'state' => 'Kerala'],
            '70' => ['city' => 'Kolkata', 'state' => 'West Bengal'],
            '71' => ['city' => 'Howrah', 'state' => 'West Bengal'],
            '72' => ['city' => 'Midnapore', 'state' => 'West Bengal'],
            '73' => ['city' => 'Siliguri', 'state' => 'West Bengal'],
            '74' => ['city' => 'Nadia', 'state' => 'West Bengal'],
            '75' => ['city' => 'Bhubaneswar', 'state' => 'Odisha'],
            '76' => ['city' => 'Cuttack', 'state' => 'Odisha'],
            '77' => ['city' => 'Rourkela', 'state' => 'Odisha'],
            '78' => ['city' => 'Guwahati', 'state' => 'Assam'],
            '79' => ['city' => 'Shillong', 'state' => 'Meghalaya'],
            '80' => ['city' => 'Patna', 'state' => 'Bihar'],
            '81' => ['city' => 'Bhagalpur', 'state' => 'Bihar'],
            '82' => ['city' => 'Gaya', 'state' => 'Bihar'],
            '83' => ['city' => 'Ranchi', 'state' => 'Jharkhand'],
            '84' => ['city' => 'Muzaffarpur', 'state' => 'Bihar'],
            '85' => ['city' => 'Purnia', 'state' => 'Bihar'],
        ];

        if (isset($regionMap[$prefix2])) {
            $m = $regionMap[$prefix2];
            return response()->json([
                'success' => true,
                'city' => $m['city'],
                'state' => $m['state'],
                'district' => $m['city'],
                'source' => 'circle_dispatch',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'City could not be detected. Please enter manually.',
        ], 404);
    }
}
