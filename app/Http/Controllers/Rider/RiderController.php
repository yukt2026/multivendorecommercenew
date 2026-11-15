<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rider;

use App\Enums\DeliveryAssignmentStatus;
use App\Enums\FulfilmentType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDocument;
use App\Models\DeliveryPartner;
use App\Models\FulfilmentOrder;
use App\Models\RiderEarning;
use App\Models\RiderLocationHistory;
use App\Models\RiderWallet;
use App\Models\RiderWalletTransaction;
use App\Models\User;
use App\Services\Order\OrderStateMachine;
use App\Services\QuickCommerce\QuickCommerceService;
use App\Services\Rider\RiderEarningsService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RiderController extends Controller
{
    public function __construct(
        protected QuickCommerceService $quickService,
        protected OrderStateMachine $stateMachine,
        protected RiderEarningsService $earningsService
    ) {}

    /**
     * Show rider login screen.
     */
    public function loginForm(): View|RedirectResponse
    {
        if (session('active_rider_id')) {
            $partner = DeliveryPartner::find(session('active_rider_id'));
            if ($partner) {
                return redirect()->route('rider.dashboard');
            }
        }

        if (auth()->check() && auth()->user()->hasRole('delivery_staff')) {
            return redirect()->route('rider.dashboard');
        }

        return view('rider.login');
    }

    /**
     * Process rider authentication.
     */
    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = $validated['login'];
        $password = $validated['password'];

        // Determine if login is phone or email
        $fieldType = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        // Check user credentials
        $user = User::where($fieldType, $login)->first();

        if ($user && Hash::check($password, $user->password)) {
            Auth::login($user, $request->boolean('remember'));

            $partner = DeliveryPartner::where('user_id', $user->id)
                ->orWhere('phone', $user->phone)
                ->first();

            if ($partner) {
                session(['active_rider_id' => $partner->id]);
                return redirect()->route('rider.dashboard')->with('success', "Welcome back, {$partner->name}!");
            }
        }

        // Direct rider phone login check
        $directRider = DeliveryPartner::where('phone', $login)->first();
        if ($directRider) {
            session(['active_rider_id' => $directRider->id]);
            return redirect()->route('rider.dashboard')->with('success', "Welcome back, {$directRider->name}!");
        }

        return back()->withInput()->with('error', 'Invalid login credentials. Please verify your phone/email and password.');
    }

    /**
     * Show rider self-registration screen.
     */
    public function registerForm(): View|RedirectResponse
    {
        if (session('active_rider_id') || (auth()->check() && auth()->user()->hasRole('delivery_staff'))) {
            return redirect()->route('rider.dashboard');
        }

        return view('rider.register');
    }

    /**
     * Handle rider self-registration.
     */
    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20', 'unique:delivery_partners,phone', 'unique:users,phone'],
            'email' => ['nullable', 'string', 'email', 'max:100', 'unique:users,email'],
            'vehicle' => ['required', 'string', 'max:50'],
            'vehicle_number' => ['nullable', 'string', 'max:30'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'terms' => ['accepted'],
        ]);

        $cleanPhone = preg_replace('/[^0-9]/', '', $validated['phone']);
        $email = $validated['email'] ?? ('rider_' . ($cleanPhone ?: uniqid()) . '@onestall.internal');

        $user = User::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $email,
            'password' => Hash::make($validated['password']),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        Role::firstOrCreate(['name' => 'delivery_staff', 'guard_name' => 'web']);
        $user->assignRole('delivery_staff');

        $partner = DeliveryPartner::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'vehicle' => $validated['vehicle'],
            'vehicle_number' => $validated['vehicle_number'] ?? null,
            'current_lat' => 12.9716,
            'current_lng' => 77.5946,
            'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
            'is_active' => false,
            'status' => 'offline',
            'approval_status' => 'pending',
            'active_orders_count' => 0,
            'last_location_updated_at' => now(),
        ]);

        $partner->ensureWallet();

        // Auto login newly registered partner
        Auth::login($user);
        session(['active_rider_id' => $partner->id]);

        return redirect()->route('rider.kyc')->with('success', 'Registration successful! Please upload your KYC documents to get approved for order assignments.');
    }

    /**
     * Log out active rider.
     */
    public function logout(Request $request): RedirectResponse
    {
        session()->forget('active_rider_id');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('rider.login')->with('success', 'Logged out successfully.');
    }

    /**
     * Rider Mobile-Friendly Web Dashboard (/rider).
     */
    public function dashboard(Request $request): View
    {
        $rider = $this->resolveRider($request);

        $activeAssignments = DeliveryAssignment::query()
            ->with([
                'fulfilmentOrder.store.location',
                'fulfilmentOrder.order.customerAddress',
                'fulfilmentOrder.items.variant.product',
            ])
            ->where('delivery_partner_id', $rider->id)
            ->whereIn('status', [
                DeliveryAssignmentStatus::ASSIGNED,
                DeliveryAssignmentStatus::ACCEPTED,
                DeliveryAssignmentStatus::PICKED_UP,
            ])
            ->latest('id')
            ->get();

        $completedToday = DeliveryAssignment::query()
            ->where('delivery_partner_id', $rider->id)
            ->where('status', DeliveryAssignmentStatus::DELIVERED)
            ->whereDate('delivered_at', now()->toDateString())
            ->count();

        $todayEarnings = (float) RiderEarning::query()
            ->where('delivery_partner_id', $rider->id)
            ->whereDate('created_at', now()->toDateString())
            ->sum('total_payout');

        $wallet = $rider->ensureWallet();

        return view('rider.index', [
            'rider' => $rider,
            'activeAssignments' => $activeAssignments,
            'completedToday' => $completedToday,
            'todayEarnings' => $todayEarnings,
            'wallet' => $wallet,
        ]);
    }

    /**
     * Toggle duty status between online (available) and offline.
     */
    public function toggleDuty(Request $request): JsonResponse|RedirectResponse
    {
        $rider = $this->resolveRider($request);

        if ($rider->status === 'busy' && $rider->active_orders_count > 0) {
            $msg = 'You cannot go offline while you have an active package assigned.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->with('error', $msg);
        }

        $newStatus = ($rider->status === 'available') ? 'offline' : 'available';
        $rider->status = $newStatus;
        $rider->is_active = ($newStatus !== 'offline');
        $rider->save();

        $statusText = $newStatus === 'available' ? 'You are now Online & ready for orders!' : 'You are now Offline.';
        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'status' => $newStatus, 'message' => $statusText]);
        }

        return back()->with('success', $statusText);
    }

    /**
     * Detailed mobile assignment view (/rider/orders/{assignment}).
     */
    public function showAssignment(Request $request, DeliveryAssignment $assignment): View
    {
        $rider = $this->resolveRider($request);

        $assignment->load([
            'fulfilmentOrder.store.location',
            'fulfilmentOrder.order.customerAddress',
            'fulfilmentOrder.order.payments',
            'fulfilmentOrder.items.variant.product',
            'deliveryPartner',
            'earnings',
        ]);

        $fo = $assignment->fulfilmentOrder;
        $order = $fo?->order;
        $isCod = $order?->payment_method === PaymentMethod::COD;
        $codAmount = $isCod ? (float) ($fo?->total ?? $order?->total ?? 0) : 0.00;

        $snapshot = $order?->address_snapshot;
        $customerAddress = $order?->customerAddress ?: (is_array($snapshot) ? (object) $snapshot : null);

        return view('rider.assignment', [
            'rider' => $rider,
            'assignment' => $assignment,
            'fulfilmentOrder' => $fo,
            'store' => $fo?->store,
            'customerAddress' => $customerAddress,
            'isCod' => $isCod,
            'codAmount' => $codAmount,
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

        if ($assignment->deliveryPartner) {
            $assignment->deliveryPartner->status = 'busy';
            $assignment->deliveryPartner->save();
        }

        $fo = $assignment->fulfilmentOrder;
        if ($fo && $this->stateMachine->canTransitionFulfilment($fo, 'accepted')) {
            $this->stateMachine->transitionFulfilmentOrder(
                $fo,
                'accepted',
                'Delivery partner accepted the task and is heading to store',
                auth()->id()
            );
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Assignment accepted! Navigate to store for pickup.']);
        }

        return back()->with('success', 'Assignment accepted! Navigate to store for pickup.');
    }

    /**
     * Reject assigned delivery job with reason.
     */
    public function reject(Request $request, DeliveryAssignment $assignment): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->quickService->reassignOnReject($assignment, $validated['reason']);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Assignment declined. Order has been returned to dispatch pool.']);
        }

        return redirect()->route('rider.dashboard')->with('success', 'Assignment declined. Order has been returned to dispatch pool.');
    }

    /**
     * Mark order picked up from store (starts out_for_delivery).
     */
    public function pickup(Request $request, DeliveryAssignment $assignment): JsonResponse|RedirectResponse
    {
        // Optional verification if store pickup OTP was issued
        if ($assignment->pickup_otp) {
            $inputOtp = trim((string) $request->input('pickup_otp', ''));
            if ($inputOtp !== '' && $inputOtp !== trim((string) $assignment->pickup_otp)) {
                $err = 'Invalid Store Pickup OTP. Please request the dark store manager for the 4-digit code.';
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $err], 422);
                }
                return back()->with('error', $err);
            }
            $assignment->pickup_otp_verified = true;
        }

        $assignment->status = DeliveryAssignmentStatus::PICKED_UP;
        $assignment->picked_up_at = now();
        $assignment->save();

        $fo = $assignment->fulfilmentOrder;
        if ($fo) {
            if ($this->stateMachine->canTransitionFulfilment($fo, 'picked_up')) {
                $this->stateMachine->transitionFulfilmentOrder($fo, 'picked_up', 'Rider picked up package from store', auth()->id());
            }
            if ($this->stateMachine->canTransitionFulfilment($fo, 'out_for_delivery')) {
                $this->stateMachine->transitionFulfilmentOrder($fo, 'out_for_delivery', 'Order is out for delivery with rider', auth()->id());
            }
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Order picked up! Navigate to customer drop-off location.']);
        }

        return back()->with('success', 'Order picked up! Navigate to customer drop-off location.');
    }

    /**
     * Complete delivery with Customer OTP handshake verification & optional Proof of Delivery.
     */
    public function deliver(Request $request, DeliveryAssignment $assignment): JsonResponse|RedirectResponse
    {
        $request->validate([
            'otp' => ['required', 'string', 'min:4', 'max:8'],
            'proof_image' => ['nullable', 'image', 'max:5120'], // max 5MB
        ]);

        $otp = $request->input('otp');
        $fo = $assignment->fulfilmentOrder;

        if (!$fo) {
            abort(404, 'Fulfilment order not found.');
        }

        // Store Proof of Delivery Image if uploaded
        if ($request->hasFile('proof_image')) {
            $path = $request->file('proof_image')->store('delivery_proofs', 'public');
            $assignment->proof_of_delivery_image = $path;
            $assignment->save();
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

        return redirect()->route('rider.dashboard')->with('success', 'Order delivered successfully! Payout credited to your wallet.');
    }

    /**
     * Report failed delivery attempt (e.g. customer unreachable, premises closed).
     */
    public function failDelivery(Request $request, DeliveryAssignment $assignment): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->quickService->handleDeliveryFailure($assignment, $validated['reason'], $validated['notes'] ?? null);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Delivery attempt marked failed. Please return the package to the dark store.']);
        }

        return redirect()->route('rider.dashboard')->with('warning', 'Delivery attempt marked failed. Please return the package to the dark store.');
    }

    /**
     * Confirm failed package returned back to merchant dark store.
     */
    public function confirmReturn(Request $request, DeliveryAssignment $assignment): RedirectResponse|JsonResponse
    {
        $fo = $assignment->fulfilmentOrder;

        if ($fo && $this->stateMachine->canTransitionFulfilment($fo, 'returned_to_store')) {
            $this->stateMachine->transitionFulfilmentOrder(
                $fo,
                'returned_to_store',
                'Package returned to store by delivery partner #' . $assignment->delivery_partner_id,
                auth()->id()
            );
        }

        $msg = 'Package safely returned to store. Job completed.';
        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return redirect()->route('rider.dashboard')->with('success', $msg);
    }

    /**
     * Delivery History Screen (/rider/history).
     */
    public function history(Request $request): View
    {
        $rider = $this->resolveRider($request);

        $filter = $request->query('status', 'all');

        $query = DeliveryAssignment::query()
            ->with([
                'fulfilmentOrder.store',
                'fulfilmentOrder.order.customerAddress',
                'earnings',
            ])
            ->where('delivery_partner_id', $rider->id)
            ->whereIn('status', [
                DeliveryAssignmentStatus::DELIVERED,
                DeliveryAssignmentStatus::CANCELLED,
                DeliveryAssignmentStatus::REJECTED,
            ]);

        if ($filter === 'delivered') {
            $query->where('status', DeliveryAssignmentStatus::DELIVERED);
        } elseif ($filter === 'failed') {
            $query->whereIn('status', [DeliveryAssignmentStatus::CANCELLED, DeliveryAssignmentStatus::REJECTED]);
        }

        $historyAssignments = $query->latest('id')->paginate(15)->withQueryString();

        $metrics = [
            'total_completed' => DeliveryAssignment::where('delivery_partner_id', $rider->id)->where('status', DeliveryAssignmentStatus::DELIVERED)->count(),
            'total_earned' => (float) RiderEarning::where('delivery_partner_id', $rider->id)->sum('total_payout'),
            'total_distance' => (float) RiderEarning::where('delivery_partner_id', $rider->id)->sum('distance_km'),
        ];

        return view('rider.history', [
            'rider' => $rider,
            'assignments' => $historyAssignments,
            'metrics' => $metrics,
            'filter' => $filter,
        ]);
    }

    /**
     * Rider Wallet & Financial Ledger Screen (/rider/wallet).
     */
    public function wallet(Request $request): View
    {
        $rider = $this->resolveRider($request);
        $wallet = $rider->ensureWallet();

        $transactions = RiderWalletTransaction::query()
            ->where('rider_wallet_id', $wallet->id)
            ->latest('id')
            ->paginate(15);

        $recentEarnings = RiderEarning::query()
            ->with('fulfilmentOrder')
            ->where('delivery_partner_id', $rider->id)
            ->latest('id')
            ->limit(5)
            ->get();

        return view('rider.wallet', [
            'rider' => $rider,
            'wallet' => $wallet,
            'transactions' => $transactions,
            'recentEarnings' => $recentEarnings,
        ]);
    }

    /**
     * Rider Profile & KYC Details Screen (/rider/profile).
     */
    public function profile(Request $request): View
    {
        $rider = $this->resolveRider($request);
        $rider->load(['documents', 'wallet']);

        return view('rider.profile', [
            'rider' => $rider,
            'user' => $rider->user,
        ]);
    }

    /**
     * Update Rider Profile.
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $rider = $this->resolveRider($request);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'vehicle' => ['nullable', 'string', 'max:50'],
            'vehicle_number' => ['nullable', 'string', 'max:30'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
        ]);

        $rider->update([
            'name' => $validated['name'] ?? $rider->name,
            'vehicle' => $validated['vehicle'] ?? $rider->vehicle,
            'vehicle_number' => array_key_exists('vehicle_number', $validated) ? $validated['vehicle_number'] : $rider->vehicle_number,
            'emergency_contact_name' => array_key_exists('emergency_contact_name', $validated) ? $validated['emergency_contact_name'] : $rider->emergency_contact_name,
            'emergency_contact_phone' => array_key_exists('emergency_contact_phone', $validated) ? $validated['emergency_contact_phone'] : $rider->emergency_contact_phone,
        ]);

        if (! empty($validated['email']) && $rider->user) {
            $rider->user->update([
                'name' => $validated['name'] ?? $rider->user->name,
                'email' => $validated['email'],
            ]);
        }

        return back()->with('success', 'Profile details updated successfully.');
    }

    /**
     * Upload & change rider profile photo.
     */
    public function updateAvatar(Request $request): RedirectResponse|JsonResponse
    {
        $rider = $this->resolveRider($request);

        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ]);

        if ($rider->avatar && Storage::disk('public')->exists($rider->avatar)) {
            Storage::disk('public')->delete($rider->avatar);
        }

        $path = $request->file('avatar')->store('riders/avatars', 'public');
        $rider->update(['avatar' => $path]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Profile photo updated successfully.',
                'avatar_url' => $rider->avatar_url,
            ]);
        }

        return back()->with('success', 'Profile photo updated successfully.');
    }

    /**
     * Remove rider profile photo.
     */
    public function removeAvatar(Request $request): RedirectResponse|JsonResponse
    {
        $rider = $this->resolveRider($request);

        if ($rider->avatar && Storage::disk('public')->exists($rider->avatar)) {
            Storage::disk('public')->delete($rider->avatar);
        }

        $rider->update(['avatar' => null]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Profile photo removed successfully.',
            ]);
        }

        return back()->with('success', 'Profile photo removed successfully.');
    }

    /**
     * Change Rider Password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $rider = $this->resolveRider($request);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = $rider->user;
        if (! $user) {
            $user = User::firstOrCreate(
                ['phone' => $rider->phone],
                [
                    'name' => $rider->name,
                    'email' => 'rider_' . $rider->id . '@onestall.internal',
                    'password' => Hash::make($validated['current_password']),
                    'status' => 'active',
                ]
            );
            if (! $user->hasRole('delivery_staff')) {
                Role::firstOrCreate(['name' => 'delivery_staff', 'guard_name' => 'web']);
                $user->assignRole('delivery_staff');
            }
            $rider->update(['user_id' => $user->id]);
        }

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Current password does not match our records.']);
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        return back()->with('success', 'Password updated successfully.');
    }

    /**
     * Show rider KYC management screen.
     */
    public function kyc(): View
    {
        $rider = $this->resolveRider();
        $rider->load('documents');

        $uploadedDocs = $rider->documents->keyBy('document_type');

        return view('rider.kyc', [
            'rider' => $rider,
            'uploadedDocs' => $uploadedDocs,
            'documentTypes' => DeliveryDocument::TYPES,
        ]);
    }

    /**
     * Upload or update a KYC document.
     */
    public function uploadDocument(Request $request): RedirectResponse
    {
        $rider = $this->resolveRider();

        $validated = $request->validate([
            'document_type' => ['required', 'string', Rule::in(array_keys(DeliveryDocument::TYPES))],
            'document_number' => ['nullable', 'string', 'max:64'],
            'expires_at' => ['nullable', 'date'],
            'document_file' => ['required', 'file', 'mimes:jpeg,png,jpg,pdf,webp', 'max:5120'], // 5MB max
        ]);

        $file = $request->file('document_file');
        $fileName = $validated['document_type'] . '_' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('riders/documents/' . $rider->id, $fileName, 'private');

        $existing = DeliveryDocument::where('delivery_partner_id', $rider->id)
            ->where('document_type', $validated['document_type'])
            ->first();

        if ($existing) {
            if (Storage::disk('private')->exists($existing->file_path)) {
                Storage::disk('private')->delete($existing->file_path);
            }
            $existing->update([
                'document_number' => $validated['document_number'] ?? $existing->document_number,
                'file_path' => $path,
                'expires_at' => $validated['expires_at'] ?? $existing->expires_at,
                'verification_status' => DeliveryDocument::STATUS_PENDING,
                'rejection_reason' => null,
                'verified_at' => null,
                'verified_by' => null,
            ]);
        } else {
            DeliveryDocument::create([
                'delivery_partner_id' => $rider->id,
                'document_type' => $validated['document_type'],
                'document_number' => $validated['document_number'] ?? null,
                'file_path' => $path,
                'expires_at' => $validated['expires_at'] ?? null,
                'verification_status' => DeliveryDocument::STATUS_PENDING,
            ]);
        }

        if (in_array($rider->approval_status, ['pending', 'rejected'])) {
            $rider->update(['approval_status' => 'under_review', 'rejection_reason' => null]);
        }

        $label = DeliveryDocument::TYPES[$validated['document_type']] ?? 'KYC document';
        return redirect()->route('rider.kyc')->with('success', "{$label} uploaded successfully. Awaiting admin verification.");
    }

    /**
     * Download or preview a KYC document.
     */
    public function viewDocument(DeliveryDocument $document): \Symfony\Component\HttpFoundation\Response
    {
        $rider = $this->resolveRider();

        abort_if((int) $document->delivery_partner_id !== (int) $rider->id, 403, 'Unauthorized access to document.');

        $disk = Storage::disk('private')->exists($document->file_path) ? 'private' : (Storage::disk('public')->exists($document->file_path) ? 'public' : null);

        if (!$disk) {
            abort(404, 'Document file not found.');
        }

        return Storage::disk($disk)->response($document->file_path);
    }

    /**
     * Delete an unapproved KYC document.
     */
    public function deleteDocument(DeliveryDocument $document): RedirectResponse
    {
        $rider = $this->resolveRider();

        abort_if((int) $document->delivery_partner_id !== (int) $rider->id, 403, 'Unauthorized access.');

        if ($document->verification_status === DeliveryDocument::STATUS_APPROVED) {
            return back()->with('error', 'Cannot delete an already approved KYC document. Please contact support.');
        }

        if (Storage::disk('private')->exists($document->file_path)) {
            Storage::disk('private')->delete($document->file_path);
        }

        $label = $document->document_type_label;
        $document->delete();

        return redirect()->route('rider.kyc')->with('success', "{$label} deleted successfully.");
    }

    /**
     * Rider Support & Emergency Helpline Screen (/rider/support).
     */
    public function support(Request $request): View
    {
        $rider = $this->resolveRider($request);

        return view('rider.support', [
            'rider' => $rider,
        ]);
    }

    /**
     * Telemetry Endpoint: Mobile GPS Location Ping (/api/v1/rider/location or /rider/location).
     */
    public function updateLocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rider_id' => 'required|exists:delivery_partners,id',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'accuracy' => 'nullable|numeric',
            'speed' => 'nullable|numeric',
            'assignment_id' => 'nullable|exists:delivery_assignments,id',
        ]);

        $partner = DeliveryPartner::findOrFail($validated['rider_id']);
        $partner->current_lat = (float) $validated['lat'];
        $partner->current_lng = (float) $validated['lng'];
        $partner->last_location_updated_at = now();
        $partner->save();

        // Append to telemetry breadcrumb history
        RiderLocationHistory::create([
            'delivery_partner_id' => $partner->id,
            'delivery_assignment_id' => $validated['assignment_id'] ?? null,
            'lat' => (float) $validated['lat'],
            'lng' => (float) $validated['lng'],
            'speed' => isset($validated['speed']) ? (float) $validated['speed'] : null,
            'recorded_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rider location telemetry recorded.',
            'rider_id' => $partner->id,
            'lat' => $partner->current_lat,
            'lng' => $partner->current_lng,
            'updated_at' => $partner->last_location_updated_at->toIso8601String(),
        ]);
    }

    /**
     * Resolve active rider profile by session or authenticated user.
     * Redirects to login if unauthenticated.
     */
    public function resolveRider(?Request $request = null): DeliveryPartner
    {
        $request = $request ?? request();
        if (session('active_rider_id')) {
            $partner = DeliveryPartner::find(session('active_rider_id'));
            if ($partner) {
                return $partner;
            }
        }

        if (auth()->check()) {
            $user = auth()->user();
            $partner = DeliveryPartner::where('user_id', $user->id)
                ->orWhere('phone', $user->phone)
                ->first();
            if ($partner) {
                session(['active_rider_id' => $partner->id]);
                return $partner;
            }
        }

        if ($request->wantsJson() || $request->ajax()) {
            throw new HttpResponseException(
                response()->json(['success' => false, 'message' => 'Unauthenticated rider.'], 401)
            );
        }

        throw new HttpResponseException(
            redirect()->route('rider.login')->with('error', 'Please sign in to access the rider portal.')
        );
    }
}
