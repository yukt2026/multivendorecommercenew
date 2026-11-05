<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Enums\StoreConnectionType;
use App\Http\Controllers\Controller;
use App\Jobs\Integration\ImportCatalogJob;
use App\Models\CatalogImport;
use App\Models\Store;
use App\Models\StoreConnection;
use App\Models\SyncLog;
use App\Services\Integration\CatalogImportService;
use App\Services\Integration\ConnectShopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ConnectionController extends Controller
{
    public function __construct(
        protected ConnectShopService $connectService,
        protected CatalogImportService $importService
    ) {}

    /**
     * Display Connect My Shop channel cards wizard.
     */
    public function index(Request $request): View
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);

        $stores = $seller->stores()->get();
        $activeStoreId = session('seller_active_store_id') ?: $stores->first()?->id;
        $activeStore = $activeStoreId ? $stores->firstWhere('id', $activeStoreId) : $stores->first();

        $connections = $this->connectService->getConnectionsSummary($seller, $activeStore?->id);
        $recentSyncs = SyncLog::query()
            ->whereHas('connection.store', fn ($q) => $q->where('seller_id', $seller->id))
            ->latest()
            ->limit(10)
            ->get();

        return view('seller.connections.index', [
            'seller' => $seller,
            'stores' => $stores,
            'activeStore' => $activeStore,
            'connections' => $connections,
            'recentSyncs' => $recentSyncs,
        ]);
    }

    /**
     * CSV/Excel catalog integration hub.
     */
    public function csvIndex(Request $request): View
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);

        $stores = $seller->stores()->get();
        $activeStore = $this->resolveActiveStore($seller);

        $imports = CatalogImport::where('seller_id', $seller->id)
            ->latest()
            ->paginate(10);

        return view('seller.connections.csv', [
            'seller' => $seller,
            'stores' => $stores,
            'activeStore' => $activeStore,
            'imports' => $imports,
        ]);
    }

    /**
     * Download sample CSV template.
     */
    public function csvTemplate(): Response
    {
        $csv = $this->importService->generateTemplateCsv();

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="onestall_catalog_template.csv"',
        ]);
    }

    /**
     * Upload CSV file for dry-run preview and column mapping.
     */
    public function csvUpload(Request $request): View|RedirectResponse
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
            'store_id' => 'required|exists:stores,id',
        ]);

        $store = $seller->stores()->findOrFail($request->input('store_id'));
        $previewData = $this->importService->uploadAndPreview($store, $request->file('csv_file'));

        return view('seller.connections.csv-mapping', [
            'seller' => $seller,
            'store' => $store,
            'import' => $previewData['import'],
            'headers' => $previewData['headers'],
            'previewRows' => $previewData['preview_rows'],
            'autoMapping' => $previewData['auto_mapping'],
            'totalRows' => $previewData['total_rows'],
        ]);
    }

    /**
     * Confirm column mapping and trigger import execution (or dry-run).
     */
    public function csvImport(Request $request, CatalogImport $import): RedirectResponse
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);
        abort_if($import->seller_id !== $seller->id, 403);

        $mapping = (array) $request->input('mapping', []);
        $isDryRun = $request->boolean('is_dry_run');

        if (!empty($mapping)) {
            $import->update(['column_mapping' => $mapping]);
        }

        if ($isDryRun) {
            $result = $this->importService->processImport($import, $mapping, true);
            return back()->with('info', "Dry run complete: {$result['imported']} valid rows, {$result['failed']} invalid rows.");
        }

        ImportCatalogJob::dispatch($import, $mapping);

        return redirect()->route('seller.connections.csv')
            ->with('success', "Queued bulk catalog import for {$import->file_name} ({$import->total_rows} rows). Progress will update live.");
    }

    /**
     * WooCommerce integration management console.
     */
    public function woocommerceIndex(Request $request): View
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);

        $stores = $seller->stores()->get();
        $activeStore = $this->resolveActiveStore($seller);

        $connection = StoreConnection::where('store_id', $activeStore?->id)
            ->where('type', StoreConnectionType::WOOCOMMERCE)
            ->first();

        $syncLogs = $connection ? $connection->syncLogs()->paginate(15) : collect();

        return view('seller.connections.woocommerce', [
            'seller' => $seller,
            'stores' => $stores,
            'activeStore' => $activeStore,
            'connection' => $connection,
            'syncLogs' => $syncLogs,
            'webhookUrl' => $connection ? url("/api/v1/integrations/woocommerce/webhook/{$connection->id}") : null,
        ]);
    }

    /**
     * Connect or save WooCommerce credentials.
     */
    public function woocommerceConnect(Request $request): RedirectResponse
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);

        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'store_url' => 'required|url|max:255',
            'consumer_key' => 'required|string|max:100',
            'consumer_secret' => 'required|string|max:100',
            'auto_sync_stock' => 'nullable|boolean',
            'auto_sync_orders' => 'nullable|boolean',
        ]);

        $store = $seller->stores()->findOrFail($validated['store_id']);
        $this->connectService->connectWooCommerce($store, $validated);

        return redirect()->route('seller.connections.woocommerce')
            ->with('success', 'WooCommerce store connected successfully! You can now test connection or sync products.');
    }

    /**
     * Test connection to WooCommerce store.
     */
    public function woocommerceTest(Request $request): JsonResponse|RedirectResponse
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);

        $activeStore = $this->resolveActiveStore($seller);
        $connection = StoreConnection::where('store_id', $activeStore?->id)
            ->where('type', StoreConnectionType::WOOCOMMERCE)
            ->firstOrFail();

        $result = $this->connectService->testWooCommerce($connection);

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Trigger WooCommerce catalog synchronization job.
     */
    public function woocommerceSync(Request $request): RedirectResponse
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);

        $activeStore = $this->resolveActiveStore($seller);
        $connection = StoreConnection::where('store_id', $activeStore?->id)
            ->where('type', StoreConnectionType::WOOCOMMERCE)
            ->firstOrFail();

        $result = $this->connectService->syncWooCommerceProducts($connection);

        return back()->with('success', $result['message']);
    }

    /**
     * Shopify integration console.
     */
    public function shopifyIndex(Request $request): View
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);

        $stores = $seller->stores()->get();
        $activeStore = $this->resolveActiveStore($seller);

        $connection = StoreConnection::where('store_id', $activeStore?->id)
            ->where('type', StoreConnectionType::SHOPIFY)
            ->first();

        $syncLogs = $connection ? $connection->syncLogs()->paginate(15) : collect();

        return view('seller.connections.shopify', [
            'seller' => $seller,
            'stores' => $stores,
            'activeStore' => $activeStore,
            'connection' => $connection,
            'syncLogs' => $syncLogs,
            'webhookUrl' => $connection ? url("/api/v1/integrations/shopify/webhook/{$connection->id}") : null,
        ]);
    }

    /**
     * Connect or save Shopify credentials.
     */
    public function shopifyConnect(Request $request): RedirectResponse
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);

        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'shop_domain' => 'required|string|max:255',
            'access_token' => 'required|string|max:255',
            'api_version' => 'nullable|string|max:20',
        ]);

        $store = $seller->stores()->findOrFail($validated['store_id']);
        $this->connectService->connectShopify($store, $validated);

        return redirect()->route('seller.connections.shopify')
            ->with('success', 'Shopify store connection saved successfully!');
    }

    /**
     * Test Shopify connection.
     */
    public function shopifyTest(Request $request): JsonResponse|RedirectResponse
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);

        $activeStore = $this->resolveActiveStore($seller);
        $connection = StoreConnection::where('store_id', $activeStore?->id)
            ->where('type', StoreConnectionType::SHOPIFY)
            ->firstOrFail();

        $result = $this->connectService->testShopify($connection);

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Trigger Shopify catalog sync.
     */
    public function shopifySync(Request $request): RedirectResponse
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);

        $activeStore = $this->resolveActiveStore($seller);
        $connection = StoreConnection::where('store_id', $activeStore?->id)
            ->where('type', StoreConnectionType::SHOPIFY)
            ->firstOrFail();

        $result = $this->connectService->syncShopifyProducts($connection);

        return back()->with('success', $result['message']);
    }

    /**
     * Custom Website REST API console and documentation.
     */
    public function customApiIndex(Request $request): View
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);

        $stores = $seller->stores()->get();
        $activeStore = $this->resolveActiveStore($seller);

        $connection = StoreConnection::where('store_id', $activeStore?->id)
            ->where('type', StoreConnectionType::CUSTOM_API)
            ->first();

        $syncLogs = $connection ? $connection->syncLogs()->paginate(15) : collect();

        return view('seller.connections.custom-api', [
            'seller' => $seller,
            'stores' => $stores,
            'activeStore' => $activeStore,
            'connection' => $connection,
            'syncLogs' => $syncLogs,
            'plainKey' => session('plain_api_key'),
            'plainSecret' => session('plain_api_secret'),
        ]);
    }

    /**
     * Generate or regenerate Custom Website API credentials.
     */
    public function customApiGenerate(Request $request): RedirectResponse
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);

        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'name' => 'nullable|string|max:100',
            'scopes' => 'nullable|array',
            'webhook_url' => 'nullable|url|max:500',
        ]);

        $store = $seller->stores()->findOrFail($validated['store_id']);
        $result = $this->connectService->generateCustomApiKey($store, $validated);

        return redirect()->route('seller.connections.custom_api')
            ->with([
                'success' => 'Custom API Key generated! Make sure to copy your API Secret now — it will not be shown again.',
                'plain_api_key' => $result['plain_key'],
                'plain_api_secret' => $result['plain_secret'],
            ]);
    }

    /**
     * Update webhook configuration for Custom API.
     */
    public function customApiWebhooks(Request $request): RedirectResponse
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);

        $activeStore = $this->resolveActiveStore($seller);
        $connection = StoreConnection::where('store_id', $activeStore?->id)
            ->where('type', StoreConnectionType::CUSTOM_API)
            ->firstOrFail();

        $validated = $request->validate([
            'webhook_url' => 'required|url|max:500',
            'webhook_secret' => 'nullable|string|max:100',
        ]);

        $this->connectService->updateCustomApiWebhooks($connection, $validated);

        return back()->with('success', 'Outgoing webhook settings saved successfully.');
    }

    /**
     * Retry a failed sync log entry.
     */
    public function retrySync(SyncLog $syncLog): RedirectResponse
    {
        $seller = Auth::user()->seller;
        abort_if(!$seller, 403);
        abort_if($syncLog->connection->store->seller_id !== $seller->id, 403);

        $result = $this->connectService->retrySyncLog($syncLog);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Helper to resolve active store from session.
     */
    protected function resolveActiveStore($seller): ?Store
    {
        $activeStoreId = session('seller_active_store_id');
        return $activeStoreId ? $seller->stores()->find($activeStoreId) : $seller->stores()->first();
    }
}
