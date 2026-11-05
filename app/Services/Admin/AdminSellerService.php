<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\ProductStatus;
use App\Enums\SellerDocumentStatus;
use App\Enums\SellerStatus;
use App\Enums\SellerType;
use App\Enums\StoreStatus;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerBankAccount;
use App\Models\SellerDocument;
use App\Models\Store;
use App\Models\StoreLocation;
use App\Models\User;
use App\Notifications\SellerApprovedNotification;
use App\Notifications\SellerRejectedNotification;
use App\Notifications\SellerSuspendedNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminSellerService
{
    /**
     * Get paginated filtered sellers list.
     *
     * @param array<string, mixed> $filters
     */
    public function getFilteredSellers(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->buildFilteredQuery($filters)
            ->with(['user', 'stores.locations', 'documents'])
            ->withCount(['stores', 'products', 'fulfilmentOrders'])
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Export filtered sellers as a downloadable CSV stream.
     *
     * @param array<string, mixed> $filters
     */
    public function exportCsv(array $filters = []): StreamedResponse
    {
        $sellers = $this->buildFilteredQuery($filters)
            ->with(['user', 'stores.locations'])
            ->withCount(['stores', 'products', 'fulfilmentOrders'])
            ->latest('id')
            ->get();

        $filename = "onestall_sellers_export_" . date('Y_m_d_His') . ".csv";

        return new StreamedResponse(function () use ($sellers) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Seller ID',
                'Business Name',
                'Legal Name',
                'Owner Name',
                'Email',
                'Phone',
                'Seller Type',
                'KYC Status',
                'PAN',
                'GSTIN',
                'Commission Override (%)',
                'Store Count',
                'Product Count',
                'Fulfilment Orders Count',
                'Primary City',
                'Registered Date',
                'Approved Date',
            ]);

            foreach ($sellers as $seller) {
                $primaryCity = $seller->stores->first()?->locations->first()?->city ?? 'N/A';

                fputcsv($handle, [
                    $seller->id,
                    $seller->business_name,
                    $seller->legal_name,
                    $seller->user?->name ?? 'N/A',
                    $seller->user?->email ?? 'N/A',
                    $seller->user?->phone ?? 'N/A',
                    $seller->seller_type->label(),
                    $seller->status->label(),
                    $seller->pan ?? 'N/A',
                    $seller->gstin ?? 'N/A',
                    $seller->commission_override !== null ? "{$seller->commission_override}%" : 'Platform Default',
                    $seller->stores_count,
                    $seller->products_count,
                    $seller->fulfilment_orders_count,
                    $primaryCity,
                    $seller->created_at->format('Y-m-d H:i:s'),
                    $seller->approved_at ? $seller->approved_at->format('Y-m-d H:i:s') : 'N/A',
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Build base query with all dynamic filters.
     *
     * @param array<string, mixed> $filters
     */
    protected function buildFilteredQuery(array $filters): Builder
    {
        $query = Seller::query();

        // 1. Text Search across business, legal, PAN, GSTIN, and user credentials
        if (!empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            $query->where(function (Builder $sub) use ($term) {
                $sub->where('business_name', 'like', "%{$term}%")
                    ->orWhere('legal_name', 'like', "%{$term}%")
                    ->orWhere('pan', 'like', "%{$term}%")
                    ->orWhere('gstin', 'like', "%{$term}%")
                    ->orWhereHas('user', function (Builder $userSub) use ($term) {
                        $userSub->where('name', 'like', "%{$term}%")
                                ->orWhere('email', 'like', "%{$term}%")
                                ->orWhere('phone', 'like', "%{$term}%");
                    });
            });
        }

        // 2. Status Filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // 3. Seller Type Filter
        if (!empty($filters['type'])) {
            $query->where('seller_type', $filters['type']);
        }

        // 4. City Filter
        if (!empty($filters['city'])) {
            $cityTerm = trim((string) $filters['city']);
            $query->whereHas('stores.locations', function (Builder $locSub) use ($cityTerm) {
                $locSub->where('city', 'like', "%{$cityTerm}%");
            });
        }

        // 5. Date Range Filter
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    /**
     * Approve seller KYC application:
     * - Update status to approved
     * - Auto-create default store + unique slug if no store exists
     * - Assign seller_owner role
     * - Send queued notification/mail
     * - Record audit log
     */
    public function approveSeller(Seller $seller, User $admin): Store
    {
        $seller->loadMissing(['user', 'stores']);
        return DB::transaction(function () use ($seller, $admin) {
            $oldStatus = $seller->status->value;

            // 1. Update seller record
            $seller->update([
                'status' => SellerStatus::APPROVED,
                'approved_at' => now(),
                'approved_by' => $admin->id,
                'rejection_reason' => null,
            ]);

            // 2. Assign seller_owner role
            if (!$seller->user->hasRole('seller_owner')) {
                $seller->user->assignRole('seller_owner');
            }

            // 3. Auto-create default store if not exists
            $store = $seller->stores()->first();
            if (!$store) {
                $baseSlug = Str::slug($seller->business_name);
                $slug = $baseSlug ?: 'store-' . $seller->id;
                $counter = 1;
                while (Store::where('slug', $slug)->exists()) {
                    $slug = "{$baseSlug}-{$counter}";
                    $counter++;
                }

                $store = Store::create([
                    'seller_id' => $seller->id,
                    'name' => $seller->business_name,
                    'slug' => $slug,
                    'description' => "Official digital storefront of {$seller->business_name} on OneStall.",
                    'phone' => $seller->user->phone,
                    'email' => $seller->user->email,
                    'status' => StoreStatus::ACTIVE,
                    'is_onestall_owned' => $seller->seller_type === SellerType::ONESTALL_STORE,
                    'accepts_quick_commerce' => in_array($seller->seller_type, [
                        SellerType::LOCAL_STORE,
                        SellerType::ONESTALL_STORE,
                        SellerType::MULTI_STORE
                    ], true),
                    'accepts_pickup' => true,
                    'rating_avg' => 5.00,
                    'rating_count' => 1,
                ]);

                // Create default store location
                StoreLocation::create([
                    'store_id' => $store->id,
                    'address' => 'Registered Commercial Address',
                    'city' => 'Bengaluru',
                    'state' => 'Karnataka',
                    'pincode' => '560001',
                    'lat' => 12.9716,
                    'lng' => 77.5946,
                ]);
            } else {
                $store->update(['status' => StoreStatus::ACTIVE]);
            }

            // 4. Automatically approve all pending KYC documents
            $seller->documents()->where('status', SellerDocumentStatus::PENDING)->update([
                'status' => SellerDocumentStatus::APPROVED,
                'remarks' => 'Verified and approved during merchant onboarding audit.',
            ]);

            // 5. Audit Log
            activity()
                ->performedOn($seller)
                ->causedBy($admin)
                ->withProperties([
                    'action' => 'approve_seller',
                    'old' => ['status' => $oldStatus],
                    'new' => [
                        'status' => SellerStatus::APPROVED->value,
                        'approved_at' => now()->toDateTimeString(),
                        'approved_by' => $admin->id,
                        'store_id' => $store->id,
                        'store_slug' => $store->slug,
                    ],
                ])
                ->log("Approved seller #{$seller->id} ({$seller->business_name}) and activated default storefront '{$store->name}'");

            // 6. Send queued email / notification
            $seller->user->notify(new SellerApprovedNotification($seller, $store));

            return $store;
        });
    }

    /**
     * Reject seller KYC application with mandatory remarks:
     * - Update status to rejected
     * - Set rejection_reason
     * - Send queued notification
     * - Record audit log
     */
    public function rejectSeller(Seller $seller, string $reason, User $admin): void
    {
        DB::transaction(function () use ($seller, $reason, $admin) {
            $oldStatus = $seller->status->value;

            $seller->update([
                'status' => SellerStatus::REJECTED,
                'rejection_reason' => $reason,
            ]);

            // Update pending documents
            $seller->documents()->where('status', SellerDocumentStatus::PENDING)->update([
                'status' => SellerDocumentStatus::REJECTED,
                'remarks' => $reason,
            ]);

            // Audit log
            activity()
                ->performedOn($seller)
                ->causedBy($admin)
                ->withProperties([
                    'action' => 'reject_seller',
                    'reason' => $reason,
                    'old' => ['status' => $oldStatus],
                    'new' => [
                        'status' => SellerStatus::REJECTED->value,
                        'rejection_reason' => $reason,
                    ],
                ])
                ->log("Rejected seller application #{$seller->id} ({$seller->business_name}). Reason: {$reason}");

            // Queued notification
            $seller->user->notify(new SellerRejectedNotification($seller, $reason));
        });
    }

    /**
     * Suspend seller account:
     * - Set status to suspended with reason
     * - Hides storefront and products
     * - Queued notification
     * - Audit log
     */
    public function suspendSeller(Seller $seller, string $reason, User $admin): void
    {
        DB::transaction(function () use ($seller, $reason, $admin) {
            $oldStatus = $seller->status->value;

            $seller->update([
                'status' => SellerStatus::SUSPENDED,
                'suspension_reason' => $reason,
            ]);

            // Deactivate storefronts
            $seller->stores()->update(['status' => StoreStatus::SUSPENDED]);

            // Deactivate products (hides catalogue from marketplace search)
            $seller->products()->update(['status' => ProductStatus::INACTIVE]);

            // Audit log
            activity()
                ->performedOn($seller)
                ->causedBy($admin)
                ->withProperties([
                    'action' => 'suspend_seller',
                    'reason' => $reason,
                    'old' => ['status' => $oldStatus],
                    'new' => [
                        'status' => SellerStatus::SUSPENDED->value,
                        'suspension_reason' => $reason,
                    ],
                ])
                ->log("Suspended seller #{$seller->id} ({$seller->business_name}). Reason: {$reason}");

            // Queued notification
            $seller->user->notify(new SellerSuspendedNotification($seller, $reason));
        });
    }

    /**
     * Reactivate suspended seller account:
     * - Set status back to approved
     * - Restore stores and products to active
     * - Audit log
     */
    public function reactivateSeller(Seller $seller, ?string $reason, User $admin): void
    {
        DB::transaction(function () use ($seller, $reason, $admin) {
            $oldStatus = $seller->status->value;

            $seller->update([
                'status' => SellerStatus::APPROVED,
                'suspension_reason' => null,
            ]);

            // Restore storefronts
            $seller->stores()->update(['status' => StoreStatus::ACTIVE]);

            // Restore products
            $seller->products()->update(['status' => ProductStatus::ACTIVE]);

            // Audit log
            activity()
                ->performedOn($seller)
                ->causedBy($admin)
                ->withProperties([
                    'action' => 'reactivate_seller',
                    'note' => $reason,
                    'old' => ['status' => $oldStatus],
                    'new' => ['status' => SellerStatus::APPROVED->value],
                ])
                ->log("Reactivated seller #{$seller->id} ({$seller->business_name}). Note: " . ($reason ?? 'Account restored'));
        });
    }

    /**
     * Update custom seller commission override percentage.
     */
    public function updateCommissionOverride(Seller $seller, ?float $commission, ?string $remarks, User $admin): void
    {
        DB::transaction(function () use ($seller, $commission, $remarks, $admin) {
            $old = $seller->commission_override;

            $seller->update(['commission_override' => $commission]);

            activity()
                ->performedOn($seller)
                ->causedBy($admin)
                ->withProperties([
                    'action' => 'update_commission',
                    'remarks' => $remarks,
                    'old' => ['commission_override' => $old],
                    'new' => ['commission_override' => $commission],
                ])
                ->log("Updated commission override for seller #{$seller->id} ({$seller->business_name}) from " . ($old !== null ? "{$old}%" : 'Platform Default') . " to " . ($commission !== null ? "{$commission}%" : 'Platform Default'));
        });
    }

    /**
     * Review individual KYC document.
     */
    public function reviewDocument(SellerDocument $document, string $status, ?string $remarks, User $admin): void
    {
        DB::transaction(function () use ($document, $status, $remarks, $admin) {
            $oldStatus = $document->status->value;
            $newStatus = SellerDocumentStatus::from($status);

            $document->update([
                'status' => $newStatus,
                'remarks' => $remarks,
            ]);

            activity()
                ->performedOn($document->seller)
                ->causedBy($admin)
                ->withProperties([
                    'action' => 'review_document',
                    'document_id' => $document->id,
                    'document_type' => $document->type->label(),
                    'old' => ['status' => $oldStatus],
                    'new' => ['status' => $newStatus->value, 'remarks' => $remarks],
                ])
                ->log("Reviewed KYC document '{$document->type->label()}' for seller #{$document->seller_id}: {$newStatus->label()}");
        });
    }

    /**
     * Verify or unverify seller bank account.
     */
    public function verifyBankAccount(SellerBankAccount $bankAccount, bool $verified, User $admin): void
    {
        DB::transaction(function () use ($bankAccount, $verified, $admin) {
            $oldVerified = $bankAccount->verified_at?->toDateTimeString();
            $newVerified = $verified ? now() : null;

            $bankAccount->update(['verified_at' => $newVerified]);

            activity()
                ->performedOn($bankAccount->seller)
                ->causedBy($admin)
                ->withProperties([
                    'action' => 'verify_bank_account',
                    'bank_account_id' => $bankAccount->id,
                    'bank_name' => $bankAccount->bank_name,
                    'old' => ['verified_at' => $oldVerified],
                    'new' => ['verified_at' => $newVerified?->toDateTimeString()],
                ])
                ->log(($verified ? 'Verified' : 'Unverified') . " bank account for seller #{$bankAccount->seller_id} ({$bankAccount->bank_name})");
        });
    }

    /**
     * Perform bulk actions on multiple merchants.
     *
     * @param array<int> $sellerIds
     */
    public function performBulkAction(array $sellerIds, string $action, ?string $reason, User $admin): int
    {
        $sellers = Seller::whereIn('id', $sellerIds)->with(['user', 'stores'])->get();
        $processed = 0;

        foreach ($sellers as $seller) {
            match ($action) {
                'approve' => $this->approveSeller($seller, $admin),
                'reject' => $this->rejectSeller($seller, $reason ?? 'Bulk rejected by administration', $admin),
                'suspend' => $this->suspendSeller($seller, $reason ?? 'Bulk suspended by administration', $admin),
                'reactivate' => $this->reactivateSeller($seller, $reason, $admin),
            };
            $processed++;
        }

        return $processed;
    }

    /**
     * Get seller activity audit logs.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Activity>
     */
    public function getSellerActivityLogs(Seller $seller)
    {
        return Activity::where('subject_type', Seller::class)
            ->where('subject_id', $seller->id)
            ->with('causer')
            ->latest('id')
            ->limit(50)
            ->get();
    }
}
