<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\SellerStatus;
use App\Enums\SellerType;
use App\Enums\SettlementStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkSellerActionRequest;
use App\Http\Requests\Admin\RejectSellerRequest;
use App\Http\Requests\Admin\ReviewSellerDocumentRequest;
use App\Http\Requests\Admin\SuspendSellerRequest;
use App\Http\Requests\Admin\UpdateSellerCommissionRequest;
use App\Models\Seller;
use App\Models\SellerBankAccount;
use App\Models\SellerDocument;
use App\Services\Admin\AdminSellerService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SellerController extends Controller
{
    public function __construct(
        protected AdminSellerService $sellerService
    ) {}

    /**
     * Display the paginated and filtered list of sellers.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Seller::class);

        $filters = [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'type' => $request->query('type'),
            'city' => $request->query('city'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];

        $sellers = $this->sellerService->getFilteredSellers($filters, 15);

        return view('admin.sellers.index', [
            'sellers' => $sellers,
            'filters' => $filters,
            'statuses' => SellerStatus::cases(),
            'types' => SellerType::cases(),
        ]);
    }

    /**
     * Stream CSV export of filtered sellers.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Seller::class);

        return $this->sellerService->exportCsv($request->all());
    }

    /**
     * Display seller detail page with all tabs.
     */
    public function show(Request $request, Seller $seller): View
    {
        $this->authorize('view', $seller);

        $seller->load([
            'user',
            'stores.locations',
            'stores.timings',
            'stores.deliveryZones',
            'documents',
            'bankAccounts',
            'brands',
        ]);

        $products = $seller->products()
            ->with(['variants', 'category', 'brand'])
            ->latest('id')
            ->paginate(10, ['*'], 'products_page');

        $orders = $seller->fulfilmentOrders()
            ->with(['order.customer.user'])
            ->latest('id')
            ->paginate(10, ['*'], 'orders_page');

        $activityLogs = $this->sellerService->getSellerActivityLogs($seller);

        $stats = [
            'total_gmv' => (float) $seller->fulfilmentOrders()->sum('subtotal'),
            'total_orders' => $seller->fulfilmentOrders()->count(),
            'stores_count' => $seller->stores()->count(),
            'products_count' => $seller->products()->count(),
            'pending_payout' => (float) $seller->settlements()->where('status', SettlementStatus::PENDING)->sum('net'),
        ];

        $activeTab = (string) $request->query('tab', 'overview');

        return view('admin.sellers.show', [
            'seller' => $seller,
            'products' => $products,
            'orders' => $orders,
            'activityLogs' => $activityLogs,
            'stats' => $stats,
            'activeTab' => $activeTab,
        ]);
    }

    /**
     * Approve seller KYC application.
     */
    public function approve(Request $request, Seller $seller): RedirectResponse
    {
        $this->authorize('approve', $seller);

        $store = $this->sellerService->approveSeller($seller, $request->user());

        return redirect()
            ->route('admin.sellers.show', ['seller' => $seller, 'tab' => 'overview'])
            ->with('success', "Merchant '{$seller->business_name}' approved successfully! Store '{$store->name}' initialized.");
    }

    /**
     * Reject seller KYC application with mandatory remarks.
     */
    public function reject(RejectSellerRequest $request, Seller $seller): RedirectResponse
    {
        $this->authorize('reject', $seller);

        $this->sellerService->rejectSeller($seller, (string) $request->validated('reason'), $request->user());

        return redirect()
            ->route('admin.sellers.show', ['seller' => $seller, 'tab' => 'kyc'])
            ->with('success', "Merchant '{$seller->business_name}' has been rejected with recorded remarks.");
    }

    /**
     * Suspend seller account with mandatory reason.
     */
    public function suspend(SuspendSellerRequest $request, Seller $seller): RedirectResponse
    {
        $this->authorize('suspend', $seller);

        $this->sellerService->suspendSeller($seller, (string) $request->validated('reason'), $request->user());

        return redirect()
            ->route('admin.sellers.show', ['seller' => $seller, 'tab' => 'overview'])
            ->with('success', "Merchant '{$seller->business_name}' has been suspended. Public storefronts and catalogue hidden.");
    }

    /**
     * Reactivate suspended seller account.
     */
    public function reactivate(Request $request, Seller $seller): RedirectResponse
    {
        $this->authorize('reactivate', $seller);

        $this->sellerService->reactivateSeller($seller, $request->input('reason'), $request->user());

        return redirect()
            ->route('admin.sellers.show', ['seller' => $seller, 'tab' => 'overview'])
            ->with('success', "Merchant '{$seller->business_name}' reactivated. Storefronts and catalogue restored.");
    }

    /**
     * Update custom seller commission override percentage.
     */
    public function updateCommission(UpdateSellerCommissionRequest $request, Seller $seller): RedirectResponse
    {
        $this->authorize('updateCommission', $seller);

        $override = $request->validated('commission_override') !== null
            ? (float) $request->validated('commission_override')
            : null;

        $this->sellerService->updateCommissionOverride(
            $seller,
            $override,
            $request->validated('remarks'),
            $request->user()
        );

        return redirect()
            ->route('admin.sellers.show', ['seller' => $seller, 'tab' => 'commission'])
            ->with('success', 'Commission override updated successfully.');
    }

    /**
     * Review individual KYC document (approve or reject with remarks).
     */
    public function reviewDocument(ReviewSellerDocumentRequest $request, Seller $seller, SellerDocument $document): RedirectResponse
    {
        $this->authorize('reviewDocument', $seller);

        $this->sellerService->reviewDocument(
            $document,
            (string) $request->validated('status'),
            $request->validated('remarks'),
            $request->user()
        );

        return redirect()
            ->route('admin.sellers.show', ['seller' => $seller, 'tab' => 'documents'])
            ->with('success', "KYC Document '{$document->type->label()}' marked as " . ucfirst((string) $request->validated('status')) . '.');
    }

    /**
     * Verify or unverify seller bank account.
     */
    public function verifyBankAccount(Request $request, Seller $seller, SellerBankAccount $bankAccount): RedirectResponse
    {
        $this->authorize('verifyBank', $seller);

        $verified = filter_var($request->input('verified', true), FILTER_VALIDATE_BOOLEAN);

        $this->sellerService->verifyBankAccount($bankAccount, $verified, $request->user());

        $msg = $verified ? 'Bank account verified successfully for payouts.' : 'Bank account unverified.';

        return redirect()
            ->route('admin.sellers.show', ['seller' => $seller, 'tab' => 'bank'])
            ->with('success', $msg);
    }

    /**
     * Perform bulk actions on multiple merchants.
     */
    public function bulkAction(BulkSellerActionRequest $request): RedirectResponse
    {
        $sellerIds = (array) $request->validated('seller_ids');
        $action = (string) $request->validated('action');
        $reason = $request->validated('reason');

        $processed = $this->sellerService->performBulkAction($sellerIds, $action, $reason, $request->user());

        return redirect()
            ->route('admin.sellers.index')
            ->with('success', "Bulk {$action} action applied to {$processed} merchant(s).");
    }
}
