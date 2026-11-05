<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CommissionRuleScope;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CommissionRule;
use App\Models\Seller;
use App\Models\Setting;
use App\Services\Commission\CommissionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommissionRuleController extends Controller
{
    public function __construct(
        protected CommissionService $commissionService
    ) {}

    /**
     * Display commission rules and interactive simulator.
     */
    public function index(Request $request): View
    {
        $query = CommissionRule::query()->with(['category', 'seller'])->orderByDesc('priority')->latest();

        if ($scope = $request->query('scope')) {
            if ($scope !== 'all') {
                $query->where('scope', $scope);
            }
        }

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $rules = $query->paginate(15)->withQueryString();

        $categories = Category::orderBy('name')->get();
        $sellers = Seller::whereIn('status', [\App\Enums\SellerStatus::APPROVED, 'approved', 'active'])->orderBy('business_name')->get();
        $defaultCommissionPct = (float) Setting::get('default_commission_pct', 10.00);
        $commissionGstPct = (float) Setting::get('commission_gst_pct', 18.00);
        $paymentGatewayFeePct = (float) Setting::get('payment_gateway_fee_pct', 2.00);

        return view('admin.commissions.index', compact(
            'rules',
            'categories',
            'sellers',
            'defaultCommissionPct',
            'commissionGstPct',
            'paymentGatewayFeePct'
        ));
    }

    /**
     * Store a new commission rule.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'scope' => ['required', 'string', 'in:global,category,seller,promo'],
            'category_id' => ['nullable', 'required_if:scope,category', 'exists:categories,id'],
            'seller_id' => ['nullable', 'required_if:scope,seller', 'exists:sellers,id'],
            'percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'fixed_fee' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'priority' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['fixed_fee'] = $validated['fixed_fee'] ?? 0.00;

        CommissionRule::create($validated);

        return redirect()->route('admin.commissions.index')->with('success', "Commission rule '{$validated['name']}' created successfully.");
    }

    /**
     * Update an existing commission rule.
     */
    public function update(Request $request, CommissionRule $commission): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'scope' => ['required', 'string', 'in:global,category,seller,promo'],
            'category_id' => ['nullable', 'required_if:scope,category', 'exists:categories,id'],
            'seller_id' => ['nullable', 'required_if:scope,seller', 'exists:sellers,id'],
            'percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'fixed_fee' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'priority' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['fixed_fee'] = $validated['fixed_fee'] ?? 0.00;

        $commission->update($validated);

        return redirect()->route('admin.commissions.index')->with('success', "Commission rule '{$commission->name}' updated successfully.");
    }

    /**
     * Delete a commission rule.
     */
    public function destroy(CommissionRule $commission): RedirectResponse
    {
        $name = $commission->name;
        $commission->delete();

        return redirect()->route('admin.commissions.index')->with('success', "Commission rule '{$name}' deleted successfully.");
    }

    /**
     * Toggle active status.
     */
    public function toggle(CommissionRule $commission): JsonResponse
    {
        $commission->is_active = !$commission->is_active;
        $commission->save();

        return response()->json([
            'success' => true,
            'is_active' => $commission->is_active,
            'message' => "Rule status changed to " . ($commission->is_active ? 'active' : 'inactive'),
        ]);
    }

    /**
     * Interactive Simulator endpoint.
     */
    public function simulate(Request $request): JsonResponse
    {
        $sellerId = $request->input('seller_id') ? (int) $request->input('seller_id') : null;
        $categoryId = $request->input('category_id') ? (int) $request->input('category_id') : null;
        $amount = (float) $request->input('amount', 1000.00);
        $shipping = (float) $request->input('shipping_adjustment', 0.00);
        $date = $request->input('date') ? Carbon::parse($request->input('date')) : Carbon::now();

        $calculation = $this->commissionService->calculate(
            baseAmount: $amount,
            sellerId: $sellerId,
            categoryId: $categoryId,
            shippingAdjustment: $shipping,
            date: $date
        );

        return response()->json([
            'success' => true,
            'base_amount' => $calculation['base_amount'],
            'tier' => $calculation['tier'],
            'tier_label' => match($calculation['tier']) {
                'seller' => 'Seller Specific Rule',
                'seller_override_column' => 'Seller Direct Column Override',
                'promo' => 'Promotional Window Rule',
                'category' => 'Category Specific Rule',
                'global' => 'Global Default Rule',
                'setting_fallback' => 'System Setting Fallback',
                default => 'Default',
            },
            'rule_name' => $calculation['rule']?->name ?? 'System Default Setting',
            'rule_id' => $calculation['rule']?->id,
            'rate_percent' => $calculation['rate_percent'],
            'fixed_fee' => $calculation['fixed_fee'],
            'commission_amount' => $calculation['commission_amount'],
            'tax_on_commission' => $calculation['tax_on_commission'],
            'tax_rate_percent' => $calculation['tax_rate_percent'],
            'gateway_fee' => $calculation['gateway_fee'],
            'gateway_fee_percent' => $calculation['gateway_fee_percent'],
            'shipping_adjustment' => $calculation['shipping_adjustment'],
            'total_platform_deduction' => $calculation['total_platform_deduction'],
            'net_payable' => $calculation['net_payable'],
            'formatted' => [
                'base_amount' => format_currency($calculation['base_amount']),
                'commission_amount' => format_currency($calculation['commission_amount']),
                'tax_on_commission' => format_currency($calculation['tax_on_commission']),
                'gateway_fee' => format_currency($calculation['gateway_fee']),
                'shipping_adjustment' => format_currency($calculation['shipping_adjustment']),
                'total_platform_deduction' => format_currency($calculation['total_platform_deduction']),
                'net_payable' => format_currency($calculation['net_payable']),
            ],
        ]);
    }
}
