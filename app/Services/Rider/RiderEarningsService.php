<?php

declare(strict_types=1);

namespace App\Services\Rider;

use App\Models\DeliveryAssignment;
use App\Models\DeliveryPartner;
use App\Models\RiderEarning;
use App\Models\RiderWalletTransaction;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class RiderEarningsService
{
    /**
     * Calculate payout details for a completed delivery assignment.
     *
     * @return array{base: float, distance_km: float, distance_pay: float, surge: float, tip: float, total: float}
     */
    public function calculatePayout(DeliveryAssignment $assignment, float $tip = 0.00): array
    {
        $fo = $assignment->fulfilmentOrder;
        $store = $fo?->store;
        $order = $fo?->order;
        $customerAddress = $order?->customerAddress;

        // Calculate actual or estimated distance in km
        $distanceKm = 2.5; // fallback average
        if ($store?->location && $customerAddress?->lat && $customerAddress?->lng) {
            $storeLat = (float) $store->location->lat;
            $storeLng = (float) $store->location->lng;
            $destLat = (float) $customerAddress->lat;
            $destLng = (float) $customerAddress->lng;

            $earthRadius = 6371; // km
            $dLat = deg2rad($destLat - $storeLat);
            $dLng = deg2rad($destLng - $storeLng);
            $a = sin($dLat / 2) * sin($dLat / 2) +
                cos(deg2rad($storeLat)) * cos(deg2rad($destLat)) *
                sin($dLng / 2) * sin($dLng / 2);
            $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
            $distanceKm = max(0.5, round($earthRadius * $c, 2));
        }

        $basePayout = (float) Setting::get('rider_base_payout', 35.00);
        $perKmRate = (float) Setting::get('rider_per_km_payout', 10.00);
        $baseThresholdKm = (float) Setting::get('rider_base_threshold_km', 3.00);
        $surgeBonus = (float) Setting::get('rider_surge_bonus', 0.00);

        $extraKm = max(0.0, $distanceKm - $baseThresholdKm);
        $distancePayout = round($extraKm * $perKmRate, 2);
        $cleanTip = max(0.0, round($tip, 2));

        $total = round($basePayout + $distancePayout + $surgeBonus + $cleanTip, 2);

        return [
            'base' => $basePayout,
            'distance_km' => $distanceKm,
            'distance_pay' => $distancePayout,
            'surge' => $surgeBonus,
            'tip' => $cleanTip,
            'total' => $total,
        ];
    }

    /**
     * Credit earnings to delivery partner upon successful delivery.
     */
    public function creditEarnings(DeliveryAssignment $assignment, float $tip = 0.00): ?RiderEarning
    {
        $partner = $assignment->deliveryPartner;
        $fo = $assignment->fulfilmentOrder;

        if (!$partner || !$fo) {
            return null;
        }

        // Avoid duplicate credit for the same assignment
        $existing = RiderEarning::where('delivery_assignment_id', $assignment->id)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($partner, $assignment, $fo, $tip) {
            $payout = $this->calculatePayout($assignment, $tip);

            $earning = RiderEarning::create([
                'delivery_partner_id' => $partner->id,
                'delivery_assignment_id' => $assignment->id,
                'fulfilment_order_id' => $fo->id,
                'base_payout' => $payout['base'],
                'distance_km' => $payout['distance_km'],
                'distance_payout' => $payout['distance_pay'],
                'surge_bonus' => $payout['surge'],
                'customer_tip' => $payout['tip'],
                'total_payout' => $payout['total'],
                'status' => 'credited',
            ]);

            // Update assignment record
            $assignment->rider_earnings_amount = $payout['total'];
            $assignment->save();

            // Credit to Rider Wallet
            $wallet = $partner->ensureWallet();
            $newBalance = round((float) $wallet->balance + $payout['total'], 2);
            $newTotalEarned = round((float) $wallet->total_earned + $payout['total'], 2);

            $wallet->update([
                'balance' => $newBalance,
                'total_earned' => $newTotalEarned,
            ]);

            RiderWalletTransaction::create([
                'rider_wallet_id' => $wallet->id,
                'type' => 'credit',
                'amount' => $payout['total'],
                'balance_after' => $newBalance,
                'category' => 'delivery_payout',
                'reference_type' => DeliveryAssignment::class,
                'reference_id' => $assignment->id,
                'description' => "Payout for delivery #{$fo->fulfilment_order_number} ({$payout['distance_km']} km)",
            ]);

            return $earning;
        });
    }
}
