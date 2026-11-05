<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Enums\StoreStaffRole;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreStaff;
use App\Services\Seller\StoreStaffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StoreStaffController extends Controller
{
    public function __construct(
        protected StoreStaffService $staffService
    ) {}

    /**
     * List all staff members across the seller's stores.
     */
    public function index(Request $request): View
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);

        $stores = $seller->stores()->get();
        $storeIdParam = $request->query('store_id', session('seller_active_store_id'));
        $storeId = (!empty($storeIdParam) && $storeIdParam !== 'all') ? (int) $storeIdParam : null;

        $staffList = $this->staffService->getStaffForSeller($seller, $storeId);

        return view('seller.staff.index', [
            'staffList' => $staffList,
            'stores' => $stores,
            'selectedStoreId' => $storeId,
            'roles' => StoreStaffRole::cases(),
            'availablePermissions' => StoreStaffRole::availablePermissions(),
        ]);
    }

    /**
     * Show form / screen to invite a staff member.
     */
    public function create(Request $request): View
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);

        $stores = $seller->stores()->get();
        $defaultStoreId = (int) $request->query('store_id', session('seller_active_store_id') ?: $stores->first()?->id);

        return view('seller.staff.create', [
            'stores' => $stores,
            'selectedStoreId' => $defaultStoreId,
            'roles' => StoreStaffRole::cases(),
            'availablePermissions' => StoreStaffRole::availablePermissions(),
        ]);
    }

    /**
     * Invite or assign staff by email/phone.
     */
    public function store(Request $request): RedirectResponse
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);

        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|string',
            'permissions' => 'nullable|array',
        ]);

        $store = $seller->stores()->findOrFail($validated['store_id']);

        try {
            $staff = $this->staffService->inviteStaff($store, $validated, Auth::user());

            return redirect()->route('seller.staff.index')
                ->with('success', "Staff member {$staff->user->name} invited successfully as {$staff->role->label()}!");
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Update an assigned staff member's role and permission matrix.
     */
    public function update(Request $request, StoreStaff $staff): RedirectResponse
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);

        // Security: Ensure staff belongs to one of this seller's stores
        abort_if($staff->store->seller_id !== $seller->id, 403);

        $validated = $request->validate([
            'role' => 'nullable|string',
            'permissions' => 'nullable|array',
            'status' => 'nullable|string|in:active,suspended',
        ]);

        $this->staffService->updateStaff($staff, $validated);

        return redirect()->route('seller.staff.index')
            ->with('success', "Staff permissions updated for {$staff->user->name}.");
    }

    /**
     * Revoke / delete staff access.
     */
    public function destroy(StoreStaff $staff): RedirectResponse
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);

        abort_if($staff->store->seller_id !== $seller->id, 403);

        $name = $staff->user->name;
        $this->staffService->removeStaff($staff);

        return redirect()->route('seller.staff.index')
            ->with('success', "Staff access revoked for {$name}.");
    }
}
