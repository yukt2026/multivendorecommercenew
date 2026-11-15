<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryDocument;
use App\Models\DeliveryPartner;
use App\Models\FulfilmentOrder;
use App\Models\RiderWallet;
use App\Models\RiderWalletTransaction;
use App\Models\User;
use App\Services\QuickCommerce\QuickCommerceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliveryPartnerController extends Controller
{
    /**
     * Authorize that the current admin user has cargo view permissions.
     */
    protected function authorizeView(): void
    {
        $user = Auth::user();
        if (!$user) {
            abort(401);
        }
        if ($user->hasRole('super_admin') || $user->hasRole('admin') || $user->can('cargo.view')) {
            return;
        }
        abort(403, 'Unauthorized. Requires cargo.view permission.');
    }

    /**
     * Authorize that the current admin user has cargo management permissions.
     */
    protected function authorizeManage(): void
    {
        $user = Auth::user();
        if (!$user) {
            abort(401);
        }
        if ($user->hasRole('super_admin') || $user->hasRole('admin') || $user->can('cargo.manage')) {
            return;
        }
        abort(403, 'Unauthorized. Requires cargo.manage permission.');
    }

    /**
     * Build shared query for fleet list and CSV export.
     */
    protected function buildQuery(Request $request)
    {
        $query = DeliveryPartner::query()->withCount('assignments')->with('documents');

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

        if ($approval = $request->query('approval')) {
            if ($approval !== 'all') {
                $query->where('approval_status', $approval);
            }
        }

        return $query;
    }

    /**
     * Delivery partners fleet management (/admin/delivery-partners).
     */
    public function index(Request $request): View
    {
        $this->authorizeView();
        $query = $this->buildQuery($request);

        // Metrics Summary for quick commerce fleet operations
        $metrics = [
            'total_riders' => DeliveryPartner::count(),
            'available' => DeliveryPartner::where('status', 'available')->where('is_active', true)->count(),
            'busy' => DeliveryPartner::where('status', 'busy')->count(),
            'offline' => DeliveryPartner::where('status', 'offline')->orWhere('is_active', false)->count(),
            'pending_approval' => DeliveryPartner::whereIn('approval_status', ['pending', 'under_review'])->count(),
            'completed_deliveries' => DeliveryAssignment::where('status', 'delivered')->count(),
        ];

        $riders = $query->latest('id')->paginate(15)->withQueryString();

        return view('admin.delivery-partners.index', [
            'riders' => $riders,
            'metrics' => $metrics,
            'search' => $request->query('search', ''),
            'status' => $request->query('status', 'all'),
            'vehicle' => $request->query('vehicle', 'all'),
            'approval' => $request->query('approval', 'all'),
        ]);
    }

    /**
     * Store a new delivery partner / rider.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20', 'unique:delivery_partners,phone'],
            'vehicle' => ['required', 'string', 'max:50'],
            'vehicle_number' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string', 'in:available,busy,offline'],
            'approval_status' => ['nullable', 'string', 'in:pending,under_review,approved,rejected,suspended'],
            'current_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'current_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        $status = $validated['status'] ?? 'available';
        $isActive = $request->boolean('is_active', $status !== 'offline');
        $approvalStatus = $validated['approval_status'] ?? 'approved';

        // Auto-provision user account with role delivery_staff if user does not exist
        $cleanPhone = preg_replace('/[^0-9]/', '', $validated['phone']);
        $user = User::firstOrCreate(
            ['phone' => $validated['phone']],
            [
                'name' => $validated['name'],
                'email' => 'rider_' . ($cleanPhone ?: uniqid()) . '@onestall.internal',
                'password' => Hash::make($request->input('password', 'Password@123')),
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'delivery_staff', 'guard_name' => 'web']);
        if (!$user->hasRole('delivery_staff')) {
            $user->assignRole('delivery_staff');
        }

        $rider = DeliveryPartner::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'vehicle' => $validated['vehicle'],
            'vehicle_number' => $validated['vehicle_number'] ?? null,
            'current_lat' => $validated['current_lat'] ?? 12.9716,
            'current_lng' => $validated['current_lng'] ?? 77.5946,
            'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
            'is_active' => $isActive,
            'status' => $status,
            'approval_status' => $approvalStatus,
            'approved_at' => $approvalStatus === 'approved' ? now() : null,
            'approved_by' => $approvalStatus === 'approved' ? Auth::id() : null,
            'active_orders_count' => 0,
            'last_location_updated_at' => now(),
        ]);

        $rider->ensureWallet();

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
        $this->authorizeManage();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20', Rule::unique('delivery_partners', 'phone')->ignore($deliveryPartner->id)],
            'vehicle' => ['required', 'string', 'max:50'],
            'vehicle_number' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string', 'in:available,busy,offline'],
            'approval_status' => ['nullable', 'string', 'in:pending,under_review,approved,rejected,suspended'],
            'current_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'current_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
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
            'approval_status' => $validated['approval_status'] ?? $deliveryPartner->approval_status,
            'is_active' => $isActive,
            'current_lat' => $validated['current_lat'] ?? $deliveryPartner->current_lat,
            'current_lng' => $validated['current_lng'] ?? $deliveryPartner->current_lng,
            'emergency_contact_name' => $validated['emergency_contact_name'] ?? $deliveryPartner->emergency_contact_name,
            'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? $deliveryPartner->emergency_contact_phone,
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
        $this->authorizeManage();

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
        $this->authorizeManage();

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
     * Approve a pending rider registration.
     */
    public function approveRider(Request $request, DeliveryPartner $deliveryPartner): RedirectResponse|JsonResponse
    {
        $this->authorizeManage();

        $deliveryPartner->approval_status = 'approved';
        $deliveryPartner->approved_at = now();
        $deliveryPartner->approved_by = Auth::id();
        $deliveryPartner->rejection_reason = null;
        $deliveryPartner->is_active = true;
        if ($deliveryPartner->status === 'offline') {
            $deliveryPartner->status = 'available';
        }
        $deliveryPartner->save();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Delivery partner '{$deliveryPartner->name}' approved successfully.",
            ]);
        }

        return back()->with('success', "Delivery partner '{$deliveryPartner->name}' approved successfully.");
    }

    /**
     * Reject a pending rider registration.
     */
    public function rejectRider(Request $request, DeliveryPartner $deliveryPartner): RedirectResponse|JsonResponse
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'rejection_reason' => ['required_without:reason', 'nullable', 'string', 'max:255'],
            'reason' => ['required_without:rejection_reason', 'nullable', 'string', 'max:255'],
        ]);

        $reason = $validated['rejection_reason'] ?? $validated['reason'];

        $deliveryPartner->approval_status = 'rejected';
        $deliveryPartner->rejection_reason = $reason;
        $deliveryPartner->is_active = false;
        $deliveryPartner->status = 'offline';
        $deliveryPartner->save();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Delivery partner '{$deliveryPartner->name}' application rejected.",
            ]);
        }

        return back()->with('success', "Delivery partner '{$deliveryPartner->name}' application rejected.");
    }

    /**
     * Review an individual KYC document (approve or reject with remarks).
     */
    public function reviewDocument(
        Request $request,
        DeliveryPartner $deliveryPartner,
        DeliveryDocument $document
    ): RedirectResponse|JsonResponse {
        $this->authorizeManage();

        abort_if((int) $document->delivery_partner_id !== (int) $deliveryPartner->id, 404, 'Document does not belong to this delivery partner.');

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:approved,rejected'],
            'rejection_reason' => ['required_if:status,rejected', 'nullable', 'string', 'max:500'],
        ]);

        $document->update([
            'verification_status' => $validated['status'],
            'rejection_reason' => $validated['status'] === 'rejected' ? $validated['rejection_reason'] : null,
            'verified_at' => now(),
            'verified_by' => Auth::id(),
        ]);

        $hasRejections = $deliveryPartner->documents()->where('verification_status', 'rejected')->exists();
        if ($hasRejections) {
            $deliveryPartner->update([
                'approval_status' => 'rejected',
                'rejection_reason' => 'One or more KYC documents were rejected. Please review and re-upload.',
            ]);
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Document '{$document->document_type_label}' marked as {$validated['status']}.",
                'document' => $document,
            ]);
        }

        return back()->with('success', "Document '{$document->document_type_label}' marked as {$validated['status']}.");
    }

    /**
     * Download or view a delivery partner KYC document.
     */
    public function downloadDocument(
        DeliveryPartner $deliveryPartner,
        DeliveryDocument $document
    ): \Symfony\Component\HttpFoundation\Response {
        $this->authorizeView();

        abort_if((int) $document->delivery_partner_id !== (int) $deliveryPartner->id, 404, 'Document does not belong to this delivery partner.');

        $disk = Storage::disk('private')->exists($document->file_path) ? 'private' : (Storage::disk('public')->exists($document->file_path) ? 'public' : null);

        if (!$disk) {
            abort(404, 'Document file not found in storage.');
        }

        return Storage::disk($disk)->response($document->file_path);
    }

    /**
     * Admin manual assignment or reassignment of a rider to a quick fulfilment order.
     */
    public function assignOrder(
        Request $request,
        FulfilmentOrder $fulfilmentOrder,
        QuickCommerceService $quickService
    ): RedirectResponse|JsonResponse {
        $this->authorizeManage();

        $validated = $request->validate([
            'delivery_partner_id' => ['required', 'exists:delivery_partners,id'],
        ]);

        $newPartner = DeliveryPartner::findOrFail($validated['delivery_partner_id']);

        // Check if already assigned to someone else
        $existing = $fulfilmentOrder->deliveryAssignment;
        if ($existing && (int) $existing->delivery_partner_id !== (int) $newPartner->id) {
            // Free the previous partner
            $oldPartner = $existing->deliveryPartner;
            if ($oldPartner) {
                $oldPartner->active_orders_count = max(0, $oldPartner->active_orders_count - 1);
                if ($oldPartner->active_orders_count === 0) {
                    $oldPartner->status = 'available';
                }
                $oldPartner->save();
            }
        }

        $quickService->assignPartnerToOrder(
            $fulfilmentOrder,
            $newPartner,
            'Manually assigned / overridden by Administrator #' . Auth::id()
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Rider {$newPartner->name} assigned to package #{$fulfilmentOrder->fulfilment_order_number}.",
            ]);
        }

        return back()->with('success', "Rider {$newPartner->name} assigned to package #{$fulfilmentOrder->fulfilment_order_number}.");
    }

    /**
     * Admin Settlements & Rider Wallets Overview.
     */
    public function settlements(Request $request): View
    {
        $this->authorizeView();

        $wallets = RiderWallet::with('deliveryPartner')
            ->latest('updated_at')
            ->paginate(15);

        $metrics = [
            'total_wallets' => RiderWallet::count(),
            'total_balance' => (float) RiderWallet::sum('balance'),
            'total_cash_in_hand' => (float) DeliveryPartner::sum('current_cash_in_hand'),
            'total_lifetime_earned' => (float) RiderWallet::sum('total_earned'),
        ];

        return view('admin.delivery-partners.settlements', compact('wallets', 'metrics'));
    }

    /**
     * Admin Process Cash Payout / Settlement to Rider.
     */
    public function processPayout(Request $request, DeliveryPartner $deliveryPartner): RedirectResponse|JsonResponse
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $wallet = $deliveryPartner->ensureWallet();
        $payoutAmount = (float) $validated['amount'];

        if ($payoutAmount > (float) $wallet->balance) {
            $msg = "Requested payout of ₹{$payoutAmount} exceeds rider's available balance of ₹{$wallet->balance}.";
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $msg], 422);
            }
            return back()->with('error', $msg);
        }

        $newBalance = round((float) $wallet->balance - $payoutAmount, 2);
        $wallet->balance = $newBalance;
        $wallet->save();

        RiderWalletTransaction::create([
            'rider_wallet_id' => $wallet->id,
            'type' => 'debit',
            'amount' => $payoutAmount,
            'balance_after' => $newBalance,
            'category' => 'bank_withdrawal',
            'reference_type' => null,
            'reference_id' => null,
            'description' => "Bank payout processed by Admin: " . ($validated['reference'] ?? 'UTR-DIRECT') . ($validated['notes'] ? " ({$validated['notes']})" : ""),
        ]);

        $msg = "Payout of ₹{$payoutAmount} processed for {$deliveryPartner->name}.";
        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Stream CSV export of delivery fleet matching current filters.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorizeView();
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
                'Approval Status',
                'Is Active',
                'Active Orders',
                'Total Completed',
                'Cash In Hand',
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
                    ucfirst((string) $rider->approval_status),
                    $rider->is_active ? 'Yes' : 'No',
                    $rider->active_orders_count,
                    $rider->assignments_count,
                    $rider->current_cash_in_hand,
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
