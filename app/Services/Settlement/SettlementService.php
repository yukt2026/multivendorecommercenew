<?php

declare(strict_types=1);

namespace App\Services\Settlement;

use App\Enums\CODRemittanceStatus;
use App\Enums\SellerStatus;
use App\Enums\SettlementStatus;
use App\Models\CodRemittance;
use App\Models\FulfilmentOrder;
use App\Models\Seller;
use App\Models\SellerSettlement;
use App\Models\SettlementItem;
use App\Models\Setting;
use App\Services\Commission\CommissionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SettlementService
{
    public function __construct(
        protected CommissionService $commissionService
    ) {}

    /**
     * Generate weekly/scheduled settlement batch for all or specific sellers.
     * Only orders delivered past the return window (e.g. 7 days) are eligible.
     *
     * @return Collection<int, SellerSettlement>
     */
    public function generateWeeklyBatch(?Carbon $periodEnd = null, ?int $sellerId = null): Collection
    {
        $evalEnd = $periodEnd ? $periodEnd->copy()->endOfDay() : Carbon::now()->endOfDay();
        $returnWindowDays = (int) Setting::get('return_window_days', 7);
        $eligibleCutoff = $evalEnd->copy()->subDays($returnWindowDays);

        $sellersQuery = Seller::query()->whereIn('status', [SellerStatus::APPROVED, 'approved', 'active']);
        if ($sellerId) {
            $sellersQuery->where('id', $sellerId);
        }

        $sellers = $sellersQuery->get();
        $generatedSettlements = collect();

        foreach ($sellers as $seller) {
            $settlement = $this->generateForSeller($seller, $eligibleCutoff, $evalEnd);
            if ($settlement) {
                $generatedSettlements->push($settlement);
            }
        }

        return $generatedSettlements;
    }

    /**
     * Generate settlement for a specific seller.
     */
    public function generateForSeller(Seller $seller, Carbon $eligibleCutoff, Carbon $periodEnd): ?SellerSettlement
    {
        return DB::transaction(function () use ($seller, $eligibleCutoff, $periodEnd) {
            // 1. Fetch delivered orders past return window not already in any settlement
            $settledFoIds = SettlementItem::query()
                ->whereNotNull('fulfilment_order_id')
                ->pluck('fulfilment_order_id')
                ->toArray();

            $fulfilmentOrders = FulfilmentOrder::query()
                ->where('seller_id', $seller->id)
                ->where('status', 'delivered')
                ->where('delivered_at', '<=', $eligibleCutoff)
                ->whereNotIn('id', $settledFoIds)
                ->with(['items.variant', 'order', 'commissionEntry'])
                ->get();

            // 2. Fetch unremitted COD collections for this seller
            $codRemittances = CodRemittance::query()
                ->where('seller_id', $seller->id)
                ->where('status', CODRemittanceStatus::COLLECTED)
                ->where('collected_at', '<=', $eligibleCutoff)
                ->get();

            // If nothing to settle, return null
            if ($fulfilmentOrders->isEmpty() && $codRemittances->isEmpty()) {
                return null;
            }

            // Determine period start
            $lastSettlement = SellerSettlement::query()
                ->where('seller_id', $seller->id)
                ->orderByDesc('period_end')
                ->first();

            $periodStart = $lastSettlement
                ? Carbon::parse($lastSettlement->period_end)->addDay()->startOfDay()
                : ($fulfilmentOrders->min('delivered_at')
                    ? Carbon::parse($fulfilmentOrders->min('delivered_at'))->startOfDay()
                    : $periodEnd->copy()->subDays(7)->startOfDay());

            // Calculate totals from orders & commission entries
            $grossTotal = 0.00;
            $commissionTotal = 0.00;
            $taxTotal = 0.00;
            $itemsData = [];

            foreach ($fulfilmentOrders as $fo) {
                $entry = $fo->commissionEntry ?? $this->commissionService->recordForDeliveredFulfilmentOrder($fo);

                $grossTotal += (float) $fo->total_amount;
                $commissionTotal += (float) $entry->commission;
                $taxTotal += (float) $entry->tax_on_commission;

                $itemsData[] = [
                    'fulfilment_order_id' => $fo->id,
                    'commission_entry_id' => $entry->id,
                    'amount' => (float) $entry->net_payable,
                    'type' => 'order_payout',
                    'description' => "Order #{$fo->fulfilment_order_number} payout",
                ];
            }

            // COD collected deduction
            $codTotal = (float) $codRemittances->sum('amount');
            foreach ($codRemittances as $remittance) {
                $itemsData[] = [
                    'fulfilment_order_id' => null,
                    'commission_entry_id' => null,
                    'amount' => -1 * (float) $remittance->amount,
                    'type' => 'cod_deduction',
                    'description' => "COD cash collected doorstep: " . ($remittance->reference ?? "Ref #{$remittance->id}"),
                ];
            }

            $adjustmentsTotal = 0.00;
            $netTotal = round($grossTotal - $commissionTotal - $taxTotal - $codTotal + $adjustmentsTotal, 2);

            $settlementNumber = 'SET-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));

            $settlement = SellerSettlement::create([
                'seller_id' => $seller->id,
                'settlement_number' => $settlementNumber,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'gross' => $grossTotal,
                'commission' => $commissionTotal,
                'tax_on_commission' => $taxTotal,
                'cod_collected' => $codTotal,
                'adjustments' => $adjustmentsTotal,
                'net' => $netTotal,
                'status' => SettlementStatus::PENDING,
            ]);

            foreach ($itemsData as $item) {
                $settlement->items()->create($item);
            }

            Log::info("Generated SellerSettlement #{$settlement->settlement_number} for Seller #{$seller->id}. Net: {$netTotal}");

            return $settlement;
        });
    }

    /**
     * Approve a pending settlement, moving it to processing.
     */
    public function approveSettlement(SellerSettlement $settlement, ?int $adminId = null): SellerSettlement
    {
        if ($settlement->status !== SettlementStatus::PENDING && $settlement->status !== SettlementStatus::ON_HOLD) {
            throw new InvalidArgumentException("Settlement #{$settlement->settlement_number} cannot be approved from status '{$settlement->status->value}'.");
        }

        $settlement->status = SettlementStatus::PROCESSING;
        $settlement->hold_reason = null;
        $settlement->save();

        Log::info("Settlement #{$settlement->settlement_number} approved by Admin #{$adminId}");

        return $settlement;
    }

    /**
     * Mark settlement paid with bank UTR reference.
     */
    public function markPaid(SellerSettlement $settlement, string $utrReference, ?int $adminId = null): SellerSettlement
    {
        if (empty(trim($utrReference))) {
            throw new InvalidArgumentException("A valid bank UTR reference is required to mark settlement as paid.");
        }

        return DB::transaction(function () use ($settlement, $utrReference, $adminId) {
            $settlement->status = SettlementStatus::PAID;
            $settlement->reference = trim($utrReference);
            $settlement->paid_at = now();
            $settlement->save();

            // Reconcile COD remittances that were deducted in this settlement
            $remittanceReferences = $settlement->items()
                ->where('type', 'cod_deduction')
                ->pluck('description')
                ->toArray();

            CodRemittance::query()
                ->where('seller_id', $settlement->seller_id)
                ->where('status', CODRemittanceStatus::COLLECTED)
                ->update([
                    'status' => CODRemittanceStatus::REMITTED,
                    'remitted_at' => now(),
                ]);

            Log::info("Settlement #{$settlement->settlement_number} marked PAID with UTR: {$utrReference}");

            return $settlement;
        });
    }

    /**
     * Place a settlement on hold with reason.
     */
    public function holdSettlement(SellerSettlement $settlement, string $reason, ?int $adminId = null): SellerSettlement
    {
        if ($settlement->status === SettlementStatus::PAID || $settlement->status === SettlementStatus::SETTLED) {
            throw new InvalidArgumentException("Cannot place an already paid settlement on hold.");
        }

        $settlement->status = SettlementStatus::ON_HOLD;
        $settlement->hold_reason = $reason;
        $settlement->save();

        Log::warning("Settlement #{$settlement->settlement_number} placed ON HOLD: {$reason}");

        return $settlement;
    }

    /**
     * Release hold on a settlement.
     */
    public function releaseHold(SellerSettlement $settlement, ?string $note = null): SellerSettlement
    {
        if ($settlement->status !== SettlementStatus::ON_HOLD) {
            throw new InvalidArgumentException("Settlement is not currently on hold.");
        }

        $settlement->status = SettlementStatus::PENDING;
        $settlement->hold_reason = null;
        if ($note) {
            $settlement->notes = trim(($settlement->notes ?? '') . "\nHold released: {$note}");
        }
        $settlement->save();

        Log::info("Settlement #{$settlement->settlement_number} released from hold.");

        return $settlement;
    }

    /**
     * Add a positive or negative adjustment to a settlement.
     */
    public function addAdjustment(SellerSettlement $settlement, float $amount, string $reason): SettlementItem
    {
        if ($settlement->status === SettlementStatus::PAID || $settlement->status === SettlementStatus::SETTLED) {
            throw new InvalidArgumentException("Cannot add adjustments to an already paid settlement.");
        }

        return DB::transaction(function () use ($settlement, $amount, $reason) {
            $item = $settlement->items()->create([
                'fulfilment_order_id' => null,
                'commission_entry_id' => null,
                'amount' => $amount,
                'type' => 'adjustment',
                'description' => $reason,
            ]);

            $settlement->adjustments = round((float) $settlement->adjustments + $amount, 2);
            $settlement->net = round((float) $settlement->net + $amount, 2);
            $settlement->save();

            return $item;
        });
    }
}
