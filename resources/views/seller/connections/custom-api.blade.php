@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'Custom Website REST API'])

@section('content')
<div class="space-y-6" x-data="{ docTab: 'auth' }">
    <x-page-header 
        title="Custom Website REST API &amp; Webhooks" 
        subtitle="Integrate your custom e-commerce storefront, warehouse ERP, or POS via secure token &amp; HMAC authenticated REST endpoints."
        :breadcrumbs="['Seller Studio' => route('seller.dashboard'), 'Connect My Shop' => route('seller.connections.index'), 'Custom API' => '#']"
    />

    @if($plainKey && $plainSecret)
        <!-- One-Time Secret Alert Banner -->
        <div class="p-5 rounded-2xl bg-amber-500/10 border-2 border-amber-500/30 text-amber-900 dark:text-amber-200 space-y-3 animate-pulse-once">
            <div class="flex items-center space-x-2">
                <svg class="w-6 h-6 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <h3 class="font-bold text-sm">Save Your API Secret Now — Shown Once Only!</h3>
            </div>
            <p class="text-xs text-amber-700 dark:text-amber-300">
                For security, OneStall stores only the cryptographic SHA-256 hash of your API secret. This plain secret will never be displayed again.
            </p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1">
                <div class="p-3 rounded-xl bg-white dark:bg-slate-900 border border-amber-500/30">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Public API Key (X-Api-Key)</span>
                    <div class="flex items-center justify-between font-mono text-xs font-bold text-slate-900 dark:text-white">
                        <span>{{ $plainKey }}</span>
                        <button type="button" onclick="navigator.clipboard.writeText('{{ $plainKey }}'); alert('Key copied!');" class="text-xs text-indigo-500 hover:underline">Copy</button>
                    </div>
                </div>
                <div class="p-3 rounded-xl bg-white dark:bg-slate-900 border border-amber-500/30">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Private API Secret (X-Api-Secret)</span>
                    <div class="flex items-center justify-between font-mono text-xs font-bold text-emerald-600 dark:text-emerald-400">
                        <span>{{ $plainSecret }}</span>
                        <button type="button" onclick="navigator.clipboard.writeText('{{ $plainSecret }}'); alert('Secret copied!');" class="text-xs text-indigo-500 hover:underline">Copy</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- API Credentials Card -->
        <div class="lg:col-span-1 space-y-6">
            <x-card title="API Key Management">
                <form method="POST" action="{{ route('seller.connections.custom_api.generate') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Store Outlet <span class="text-rose-500">*</span></label>
                        <select name="store_id" required class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white font-semibold">
                            @foreach($stores as $st)
                                <option value="{{ $st->id }}" {{ $activeStore && $activeStore->id === $st->id ? 'selected' : '' }}>
                                    {{ $st->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if($connection && $connection->api_key)
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-xs space-y-2">
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Current Active API Key</span>
                                <span class="font-mono font-bold text-slate-800 dark:text-slate-200">{{ substr($connection->api_key, 0, 16) }}••••••••</span>
                            </div>
                            <div class="flex items-center justify-between text-[11px] text-slate-500">
                                <span>Status: <strong class="text-emerald-500">Active</strong></span>
                                <span>Rate limit: 60 req/min</span>
                            </div>
                        </div>
                    @endif

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">Authorized Scopes</label>
                        <div class="space-y-1.5 text-xs">
                            @php
                                $allScopes = [
                                    'products:read' => 'Read catalog products & variants',
                                    'products:write' => 'Create and update products & prices',
                                    'inventory:read' => 'Read stock balances and warehouses',
                                    'inventory:write' => 'Adjust quantity on hand for SKUs',
                                    'orders:read' => 'List and inspect incoming orders',
                                    'orders:write' => 'Advance fulfillment orders status',
                                ];
                                $activeScopes = $connection->config['scopes'] ?? array_keys($allScopes);
                            @endphp
                            @foreach($allScopes as $scope => $label)
                                <label class="flex items-center space-x-2 cursor-pointer">
                                    <input type="checkbox" name="scopes[]" value="{{ $scope }}" {{ in_array($scope, $activeScopes) ? 'checked' : '' }} class="rounded border-slate-300 text-amber-500 focus:ring-amber-500" />
                                    <span class="font-mono text-[11px] text-slate-700 dark:text-slate-300">{{ $scope }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-200 dark:border-slate-800">
                        <x-button variant="primary" size="sm" type="submit" class="w-full justify-center">
                            {{ $connection ? 'Regenerate API Key &amp; Secret' : 'Generate New API Credentials' }}
                        </x-button>
                    </div>
                </form>
            </x-card>

            @if($connection)
                <!-- Webhook Config Card -->
                <x-card title="Outgoing Webhooks">
                    <form method="POST" action="{{ route('seller.connections.custom_api.webhooks') }}" class="space-y-4">
                        @csrf

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Your Webhook URL (HTTPS)</label>
                            <input type="url" name="webhook_url" value="{{ old('webhook_url', $connection->webhook_url) }}" placeholder="https://api.mybrand.com/webhooks/onestall" required class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white font-mono" />
                            <span class="text-[10px] text-slate-400 mt-1 block">OneStall will POST order status events to this endpoint.</span>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Webhook Secret (HMAC Signing)</label>
                            <input type="text" name="webhook_secret" value="{{ old('webhook_secret', $connection->webhook_secret) }}" readonly class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3 py-2 font-mono text-slate-600 dark:text-slate-400" />
                        </div>

                        <x-button variant="outline" size="sm" type="submit" class="w-full justify-center">Save Webhook Settings</x-button>
                    </form>
                </x-card>
            @endif
        </div>

        <!-- Documentation & Endpoint Testing Console -->
        <div class="lg:col-span-2 space-y-6">
            <x-card title="Interactive REST API Documentation">
                <!-- Tabs -->
                <div class="flex items-center space-x-2 border-b border-slate-200 dark:border-slate-800 pb-3 mb-4 text-xs font-bold">
                    <button type="button" @click="docTab = 'auth'" :class="docTab === 'auth' ? 'text-amber-600 dark:text-amber-400 border-b-2 border-amber-500 pb-1' : 'text-slate-500 hover:text-slate-800'">
                        1. Authentication &amp; Headers
                    </button>
                    <button type="button" @click="docTab = 'products'" :class="docTab === 'products' ? 'text-amber-600 dark:text-amber-400 border-b-2 border-amber-500 pb-1' : 'text-slate-500 hover:text-slate-800'">
                        2. Products API
                    </button>
                    <button type="button" @click="docTab = 'inventory'" :class="docTab === 'inventory' ? 'text-amber-600 dark:text-amber-400 border-b-2 border-amber-500 pb-1' : 'text-slate-500 hover:text-slate-800'">
                        3. Inventory API
                    </button>
                    <button type="button" @click="docTab = 'orders'" :class="docTab === 'orders' ? 'text-amber-600 dark:text-amber-400 border-b-2 border-amber-500 pb-1' : 'text-slate-500 hover:text-slate-800'">
                        4. Orders API
                    </button>
                </div>

                <!-- Tab 1: Auth -->
                <div x-show="docTab === 'auth'" class="space-y-4 text-xs">
                    <p class="text-slate-600 dark:text-slate-400">
                        All requests to <code class="bg-slate-100 dark:bg-slate-800 px-1 py-0.5 rounded font-mono font-bold">/api/v1/integrations/*</code> require your API Key and Secret in HTTP headers.
                    </p>

                    <div class="p-3 rounded-xl bg-slate-900 text-slate-100 font-mono text-[11px] overflow-x-auto space-y-1">
                        <div><span class="text-amber-400">X-Api-Key:</span> {{ $connection?->api_key ?? 'osk_live_samplekey123' }}</div>
                        <div><span class="text-amber-400">X-Api-Secret:</span> oss_sec_your_secret_here</div>
                        <div><span class="text-slate-400"># Or pass as Bearer token:</span></div>
                        <div><span class="text-emerald-400">Authorization:</span> Bearer {{ $connection?->api_key ?? 'osk_live_samplekey123' }}:oss_sec_secret</div>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700 space-y-1 text-slate-600 dark:text-slate-400">
                        <div class="font-bold text-slate-900 dark:text-white">Rate Limits &amp; Throttling</div>
                        <div>Standard rate limit: <strong>60 requests per minute</strong> per API key. When exceeded, the API returns <code class="font-mono">HTTP 429 Too Many Requests</code> with a <code class="font-mono">Retry-After</code> header.</div>
                    </div>
                </div>

                <!-- Tab 2: Products -->
                <div x-show="docTab === 'products'" class="space-y-4 text-xs" style="display: none;">
                    <div>
                        <div class="flex items-center space-x-2 font-mono font-bold mb-1">
                            <span class="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-600 font-bold">GET</span>
                            <span>/api/v1/integrations/products</span>
                        </div>
                        <p class="text-slate-500">List all catalog items and SKUs with variant pricing.</p>
                        <pre class="mt-2 p-3 rounded-xl bg-slate-900 text-slate-100 font-mono text-[11px] overflow-x-auto">curl -X GET "{{ url('/api/v1/integrations/products') }}?per_page=20" \
  -H "X-Api-Key: {{ $connection?->api_key ?? 'osk_live_sample' }}" \
  -H "X-Api-Secret: oss_sec_sample"</pre>
                    </div>

                    <div class="pt-3 border-t border-slate-200 dark:border-slate-800">
                        <div class="flex items-center space-x-2 font-mono font-bold mb-1">
                            <span class="px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-600 font-bold">POST</span>
                            <span>/api/v1/integrations/products</span>
                        </div>
                        <p class="text-slate-500">Create or upsert a product with variant and initial stock.</p>
                        <pre class="mt-2 p-3 rounded-xl bg-slate-900 text-slate-100 font-mono text-[11px] overflow-x-auto">curl -X POST "{{ url('/api/v1/integrations/products') }}" \
  -H "X-Api-Key: {{ $connection?->api_key ?? 'osk_live_sample' }}" \
  -H "X-Api-Secret: oss_sec_sample" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Premium Wireless Noise-Cancelling Headphones",
    "sku": "AUDIO-NC-001",
    "selling_price": 4999.00,
    "mrp": 6999.00,
    "quantity": 25,
    "category": "Electronics"
  }'</pre>
                    </div>
                </div>

                <!-- Tab 3: Inventory -->
                <div x-show="docTab === 'inventory'" class="space-y-4 text-xs" style="display: none;">
                    <div>
                        <div class="flex items-center space-x-2 font-mono font-bold mb-1">
                            <span class="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-600 font-bold">GET</span>
                            <span>/api/v1/integrations/inventory</span>
                        </div>
                        <p class="text-slate-500">Fetch real-time stock balances across all warehouse shelves for this store.</p>
                    </div>

                    <div class="pt-3 border-t border-slate-200 dark:border-slate-800">
                        <div class="flex items-center space-x-2 font-mono font-bold mb-1">
                            <span class="px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-600 font-bold">POST</span>
                            <span>/api/v1/integrations/inventory/adjust</span>
                        </div>
                        <p class="text-slate-500">Directly set or adjust stock level for a SKU.</p>
                        <pre class="mt-2 p-3 rounded-xl bg-slate-900 text-slate-100 font-mono text-[11px] overflow-x-auto">curl -X POST "{{ url('/api/v1/integrations/inventory/adjust') }}" \
  -H "X-Api-Key: {{ $connection?->api_key ?? 'osk_live_sample' }}" \
  -H "X-Api-Secret: oss_sec_sample" \
  -H "Content-Type: application/json" \
  -d '{
    "sku": "AUDIO-NC-001",
    "quantity": 100,
    "reason": "Warehouse physical count reconciliation"
  }'</pre>
                    </div>
                </div>

                <!-- Tab 4: Orders -->
                <div x-show="docTab === 'orders'" class="space-y-4 text-xs" style="display: none;">
                    <div>
                        <div class="flex items-center space-x-2 font-mono font-bold mb-1">
                            <span class="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-600 font-bold">GET</span>
                            <span>/api/v1/integrations/orders</span>
                        </div>
                        <p class="text-slate-500">Query orders assigned to this store outlet (filter by status e.g. <code class="font-mono">?status=ready_to_ship</code>).</p>
                    </div>

                    <div class="pt-3 border-t border-slate-200 dark:border-slate-800">
                        <div class="flex items-center space-x-2 font-mono font-bold mb-1">
                            <span class="px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-600 font-bold">POST</span>
                            <span>/api/v1/integrations/orders/{id}/status</span>
                        </div>
                        <p class="text-slate-500">Update fulfillment package status (e.g. <code class="font-mono">packed</code>, <code class="font-mono">ready_to_ship</code>).</p>
                    </div>
                </div>
            </x-card>
        </div>
    </div>
</div>
@endsection
