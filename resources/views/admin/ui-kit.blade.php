@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'UI Kit & Component Showcase'])

@section('content')
<div class="space-y-12 max-w-7xl mx-auto pb-16">
    <!-- Component 1: Page Header -->
    <x-page-header 
        title="OneStall Design System & UI Kit" 
        subtitle="Visual test bench for all 22 reusable Blade + Tailwind CSS + Alpine.js components. Responsive down to 360px."
        :breadcrumbs="['Command Central' => route('admin.dashboard'), 'Design System' => route('admin.uikit')]"
    >
        <x-slot name="badge">
            <x-badge variant="primary" dot pulse size="sm">22 Components Active</x-badge>
        </x-slot>

        <x-slot name="actions">
            <x-button variant="amber" size="sm" @click="$dispatch('toast', { message: 'Interactive toast dispatched via Alpine!', type: 'success' })">
                Trigger Toast
            </x-button>
            <x-button variant="primary" size="sm" @click="$dispatch('open-modal', 'demo-modal')">
                Open Demo Modal
            </x-button>
        </x-slot>
    </x-page-header>

    <!-- Component 2: Stat Cards Grid -->
    <section class="space-y-4">
        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            01. Stat Cards (`x-stat-card`)
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <x-stat-card 
                label="Gross Merchandise Value" 
                value="₹ 14,82,500.00" 
                change="+18.4%" 
                changeType="up"
                badge="Platform Total"
                color="primary" 
            />

            <x-stat-card 
                label="10-30m Quick Deliveries" 
                value="1,420" 
                change="+24.1%" 
                changeType="up"
                badge="⚡ Blinkit Mode"
                color="amber" 
            />

            <x-stat-card 
                label="Connected Physical Stores" 
                value="89 Stores" 
                change="3 pending KYC" 
                changeType="neutral"
                badge="Local POS"
                color="emerald" 
            />

            <x-stat-card 
                label="Cargo Linehaul Waybills" 
                value="42 Consignments" 
                change="-2.1% transit delays" 
                changeType="down"
                badge="OneStall Cargo"
                color="rose" 
            />
        </div>
    </section>

    <!-- Component 3: Buttons & Badges -->
    <section class="space-y-4">
        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            02. Buttons (`x-button`) & Badges (`x-badge`)
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Buttons Matrix -->
            <x-card title="Buttons (Variants, Sizes & States)">
                <div class="space-y-4">
                    <div>
                        <span class="text-xs font-semibold text-slate-400 block mb-2">Color Variants:</span>
                        <div class="flex flex-wrap gap-2.5 items-center">
                            <x-button variant="primary">Primary</x-button>
                            <x-button variant="secondary">Secondary</x-button>
                            <x-button variant="amber">Amber / Quick</x-button>
                            <x-button variant="danger">Danger</x-button>
                            <x-button variant="outline">Outline</x-button>
                            <x-button variant="ghost">Ghost</x-button>
                        </div>
                    </div>

                    <div class="pt-2">
                        <span class="text-xs font-semibold text-slate-400 block mb-2">Sizes & Loading Spinner:</span>
                        <div class="flex flex-wrap gap-2.5 items-center">
                            <x-button size="sm" variant="primary">Small (sm)</x-button>
                            <x-button size="md" variant="primary">Medium (md)</x-button>
                            <x-button size="lg" variant="primary">Large (lg)</x-button>
                            <x-button variant="primary" :loading="true">Processing</x-button>
                        </div>
                    </div>
                </div>
            </x-card>

            <!-- Badges Matrix -->
            <x-card title="Badges (Status & Dot Variants)">
                <div class="space-y-4">
                    <div>
                        <span class="text-xs font-semibold text-slate-400 block mb-2">Status Badges:</span>
                        <div class="flex flex-wrap gap-2 items-center">
                            <x-badge variant="primary">Primary</x-badge>
                            <x-badge variant="success">Delivered (Success)</x-badge>
                            <x-badge variant="warning">Packing (Warning)</x-badge>
                            <x-badge variant="danger">Failed (Danger)</x-badge>
                            <x-badge variant="info">In-Transit (Info)</x-badge>
                            <x-badge variant="neutral">Draft (Neutral)</x-badge>
                        </div>
                    </div>

                    <div class="pt-2">
                        <span class="text-xs font-semibold text-slate-400 block mb-2">Animated Pulse Indicators:</span>
                        <div class="flex flex-wrap gap-2 items-center">
                            <x-badge variant="success" dot pulse>Live Storefront</x-badge>
                            <x-badge variant="warning" dot pulse>10m Quick SLA</x-badge>
                            <x-badge variant="danger" dot>RTO Initiated</x-badge>
                            <x-badge variant="primary" dot size="sm">Small Dot</x-badge>
                        </div>
                    </div>
                </div>
            </x-card>
        </div>
    </section>

    <!-- Component 4: Alerts & Avatars -->
    <section class="space-y-4">
        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            03. Alerts (`x-alert`) & Avatars (`x-avatar`)
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Alerts Stack -->
            <div class="md:col-span-2 space-y-3">
                <x-alert type="info" title="Fulfilment Route Optimized" dismissible>
                    Algorithm resolved nearest Dark Store #DS-102 (1.2 km distance) for 14-minute guaranteed dispatch.
                </x-alert>
                <x-alert type="success" title="Payment Captured & Reconciled" dismissible>
                    Razorpay webhook verified. Settlement ledger created with 10% platform commission retained.
                </x-alert>
                <x-alert type="warning" title="Quick Delivery Surge Active" dismissible>
                    High order density detected in Indiranagar cluster. Surge radius capped to 6.5 km.
                </x-alert>
                <x-alert type="danger" title="Warehouse Stock Threshold Breached" dismissible>
                    Safety stock level reached for SKU #OS-7712. Automated purchase order draft created.
                </x-alert>
            </div>

            <!-- Avatars Showcase -->
            <x-card title="Avatars (`x-avatar`)">
                <div class="space-y-4">
                    <div>
                        <span class="text-xs font-semibold text-slate-400 block mb-2">Sizes & Status Dots:</span>
                        <div class="flex items-center space-x-3">
                            <x-avatar name="Priya Sharma" size="xs" status="online" />
                            <x-avatar name="Anand Verma" size="sm" status="online" />
                            <x-avatar name="Rahul Dravid" size="md" status="busy" />
                            <x-avatar name="OneStall Admin" size="lg" status="online" />
                            <x-avatar name="Kiran Rao" size="xl" status="offline" />
                        </div>
                    </div>
                    <div class="pt-2 text-xs text-slate-500 dark:text-slate-400">
                        Supports automatic 2-letter initials extraction, external image URLs, online/busy/offline status rings, and rounded-xl shapes.
                    </div>
                </div>
            </x-card>
        </div>
    </section>

    <!-- Component 5: Form Inputs & Controls -->
    <section class="space-y-4">
        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            04. Form Controls (`x-input`, `x-select`, `x-textarea`, `x-checkbox`, `x-toggle`)
        </h2>
        <x-card title="Interactive Form Controls">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Inputs -->
                <div class="space-y-4">
                    <x-input 
                        label="Store Name" 
                        name="sample_store"
                        value="Apex Retail Indiranagar" 
                        required
                        helper="Visible on customer storefront"
                    />

                    <x-input 
                        label="Quick Delivery Search" 
                        name="sample_search"
                        placeholder="Search items or pincode..." 
                    >
                        <x-slot name="prefixIcon">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </x-slot>
                    </x-input>

                    <x-input 
                        label="GSTIN Identification" 
                        name="sample_gstin"
                        value="29AAAAA0000A1Z5" 
                        error="GSTIN verification check failed with Tax Portal."
                    />
                </div>

                <!-- Select & Textarea -->
                <div class="space-y-4">
                    <x-select 
                        label="Fulfilment Strategy" 
                        name="sample_fulfilment"
                        required
                        :options="[
                            'physical_store' => 'Physical Store Connect (10-30m)',
                            'seller_warehouse' => 'Seller Direct Warehouse',
                            'onestall_hub' => 'OneStall Regional Logistics Center',
                            'cargo_freight' => 'OneStall Cargo Heavy Fleet',
                        ]"
                        selected="physical_store"
                    />

                    <x-textarea 
                        label="Store Operating Instructions" 
                        name="sample_instructions"
                        rows="3"
                        placeholder="Provide rider pickup directions, landmark, or gate entry notes..."
                        helper="Instructions sent to quick commerce riders."
                    />
                </div>

                <!-- Toggles & Checkboxes -->
                <div class="space-y-5 p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">Switches & Options</h4>

                    <x-toggle 
                        label="Quick Commerce 10m Mode" 
                        description="Auto-accept orders within 8 km radius" 
                        :checked="true"
                    />

                    <x-toggle 
                        label="Cargo Freight Auto-Manifest" 
                        description="Generate waybills upon packaging" 
                        :checked="false"
                    />

                    <div class="pt-2 space-y-2.5">
                        <x-checkbox 
                            label="Bi-directional POS Synchronization" 
                            description="Keep physical shelf inventory in sync" 
                            :checked="true"
                        />
                        <x-checkbox 
                            label="Customer SMS/WhatsApp Order Alerts" 
                            description="Heavy queued notifications" 
                            :checked="true"
                        />
                    </div>
                </div>
            </div>
        </x-card>
    </section>

    <!-- Component 6: File Upload & Drag-and-Drop -->
    <section class="space-y-4">
        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            05. File Upload (`x-file-upload`)
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <x-card title="Store Logo & Storefront Banner">
                <x-file-upload 
                    name="store_banner" 
                    label="Storefront Cover Image" 
                    hint="PNG, JPG, WebP up to 5MB (1200x400 recommended)"
                />
            </x-card>

            <x-card title="KYC Document Upload">
                <x-file-upload 
                    name="fssai_cert" 
                    label="FSSAI License / GST Certificate" 
                    hint="PDF or High-resolution image up to 10MB"
                    accept=".pdf,image/*"
                />
            </x-card>
        </div>
    </section>

    <!-- Component 7: Tabs & Interactive Dropdowns -->
    <section class="space-y-4">
        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            06. Tabs (`x-tabs`) & Dropdowns (`x-dropdown`)
        </h2>
        <x-card title="Multi-Tab Navigation (Pills & Underline)">
            <x-tabs 
                :tabs="[
                    'orders' => ['label' => 'Instant Orders', 'badge' => '8 Active'],
                    'inventory' => ['label' => 'Inventory Matrix'],
                    'cargo' => ['label' => 'Cargo Fleet Dispatch'],
                ]"
                variant="pills"
            >
                <div x-show="currentTab === 'orders'" class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800 text-xs">
                    <p class="font-bold text-slate-900 dark:text-white">Active Instant Orders Tab Content</p>
                    <p class="text-slate-500 dark:text-slate-400 mt-1">Orders dispatched within 10-30m window via local physical store connect.</p>
                </div>
                <div x-show="currentTab === 'inventory'" class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800 text-xs">
                    <p class="font-bold text-slate-900 dark:text-white">Stock Allocation Across Stores</p>
                    <p class="text-slate-500 dark:text-slate-400 mt-1">Multi-location stock availability managed under isolated tenant scopes.</p>
                </div>
                <div x-show="currentTab === 'cargo'" class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800 text-xs">
                    <p class="font-bold text-slate-900 dark:text-white">OneStall Cargo Linehaul Tracking</p>
                    <p class="text-slate-500 dark:text-slate-400 mt-1">Heavy bulk consignments with automated waybill manifests.</p>
                </div>
            </x-tabs>
        </x-card>
    </section>

    <!-- Component 8: Data Table & Pagination -->
    <section class="space-y-4">
        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            07. Data Table (`x-table`) & Pagination (`x-pagination`)
        </h2>
        <x-table 
            :headers="['Order ID', 'Fulfilment Channel', 'Items & Value', 'Destination', 'SLA / Status', 'Action']"
            striped
        >
            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition">
                <td class="px-6 py-4 font-mono font-bold text-xs text-indigo-600 dark:text-indigo-400">
                    #OS-202611-001
                </td>
                <td class="px-6 py-4">
                    <x-badge variant="warning" dot size="sm">Quick Commerce</x-badge>
                    <span class="block text-[10px] text-slate-400 mt-0.5">Physical Store Indiranagar</span>
                </td>
                <td class="px-6 py-4">
                    <div class="font-bold text-slate-900 dark:text-white">₹ 840.00</div>
                    <div class="text-[11px] text-slate-400">3 SKUs (Dairy & Grocery)</div>
                </td>
                <td class="px-6 py-4 text-xs">
                    Indiranagar 2nd Stage (1.4 km)
                </td>
                <td class="px-6 py-4">
                    <span class="font-bold text-emerald-500 text-xs">11 mins left</span>
                </td>
                <td class="px-6 py-4 text-right">
                    <x-confirm-delete 
                        title="Cancel Order #OS-202611-001?"
                        message="Cancelling this order will release reserved stock back to the physical store pool."
                        triggerText="Cancel"
                    />
                </td>
            </tr>

            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition">
                <td class="px-6 py-4 font-mono font-bold text-xs text-indigo-600 dark:text-indigo-400">
                    #OS-202611-002
                </td>
                <td class="px-6 py-4">
                    <x-badge variant="info" size="sm">Cargo Logistics</x-badge>
                    <span class="block text-[10px] text-slate-400 mt-0.5">Consignment OSC-8192</span>
                </td>
                <td class="px-6 py-4">
                    <div class="font-bold text-slate-900 dark:text-white">₹ 34,500.00</div>
                    <div class="text-[11px] text-slate-400">12 Parcels (Bulk Kitchenware)</div>
                </td>
                <td class="px-6 py-4 text-xs">
                    Mumbai Central Linehaul Hub
                </td>
                <td class="px-6 py-4">
                    <x-badge variant="info" dot size="sm">In-Transit</x-badge>
                </td>
                <td class="px-6 py-4 text-right">
                    <x-button variant="outline" size="sm">Track</x-button>
                </td>
            </tr>
        </x-table>

        <x-pagination />
    </section>

    <!-- Component 9: Chart.js Wrapper Component -->
    <section class="space-y-4">
        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            08. Chart.js Wrapper (`x-chart`)
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <x-card title="Revenue Split (Quick Commerce vs Cargo)" subtitle="Live telemetry">
                <x-chart 
                    id="chartRevenueDemo"
                    type="bar"
                    :labels="['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']"
                    :datasets="[
                        [
                            'label' => 'Quick Commerce (Instant)',
                            'data' => [42000, 56000, 68000, 61000, 78000, 112000, 125000],
                            'backgroundColor' => '#f59e0b',
                            'borderRadius' => 6,
                        ],
                        [
                            'label' => 'OneStall Cargo (Freight)',
                            'data' => [85000, 92000, 78000, 105000, 110000, 94000, 89000],
                            'backgroundColor' => '#6366f1',
                            'borderRadius' => 6,
                        ]
                    ]"
                />
            </x-card>

            <x-card title="10-30 Minute Delivery SLA Adherence" subtitle="Percentage completed under 20 mins">
                <x-chart 
                    id="chartSlaDemo"
                    type="line"
                    :labels="['W1', 'W2', 'W3', 'W4']"
                    :datasets="[
                        [
                            'label' => 'On-Time SLA %',
                            'data' => [94.5, 96.2, 97.8, 98.4],
                            'borderColor' => '#10b981',
                            'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                            'fill' => true,
                            'tension' => 0.4,
                        ]
                    ]"
                />
            </x-card>
        </div>
    </section>

    <!-- Component 10: Empty State & Skeleton Loaders -->
    <section class="space-y-4">
        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            09. Empty State (`x-empty-state`) & Skeletons (`x-skeleton`)
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
            <x-empty-state 
                title="No Pending Payout Settlements" 
                description="All completed marketplace and quick commerce orders have been reconciled and processed into your merchant bank account."
            >
                <x-button variant="primary" size="sm">
                    View Settlement History &rarr;
                </x-button>
            </x-empty-state>

            <x-card title="Skeleton Loading Placeholders">
                <div class="space-y-4">
                    <span class="text-xs text-slate-400 block">Card & Text Shimmer:</span>
                    <x-skeleton type="text" :lines="3" />

                    <span class="text-xs text-slate-400 block pt-2">Circular Avatar & Blocks:</span>
                    <div class="flex items-center space-x-3">
                        <x-skeleton type="circle" width="w-10" height="h-10" />
                        <div class="flex-1 space-y-1.5">
                            <x-skeleton width="w-1/3" height="h-3" />
                            <x-skeleton width="w-2/3" height="h-2.5" />
                        </div>
                    </div>
                </div>
            </x-card>
        </div>
    </section>
</div>

<!-- Interactive Demo Modal -->
<x-modal name="demo-modal" title="OneStall Modal Component" size="md">
    <div class="space-y-3 text-xs leading-relaxed text-slate-600 dark:text-slate-300">
        <p>This modal is rendered with Alpine.js and features smooth CSS opacity/scale transitions, backdrop blur, and handles closing via backdrop clicks and the <kbd class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 font-mono text-[10px]">Esc</kbd> key.</p>
        <x-alert type="info">
            All heavy modals support arbitrary Blade components, forms, and slot actions.
        </x-alert>
    </div>

    <x-slot name="footer">
        <x-button variant="secondary" size="sm" @click="$dispatch('close-modal', 'demo-modal')">
            Close Modal
        </x-button>
        <x-button variant="primary" size="sm" @click="$dispatch('close-modal', 'demo-modal'); $dispatch('toast', { message: 'Action saved!', type: 'success' })">
            Save Changes
        </x-button>
    </x-slot>
</x-modal>
@endsection
