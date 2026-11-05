<?php

declare(strict_types=1);

namespace App\Services\Commission;

use App\Enums\CommissionRuleScope;
use App\Models\Category;
use App\Models\CommissionEntry;
use App\Models\CommissionRule;
use App\Models\FulfilmentOrder;
use App\Models\Seller;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommissionService
{
    /**
     * Resolve the active commission rule by strict priority order:
     * 1. Seller-specific override rule
     * 2. Promotional rule within active dates
     * 3. Category-specific rule
     * 4. Global default rule
     *
     * @param int|null $sellerId
     * @param int|null $categoryId
     * @param Carbon|null $date
     * @return array{rule: ?CommissionRule, tier: string, rate: float, fixed_fee: float}
     */
    public function resolveRule(?int $sellerId = null, ?int $categoryId = null, ?Carbon $date = null): array
    {
        $evalDate = $date ?? Carbon::now();

        // 1. Priority 1: Seller-specific rule
        if ($sellerId) {
            $sellerRule = CommissionRule::query()
                ->where('scope', CommissionRuleScope::SELLER)
                ->where('seller_id', $sellerId)
                ->where('is_active', true)
                ->where(function ($q) use ($evalDate) {
                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', $evalDate);
                })
                ->where(function ($q) use ($evalDate) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', $evalDate);
                })
                ->orderByDesc('priority')
                ->first();

            if ($sellerRule) {
                return [
                    'rule' => $sellerRule,
                    'tier' => 'seller',
                    'rate' => (float) $sellerRule->percent,
                    'fixed_fee' => (float) $sellerRule->fixed_fee,
                ];
            }

            // Check direct seller commission_override_pct column on Seller entity
            $seller = Seller::find($sellerId);
            $override = $seller?->commission_override ?? $seller?->commission_override_pct;
            if ($seller && $override !== null && (float) $override >= 0) {
                return [
                    'rule' => null,
                    'tier' => 'seller_override_column',
                    'rate' => (float) $override,
                    'fixed_fee' => 0.00,
                ];
            }
        }

        // 2. Priority 2: Promotional rule within dates
        $promoRule = CommissionRule::query()
            ->where('scope', CommissionRuleScope::PROMO)
            ->where('is_active', true)
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->where('starts_at', '<=', $evalDate)
            ->where('ends_at', '>=', $evalDate)
            ->orderByDesc('priority')
            ->first();

        if ($promoRule) {
            return [
                'rule' => $promoRule,
                'tier' => 'promo',
                'rate' => (float) $promoRule->percent,
                'fixed_fee' => (float) $promoRule->fixed_fee,
            ];
        }

        // 3. Priority 3: Category-specific rule
        if ($categoryId) {
            $categoryRule = CommissionRule::query()
                ->where('scope', CommissionRuleScope::CATEGORY)
                ->where('category_id', $categoryId)
                ->where('is_active', true)
                ->where(function ($q) use ($evalDate) {
                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', $evalDate);
                })
                ->where(function ($q) use ($evalDate) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', $evalDate);
                })
                ->orderByDesc('priority')
                ->first();

            if ($categoryRule) {
                return [
                    'rule' => $categoryRule,
                    'tier' => 'category',
                    'rate' => (float) $categoryRule->percent,
                    'fixed_fee' => (float) $categoryRule->fixed_fee,
                ];
            }

            // Check parent categories if nested
            $category = Category::find($categoryId);
            if ($category && $category->parent_id) {
                $parentResolved = $this->resolveRule(null, $category->parent_id, $evalDate);
                if ($parentResolved['tier'] === 'category') {
                    return $parentResolved;
                }
            }
        }

        // 4. Priority 4: Global default rule from commission_rules table
        $globalRule = CommissionRule::query()
            ->where('scope', CommissionRuleScope::GLOBAL)
            ->where('is_active', true)
            ->where(function ($q) use ($evalDate) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $evalDate);
            })
            ->where(function ($q) use ($evalDate) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $evalDate);
            })
            ->orderByDesc('priority')
            ->first();

        if ($globalRule) {
            return [
                'rule' => $globalRule,
                'tier' => 'global',
                'rate' => (float) $globalRule->percent,
                'fixed_fee' => (float) $globalRule->fixed_fee,
            ];
        }

        // 5. Priority 5: Fallback to platform database setting
        $fallbackRate = (float) Setting::get('default_commission_pct', config('onestall.default_commission_pct', 10.00));

        return [
            'rule' => null,
            'tier' => 'setting_fallback',
            'rate' => $fallbackRate,
            'fixed_fee' => 0.00,
        ];
    }

    /**
     * Compute full commission accounting breakdown:
     * - Percent + Fixed Fee
     * - GST on Commission (default 18% from settings)
     * - Payment Gateway Fee accounting (default 2% from settings)
     * - Shipping Adjustments
     * - Net Seller Payable
     *
     * @return array{
     *     rule: ?CommissionRule,
     *     tier: string,
     *     base_amount: float,
     *     rate: float,
     *     fixed_fee: float,
     *     commission_pre_tax: float,
     *     tax_on_commission: float,
     *     gst_pct: float,
     *     total_commission: float,
     *     gateway_fee: float,
     *     shipping_adjustment: float,
     *     net_payable: float,
     *     explanation: string
     * }
     */
    public function calculate(
        float $baseAmount,
        ?int $sellerId = null,
        ?int $categoryId = null,
        float $shippingAdjustment = 0.00,
        ?Carbon $date = null
    ): array {
        $resolved = $this->resolveRule($sellerId, $categoryId, $date);

        $rate = $resolved['rate'];
        $fixedFee = $resolved['fixed_fee'];

        // 1. Base Commission (Percent + Fixed Fee)
        $commissionPercentAmount = round(($baseAmount * $rate) / 100, 2);
        $commissionPreTax = round($commissionPercentAmount + $fixedFee, 2);

        // 2. GST on Commission (Government mandated, e.g. 18%)
        $gstPct = (float) Setting::get('commission_gst_pct', 18.00);
        $taxOnCommission = round(($commissionPreTax * $gstPct) / 100, 2);
        $totalCommission = round($commissionPreTax + $taxOnCommission, 2);

        // 3. Payment Gateway Fee Accounting
        $gatewayFeePct = (float) Setting::get('payment_gateway_fee_pct', 2.00);
        $gatewayFeeBorneBySeller = (bool) Setting::get('gateway_fee_borne_by_seller', true);
        $gatewayFee = $gatewayFeeBorneBySeller ? round(($baseAmount * $gatewayFeePct) / 100, 2) : 0.00;

        // 4. Net Seller Payable
        // Net = Base + Shipping Adjustment - Total Commission (incl GST) - Gateway Fee
        $netPayable = max(0.00, round($baseAmount + $shippingAdjustment - $totalCommission - $gatewayFee, 2));

        $tierLabels = [
            'seller' => 'Seller-Specific Rule Override (Priority 1)',
            'seller_override_column' => 'Seller Profile Override Rate (Priority 1)',
            'promo' => 'Active Promotional Campaign Window (Priority 2)',
            'category' => 'Category-Specific Rule (Priority 3)',
            'global' => 'Global Commission Rule (Priority 4)',
            'setting_fallback' => 'Platform Settings Fallback (Priority 5)',
        ];

        return [
            'rule' => $resolved['rule'],
            'tier' => $resolved['tier'],
            'base_amount' => $baseAmount,
            'rate' => $rate,
            'rate_percent' => $rate,
            'fixed_fee' => $fixedFee,
            'commission_pre_tax' => $commissionPreTax,
            'commission_amount' => $commissionPreTax,
            'tax_on_commission' => $taxOnCommission,
            'tax_rate_percent' => $gstPct,
            'gst_pct' => $gstPct,
            'total_commission' => $totalCommission,
            'total_platform_deduction' => $totalCommission,
            'gateway_fee' => $gatewayFee,
            'gateway_fee_percent' => $gatewayFeePct,
            'shipping_adjustment' => $shippingAdjustment,
            'net_payable' => $netPayable,
            'explanation' => $tierLabels[$resolved['tier']] ?? 'Commission Rule',
        ];
    }

    /**
     * Create CommissionEntry when a fulfilment order is marked DELIVERED.
     */
    public function recordForDeliveredFulfilmentOrder(FulfilmentOrder $fo): CommissionEntry
    {
        return DB::transaction(function () use ($fo) {
            $sellerId = $fo->seller_id;
            $firstItem = $fo->items()->with('product.category')->first();
            $categoryId = $firstItem?->product?->category_id;

            $baseAmount = (float) $fo->subtotal;
            $shippingAdjustment = 0.00;

            // If seller delivers directly or merchant self-pickup, pass shipping fee to seller
            if (Setting::get('pass_delivery_fee_to_seller', false)) {
                $shippingAdjustment = (float) $fo->delivery_fee;
            }

            $calculation = $this->calculate($baseAmount, $sellerId, $categoryId, $shippingAdjustment);

            // Update FulfilmentOrder values
            $fo->commission_amount = $calculation['total_commission'];
            $fo->seller_payable = $calculation['net_payable'];
            $fo->saveQuietly();

            $entry = CommissionEntry::create([
                'fulfilment_order_id' => $fo->id,
                'rule_id' => $calculation['rule']?->id,
                'base_amount' => $baseAmount,
                'commission' => $calculation['commission_pre_tax'],
                'tax_on_commission' => $calculation['tax_on_commission'],
                'gateway_fee' => $calculation['gateway_fee'],
                'shipping_adjustment' => $calculation['shipping_adjustment'],
                'net_payable' => $calculation['net_payable'],
                'entry_type' => 'standard',
                'notes' => "Delivered package payout. Resolved tier: {$calculation['tier']}",
            ]);

            Log::info("CommissionEntry #{$entry->id} created for delivered package #{$fo->fulfilment_order_number}. Net: {$calculation['net_payable']}");

            return $entry;
        });
    }

    /**
     * Compute and record commission entry for a FulfilmentOrder.
     */
    public function recordForFulfilmentOrder(FulfilmentOrder $fo): CommissionEntry
    {
        return $this->recordForDeliveredFulfilmentOrder($fo);
    }

    /**
     * Create Reversal CommissionEntry when an order/package is refunded or returned.
     */
    public function recordReversal(
        FulfilmentOrder $fo,
        float $refundAmount,
        string $reason = 'Return / Refund reversal'
    ): CommissionEntry {
        return DB::transaction(function () use ($fo, $refundAmount, $reason) {
            // Find existing standard entry for proportion
            $originalEntry = $fo->commissionEntry ?? CommissionEntry::where('fulfilment_order_id', $fo->id)->where('entry_type', 'standard')->first();

            $baseAmount = $originalEntry ? (float) $originalEntry->base_amount : (float) $fo->subtotal;
            $ratio = $baseAmount > 0 ? min(1.0, max(0.0, $refundAmount / $baseAmount)) : 1.0;

            $commPreTax = $originalEntry ? round(((float) $originalEntry->commission) * $ratio, 2) : 0.00;
            $taxOnComm = $originalEntry ? round(((float) $originalEntry->tax_on_commission) * $ratio, 2) : 0.00;
            $gatewayFee = $originalEntry ? round(((float) $originalEntry->gateway_fee) * $ratio, 2) : 0.00;
            $netDeduction = round($refundAmount - ($commPreTax + $taxOnComm) - $gatewayFee, 2);

            $reversalEntry = CommissionEntry::create([
                'fulfilment_order_id' => $fo->id,
                'rule_id' => $originalEntry?->rule_id,
                'base_amount' => -$refundAmount,
                'commission' => -$commPreTax,
                'tax_on_commission' => -$taxOnComm,
                'gateway_fee' => -$gatewayFee,
                'shipping_adjustment' => 0.00,
                'net_payable' => -$netDeduction,
                'entry_type' => 'reversal',
                'notes' => "Commission reversal: {$reason}",
            ]);

            Log::warning("Reversal CommissionEntry #{$reversalEntry->id} recorded for FulfilmentOrder #{$fo->id}. Deduction: -{$netDeduction}");

            return $reversalEntry;
        });
    }
}
