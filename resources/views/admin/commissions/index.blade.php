@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Commission Rules & Simulator'])

@section('content')
<div class="space-y-6" x-data="commissionSimulator()">

    <!-- Page Header -->
    <x-page-header 
        title="Commissions Engine &amp; Governance" 
        subtitle="Manage tiered marketplace commission rules, seller overrides, promotional fee windows, and simulate dynamic fee calculations."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Commission Rules' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                {{ $rules->total() }} Active Rules
            </span>
        </x-slot>

        <x-slot name="actions">
            <button 
                @click="openCreateModal = true" 
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm transition flex items-center space-x-1.5"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Add Commission Rule</span>
            </button>
        </x-slot>
    </x-page-header>

    <!-- Top Grid: Interactive Simulator & System Defaults -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Live Interactive Simulator Widget (Span 2) -->
        <div class="lg:col-span-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm p-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">
                        ⚡
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">Live Commission Engine Simulator</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Evaluate winning rule priority: Seller Override &gt; Promotional Window &gt; Category Rule &gt; Global Default</p>
                    </div>
                </div>
                <span class="px-2.5 py-1 rounded-full text-[10px] font-mono font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400">
                    Real-time
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-5">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Target Seller</label>
                    <select x-model="simSellerId" @change="runSimulation()" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                        <option value="">Any Seller (No override)</option>
                        @foreach($sellers as $s)
                            <option value="{{ $s->id }}">{{ $s->store_name }} (#{{ $s->id }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Product Category</label>
                    <select x-model="simCategoryId" @change="runSimulation()" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                        <option value="">Any Category</option>
                        @foreach($categories as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Order Base Amount (₹)</label>
                    <input type="number" step="10" x-model.number="simAmount" @input.debounce.300ms="runSimulation()" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500" placeholder="1000.00">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Shipping Adjustment (₹)</label>
                    <input type="number" step="5" x-model.number="simShipping" @input.debounce.300ms="runSimulation()" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500" placeholder="0.00">
                </div>
            </div>

            <!-- Simulation Calculation Output -->
            <div class="mt-6 p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center space-x-2">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Winning Rule:</span>
                        <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400" x-text="simResult.rule_name || 'System Default'"></span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300" x-text="simResult.tier_label || 'Default'"></span>
                    </div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        Commission: <strong class="text-slate-900 dark:text-white" x-text="(simResult.rate_percent || 0) + '% + ₹' + (simResult.fixed_fee || 0)"></strong>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 pt-3 border-t border-slate-200 dark:border-slate-700">
                    <div class="p-2.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                        <div class="text-[11px] text-slate-500">Gross Sale</div>
                        <div class="text-sm font-bold font-mono text-slate-900 dark:text-white" x-text="simResult.formatted?.base_amount || '₹0.00'"></div>
                    </div>
                    <div class="p-2.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                        <div class="text-[11px] text-rose-500">Platform Commission</div>
                        <div class="text-sm font-bold font-mono text-rose-600 dark:text-rose-400" x-text="'- ' + (simResult.formatted?.commission_amount || '₹0.00')"></div>
                    </div>
                    <div class="p-2.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                        <div class="text-[11px] text-rose-500">GST on Comm ({{ $commissionGstPct }}%)</div>
                        <div class="text-sm font-bold font-mono text-rose-600 dark:text-rose-400" x-text="'- ' + (simResult.formatted?.tax_on_commission || '₹0.00')"></div>
                    </div>
                    <div class="p-2.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800">
                        <div class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold">Net Seller Payable</div>
                        <div class="text-sm font-bold font-mono text-emerald-700 dark:text-emerald-300" x-text="simResult.formatted?.net_payable || '₹0.00'"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Global Platform Defaults Card -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm p-6 flex flex-col justify-between">
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-1">Global Fallbacks &amp; Taxes</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Values configured dynamically via database platform settings.</p>

                <div class="space-y-3">
                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                        <span class="text-xs text-slate-600 dark:text-slate-400">Default Commission</span>
                        <span class="text-xs font-bold font-mono text-slate-900 dark:text-white">{{ $defaultCommissionPct }}%</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                        <span class="text-xs text-slate-600 dark:text-slate-400">GST on Commission</span>
                        <span class="text-xs font-bold font-mono text-slate-900 dark:text-white">{{ $commissionGstPct }}%</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                        <span class="text-xs text-slate-600 dark:text-slate-400">Gateway Fee Allowance</span>
                        <span class="text-xs font-bold font-mono text-slate-900 dark:text-white">{{ $paymentGatewayFeePct }}%</span>
                    </div>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800">
                <a href="{{ route('admin.settings.index') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline flex items-center space-x-1 font-medium">
                    <span>Manage Settings in System Console</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
        </div>
    </div>

    <!-- Filter & Scope Bar -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <div class="flex items-center space-x-2">
            @php
                $currentScope = request('scope', 'all');
            @endphp
            <a href="{{ route('admin.commissions.index', ['scope' => 'all']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $currentScope === 'all' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                All Scopes
            </a>
            <a href="{{ route('admin.commissions.index', ['scope' => 'seller']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $currentScope === 'seller' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                Seller Overrides
            </a>
            <a href="{{ route('admin.commissions.index', ['scope' => 'promo']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $currentScope === 'promo' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                Promotional Windows
            </a>
            <a href="{{ route('admin.commissions.index', ['scope' => 'category']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $currentScope === 'category' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                Category Specific
            </a>
            <a href="{{ route('admin.commissions.index', ['scope' => 'global']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium {{ $currentScope === 'global' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                Global Rules
            </a>
        </div>

        <form method="GET" action="{{ route('admin.commissions.index') }}" class="flex items-center space-x-2">
            <input type="hidden" name="scope" value="{{ $currentScope }}">
            <input 
                type="text" 
                name="search" 
                value="{{ request('search') }}" 
                placeholder="Search rule name..." 
                class="text-xs px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500 w-48"
            >
            <button type="submit" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-lg text-xs font-medium">Search</button>
        </form>
    </div>

    <!-- Rules Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Priority</th>
                        <th class="px-6 py-3.5">Rule Name &amp; Scope</th>
                        <th class="px-6 py-3.5">Target Entity</th>
                        <th class="px-6 py-3.5">Commission Rate</th>
                        <th class="px-6 py-3.5">Fixed Fee</th>
                        <th class="px-6 py-3.5">Active Period</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($rules as $rule)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        <td class="px-6 py-4 font-mono font-bold text-slate-600 dark:text-slate-400">
                            #{{ $rule->priority }}
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-bold text-slate-900 dark:text-white">{{ $rule->name }}</div>
                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase mt-1
                                @if($rule->scope->value === 'seller') bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300
                                @elseif($rule->scope->value === 'promo') bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300
                                @elseif($rule->scope->value === 'category') bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300
                                @else bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300
                                @endif
                            ">
                                {{ $rule->scope->label() }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @if($rule->scope->value === 'seller')
                                <div class="font-medium text-slate-900 dark:text-white">{{ $rule->seller?->store_name ?? 'N/A' }}</div>
                                <div class="text-[10px] text-slate-400">Seller ID #{{ $rule->seller_id }}</div>
                            @elseif($rule->scope->value === 'category')
                                <div class="font-medium text-slate-900 dark:text-white">{{ $rule->category?->name ?? 'N/A' }}</div>
                                <div class="text-[10px] text-slate-400">Category ID #{{ $rule->category_id }}</div>
                            @elseif($rule->scope->value === 'promo')
                                <div class="text-amber-600 dark:text-amber-400 font-medium">All Eligible Orders</div>
                            @else
                                <div class="text-slate-500 dark:text-slate-400 font-medium">Platform Default</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 font-mono font-bold text-indigo-600 dark:text-indigo-400 text-sm">
                            {{ $rule->percent }}%
                        </td>
                        <td class="px-6 py-4 font-mono text-slate-700 dark:text-slate-300">
                            {{ format_currency((float)$rule->fixed_fee) }}
                        </td>
                        <td class="px-6 py-4 text-slate-500 dark:text-slate-400">
                            @if($rule->starts_at || $rule->ends_at)
                                <div>{{ $rule->starts_at ? $rule->starts_at->format('d M Y') : 'Start' }} &rarr; {{ $rule->ends_at ? $rule->ends_at->format('d M Y') : 'Indefinite' }}</div>
                            @else
                                <span class="text-slate-400">Permanent</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ $rule->is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' }}">
                                {{ $rule->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <button 
                                @click="openEditModal({{ json_encode($rule) }})" 
                                class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition"
                                title="Edit Rule"
                            >
                                <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <form method="POST" action="{{ route('admin.commissions.destroy', $rule) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete this commission rule?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 transition" title="Delete Rule">
                                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                            No commission rules found matching your filters.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800">
            {{ $rules->links() }}
        </div>
    </div>

    <!-- Create/Edit Modal with Alpine.js -->
    <div x-show="openCreateModal || editingRule !== null" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div @click.away="closeModal()" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl w-full max-w-lg p-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                <h3 class="text-base font-bold text-slate-900 dark:text-white" x-text="editingRule ? 'Edit Commission Rule' : 'Create New Commission Rule'"></h3>
                <button @click="closeModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form :action="editingRule ? ('/admin/commissions/' + editingRule.id) : '{{ route('admin.commissions.store') }}'" method="POST" class="mt-4 space-y-4">
                @csrf
                <template x-if="editingRule">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Rule Name</label>
                    <input type="text" name="name" x-model="form.name" required class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500" placeholder="e.g. Electronics Promotional Q4">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Scope</label>
                        <select name="scope" x-model="form.scope" required class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                            <option value="global">Global Default</option>
                            <option value="category">Category Specific</option>
                            <option value="seller">Seller Specific</option>
                            <option value="promo">Promotional Window</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Priority (0-999)</label>
                        <input type="number" name="priority" x-model="form.priority" required min="0" max="999" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                    </div>
                </div>

                <div x-show="form.scope === 'seller'" class="space-y-1">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Target Seller</label>
                    <select name="seller_id" x-model="form.seller_id" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                        <option value="">Select Seller</option>
                        @foreach($sellers as $s)
                            <option value="{{ $s->id }}">{{ $s->store_name }} (#{{ $s->id }})</option>
                        @endforeach
                    </select>
                </div>

                <div x-show="form.scope === 'category'" class="space-y-1">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Target Category</label>
                    <select name="category_id" x-model="form.category_id" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                        <option value="">Select Category</option>
                        @foreach($categories as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Commission Rate (%)</label>
                        <input type="number" step="0.01" name="percent" x-model="form.percent" required min="0" max="100" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Fixed Fee (₹)</label>
                        <input type="number" step="0.01" name="fixed_fee" x-model="form.fixed_fee" min="0" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500" placeholder="0.00">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Starts At (Optional)</label>
                        <input type="datetime-local" name="starts_at" x-model="form.starts_at" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Ends At (Optional)</label>
                        <input type="datetime-local" name="ends_at" x-model="form.ends_at" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                    </div>
                </div>

                <div class="flex items-center space-x-2 pt-2">
                    <input type="checkbox" id="is_active_box" name="is_active" value="1" x-model="form.is_active" class="rounded border-slate-300 dark:border-slate-700 text-indigo-600 focus:ring-indigo-500">
                    <label for="is_active_box" class="text-xs font-medium text-slate-700 dark:text-slate-300">Rule is active immediately</label>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" @click="closeModal()" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow-sm">
                        Save Rule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function commissionSimulator() {
    return {
        openCreateModal: false,
        editingRule: null,
        simSellerId: '',
        simCategoryId: '',
        simAmount: 1000.00,
        simShipping: 0.00,
        simResult: {},
        form: {
            name: '',
            scope: 'global',
            category_id: '',
            seller_id: '',
            percent: 10.00,
            fixed_fee: 0.00,
            starts_at: '',
            ends_at: '',
            priority: 10,
            is_active: true
        },
        init() {
            this.runSimulation();
        },
        runSimulation() {
            fetch('{{ route('admin.commissions.simulate') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    seller_id: this.simSellerId || null,
                    category_id: this.simCategoryId || null,
                    amount: this.simAmount || 0,
                    shipping_adjustment: this.simShipping || 0
                })
            })
            .then(res => res.json())
            .then(data => {
                this.simResult = data;
            })
            .catch(err => console.error(err));
        },
        openEditModal(rule) {
            this.editingRule = rule;
            this.form = {
                name: rule.name,
                scope: rule.scope,
                category_id: rule.category_id || '',
                seller_id: rule.seller_id || '',
                percent: rule.percent,
                fixed_fee: rule.fixed_fee || 0.00,
                starts_at: rule.starts_at ? rule.starts_at.slice(0, 16) : '',
                ends_at: rule.ends_at ? rule.ends_at.slice(0, 16) : '',
                priority: rule.priority,
                is_active: Boolean(rule.is_active)
            };
        },
        closeModal() {
            this.openCreateModal = false;
            this.editingRule = null;
        }
    };
}
</script>
@endsection
