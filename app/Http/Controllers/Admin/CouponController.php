<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Seller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CouponController extends Controller
{
    /**
     * Display coupons list.
     */
    public function index(Request $request): View
    {
        $query = Coupon::query()->with(['seller'])->withCount('usages')->latest('id');

        if ($search = $request->query('search')) {
            $query->where('code', 'like', "%{$search}%");
        }

        if ($type = $request->query('type')) {
            if ($type !== 'all') {
                $query->where('type', $type);
            }
        }

        if ($scope = $request->query('scope')) {
            if ($scope === 'platform') {
                $query->whereNull('seller_id');
            } elseif ($scope === 'seller') {
                $query->whereNotNull('seller_id');
            }
        }

        if ($status = $request->query('status')) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $coupons = $query->paginate(15)->withQueryString();
        $sellers = Seller::whereIn('status', [\App\Enums\SellerStatus::APPROVED, 'approved', 'active'])->orderBy('business_name')->get();
        $categories = Category::whereNull('parent_id')->orderBy('name')->get();

        $metrics = [
            'total_coupons' => Coupon::count(),
            'active_coupons' => Coupon::where('is_active', true)->count(),
            'platform_coupons' => Coupon::whereNull('seller_id')->count(),
            'seller_coupons' => Coupon::whereNotNull('seller_id')->count(),
            'total_redemptions' => CouponUsage::count(),
        ];

        return view('admin.coupons.index', compact('coupons', 'sellers', 'categories', 'metrics'));
    }

    /**
     * Store a new platform or seller coupon.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'type' => ['required', 'in:percent,flat'],
            'value' => ['required', 'numeric', 'min:0.01'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'min_order' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'seller_id' => ['nullable', 'exists:sellers,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['min_order'] = $validated['min_order'] ?? 0.00;
        $validated['per_user_limit'] = $validated['per_user_limit'] ?? 1;
        $validated['seller_id'] = !empty($validated['seller_id']) ? (int) $validated['seller_id'] : null;

        if (!empty($validated['category_ids'])) {
            $validated['applies_to'] = [
                'categories' => array_values(array_map('intval', $validated['category_ids'])),
            ];
        } else {
            $validated['applies_to'] = null;
        }
        unset($validated['category_ids']);

        $coupon = Coupon::create($validated);

        if (function_exists('activity')) {
            activity('coupon')
                ->performedOn($coupon)
                ->causedBy(auth()->user())
                ->log("Coupon #{$coupon->id} ({$coupon->code}) created");
        }

        return redirect()->route('admin.coupons.index')->with('success', "Coupon '{$coupon->code}' created successfully.");
    }

    /**
     * Update an existing coupon.
     */
    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code,' . $coupon->id],
            'type' => ['required', 'in:percent,flat'],
            'value' => ['required', 'numeric', 'min:0.01'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'min_order' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'seller_id' => ['nullable', 'exists:sellers,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = $request->boolean('is_active');
        $validated['min_order'] = $validated['min_order'] ?? 0.00;
        $validated['per_user_limit'] = $validated['per_user_limit'] ?? 1;
        $validated['seller_id'] = !empty($validated['seller_id']) ? (int) $validated['seller_id'] : null;

        if (!empty($validated['category_ids'])) {
            $validated['applies_to'] = [
                'categories' => array_values(array_map('intval', $validated['category_ids'])),
            ];
        } else {
            $validated['applies_to'] = null;
        }
        unset($validated['category_ids']);

        $coupon->update($validated);

        if (function_exists('activity')) {
            activity('coupon')
                ->performedOn($coupon)
                ->causedBy(auth()->user())
                ->log("Coupon #{$coupon->id} ({$coupon->code}) updated");
        }

        return redirect()->route('admin.coupons.index')->with('success', "Coupon '{$coupon->code}' updated successfully.");
    }

    /**
     * Delete a coupon.
     */
    public function destroy(Coupon $coupon): RedirectResponse
    {
        $code = $coupon->code;
        $coupon->delete();

        if (function_exists('activity')) {
            activity('coupon')
                ->causedBy(auth()->user())
                ->log("Coupon {$code} deleted");
        }

        return redirect()->route('admin.coupons.index')->with('success', "Coupon '{$code}' deleted successfully.");
    }

    /**
     * Toggle coupon active state.
     */
    public function toggle(Request $request, Coupon $coupon): JsonResponse|RedirectResponse
    {
        $coupon->is_active = !$coupon->is_active;
        $coupon->save();

        if (function_exists('activity')) {
            activity('coupon')
                ->performedOn($coupon)
                ->causedBy(auth()->user())
                ->log("Coupon #{$coupon->id} ({$coupon->code}) status changed to " . ($coupon->is_active ? 'Active' : 'Inactive'));
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'is_active' => $coupon->is_active,
                'message' => "Coupon status updated to " . ($coupon->is_active ? 'Active' : 'Inactive'),
            ]);
        }

        return back()->with('success', "Coupon '{$coupon->code}' status toggled to " . ($coupon->is_active ? 'Active' : 'Inactive') . '.');
    }

    /**
     * Export coupons list as CSV.
     */
    public function export(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="coupons-' . now()->format('Ymd-His') . '.csv"',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Coupon ID', 'Code', 'Discount Type', 'Discount Value', 'Min Basket (INR)', 'Max Discount Cap (INR)', 'Total Usage Cap', 'Times Redeemed', 'Scope', 'Status', 'Valid From', 'Valid Until']);

            Coupon::with(['seller'])->withCount('usages')->chunk(100, function ($coupons) use ($handle) {
                foreach ($coupons as $c) {
                    fputcsv($handle, [
                        $c->id,
                        $c->code,
                        $c->type_label,
                        $c->formatted_discount,
                        $c->min_order,
                        $c->max_discount ?? 'Unlimited',
                        $c->usage_limit ?? 'Unlimited',
                        $c->usages_count ?? 0,
                        $c->seller ? $c->seller->business_name : 'Platform Wide',
                        $c->is_active ? 'Active' : 'Inactive',
                        $c->starts_at ? $c->starts_at->format('Y-m-d H:i') : 'Immediate',
                        $c->ends_at ? $c->ends_at->format('Y-m-d H:i') : 'Indefinite',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}
