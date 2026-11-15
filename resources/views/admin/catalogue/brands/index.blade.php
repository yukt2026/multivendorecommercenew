@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Brands Registry'])

@section('content')
<div class="space-y-6" x-data="brandManager()">
    <x-page-header 
        title="Brands Registry & Certification" 
        subtitle="Manage brand intellectual property, verified merchant authorizations, and consumer brand badges."
        :breadcrumbs="['Command Central' => route('admin.dashboard'), 'Catalogue' => '#', 'Brands' => '#']"
    >
        <x-slot name="badge">
            <x-badge variant="primary" size="sm">
                {{ $brands->total() }} Brands Live
            </x-badge>
        </x-slot>

        <x-slot name="actions">
            <button 
                type="button" 
                @click="openCreateModal()"
                class="inline-flex items-center px-4 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition"
            >
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Register New Brand
            </button>
        </x-slot>
    </x-page-header>

    <!-- Top Metric Summary Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Brands -->
        <a href="{{ route('admin.catalogue.brands.index') }}" class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs hover:border-indigo-300 dark:hover:border-indigo-700 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Brands</span>
                <span class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                </span>
            </div>
            <div class="mt-2 text-2xl font-black text-slate-900 dark:text-white font-mono">{{ $stats['total'] ?? $brands->total() }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Platform catalog registry</div>
        </a>

        <!-- Verified Brands -->
        <a href="{{ route('admin.catalogue.brands.index', array_merge($filters, ['verified' => '1'])) }}" class="p-4 rounded-2xl bg-white dark:bg-slate-900 border {{ ($filters['verified'] ?? '') === '1' ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-slate-200/80 dark:border-slate-800' }} shadow-xs hover:border-emerald-300 dark:hover:border-emerald-700 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Verified & Certified</span>
                <span class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </span>
            </div>
            <div class="mt-2 text-2xl font-black text-emerald-600 dark:text-emerald-400 font-mono">{{ $stats['verified'] ?? 0 }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Official certification badge</div>
        </a>

        <!-- Unverified Brands -->
        <a href="{{ route('admin.catalogue.brands.index', array_merge($filters, ['verified' => '0'])) }}" class="p-4 rounded-2xl bg-white dark:bg-slate-900 border {{ ($filters['verified'] ?? '') === '0' ? 'border-amber-500 ring-2 ring-amber-500/20' : 'border-slate-200/80 dark:border-slate-800' }} shadow-xs hover:border-amber-300 dark:hover:border-amber-700 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">Pending / Unverified</span>
                <span class="p-2 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </span>
            </div>
            <div class="mt-2 text-2xl font-black text-amber-600 dark:text-amber-400 font-mono">{{ $stats['unverified'] ?? 0 }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Require admin validation</div>
        </a>

        <!-- Merchant Owned Brands -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-purple-600 dark:text-purple-400">Merchant Partners</span>
                <span class="p-2 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </span>
            </div>
            <div class="mt-2 text-2xl font-black text-purple-600 dark:text-purple-400 font-mono">{{ $stats['merchant_owned'] ?? 0 }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Private merchant labels</div>
        </div>
    </div>

    <!-- Filters Bar Card -->
    <x-card padding="p-5">
        <form method="GET" action="{{ route('admin.catalogue.brands.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Search Brand</label>
                <input 
                    type="text" 
                    name="search" 
                    value="{{ $filters['search'] ?? '' }}" 
                    placeholder="Brand name or slug..." 
                    class="w-full py-2 px-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                />
            </div>

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Verification Status</label>
                <select name="verified" class="w-full py-2 px-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white">
                    <option value="">All Brands</option>
                    <option value="1" {{ ($filters['verified'] ?? '') === '1' ? 'selected' : '' }}>Verified Brands Only</option>
                    <option value="0" {{ ($filters['verified'] ?? '') === '0' ? 'selected' : '' }}>Unverified Brands</option>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Registered Merchant Owner</label>
                <select name="seller_id" class="w-full py-2 px-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white">
                    <option value="">Platform / All Sellers</option>
                    @foreach($sellers as $seller)
                        <option value="{{ $seller->id }}" {{ ($filters['seller_id'] ?? '') == $seller->id ? 'selected' : '' }}>{{ $seller->business_name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end space-x-2">
                <button type="submit" class="flex-1 py-2 px-3 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white transition">Filter</button>
                @if(array_filter($filters))
                    <a href="{{ route('admin.catalogue.brands.index') }}" class="py-2 px-3 text-xs font-semibold rounded-xl bg-slate-200 dark:bg-slate-750 text-slate-700 dark:text-slate-300 hover:bg-slate-300">Reset</a>
                @endif
            </div>
        </form>
    </x-card>

    <!-- Brands Table Card -->
    <x-card padding="p-0">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/40 text-slate-400 font-semibold uppercase">
                        <th class="py-3 px-6">Brand Details</th>
                        <th class="py-3 px-4">Slug Identifier</th>
                        <th class="py-3 px-4">Merchant Origin</th>
                        <th class="py-3 px-4 text-center">Catalogue Products</th>
                        <th class="py-3 px-4 text-center">Verification Badge</th>
                        <th class="py-3 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($brands as $brand)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-850/50">
                            <td class="py-3.5 px-6">
                                <div class="flex items-center space-x-3">
                                    <!-- Logo with initials fallback -->
                                    <div class="relative w-10 h-10 rounded-xl overflow-hidden shrink-0">
                                        @if($brand->logo_url)
                                            <img src="{{ $brand->logo_url }}" alt="{{ $brand->name }}" class="w-10 h-10 rounded-xl object-contain bg-white dark:bg-slate-800 p-1 border border-slate-200 dark:border-slate-700 shrink-0" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                                        @endif
                                        <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 {{ $brand->logo_url ? 'hidden' : 'flex' }} items-center justify-center font-bold text-slate-700 dark:text-slate-200 shrink-0">
                                            {{ substr($brand->name, 0, 2) }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900 dark:text-white flex items-center space-x-1.5">
                                            <span>{{ $brand->name }}</span>
                                            @if($brand->is_verified)
                                                <svg class="w-4 h-4 text-indigo-500 fill-current shrink-0" viewBox="0 0 20 20" title="Certified Brand"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                            @endif
                                        </div>
                                        <div class="text-[11px] text-slate-400 mt-0.5">
                                            Created {{ $brand->created_at->format('M Y') }}
                                            @if($brand->meta_title)
                                                &bull; <span class="text-indigo-600 dark:text-indigo-400 font-mono text-[10px]">SEO Ready</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 font-mono text-slate-500">
                                /{{ $brand->slug }}
                            </td>
                            <td class="py-3.5 px-4 text-slate-600 dark:text-slate-300">
                                @if($brand->seller)
                                    <a href="{{ route('admin.sellers.show', $brand->seller) }}" class="font-medium text-slate-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 flex items-center space-x-1">
                                        <span>{{ $brand->seller->business_name }}</span>
                                        <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    </a>
                                    <div class="text-[10px] text-slate-400">Merchant Partner Brand</div>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                                        Platform Global Brand
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center font-mono font-bold text-slate-700 dark:text-slate-300">
                                {{ $brand->products_count }} item(s)
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <form method="POST" action="{{ route('admin.catalogue.brands.toggle_verify', $brand) }}">
                                    @csrf
                                    <button 
                                        type="submit" 
                                        class="px-2.5 py-0.5 rounded-full text-[10px] font-bold transition border {{ $brand->is_verified ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 border-slate-200 dark:border-slate-700 hover:bg-slate-200' }}"
                                        title="Click to toggle verified badge"
                                    >
                                        {{ $brand->is_verified ? 'Verified & Certified' : 'Unverified' }}
                                    </button>
                                </form>
                            </td>
                            <td class="py-3.5 px-6 text-right">
                                <div class="inline-flex items-center space-x-2">
                                    <button 
                                        type="button" 
                                        @click="openEditModal(@js($brand))" 
                                        class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition"
                                    >
                                        Edit
                                    </button>

                                    <form method="POST" action="{{ route('admin.catalogue.brands.destroy', $brand) }}" class="inline" onsubmit="return confirm('Delete this brand? Associated products will have brand detached.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1 text-slate-400 hover:text-rose-600 transition" title="Delete">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400">
                                <x-empty-state 
                                    title="No brands found" 
                                    description="Register trusted product brands to boost consumer discovery." 
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-100 dark:border-slate-800 px-4 py-3">
            <x-pagination :paginator="$brands" />
        </div>
    </x-card>

    <!-- Create / Edit Brand Modal -->
    <x-modal name="brand-modal" size="xl">
        <x-slot name="header">
            <h3 class="text-base font-bold text-slate-900 dark:text-white" x-text="modalMode === 'create' ? 'Register New Brand' : 'Edit Brand Profile'"></h3>
        </x-slot>
        <form id="brandForm" :action="modalAction" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <template x-if="modalMode === 'edit'">
                <input type="hidden" name="_method" value="PUT">
            </template>

            <!-- Row 1: Brand Name & URL Slug -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        Brand Name <span class="text-rose-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="name" 
                        x-model="form.name" 
                        required 
                        placeholder="e.g. Tata Consumer Products, Amul, Apple" 
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 transition"
                    />
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        URL Slug (auto-generated if empty)
                    </label>
                    <input 
                        type="text" 
                        name="slug" 
                        x-model="form.slug" 
                        placeholder="e.g. amul" 
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-indigo-500 transition"
                    />
                </div>
            </div>

            <!-- Row 2: Merchant Owner & Official Certification Badge -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        Merchant Owner (Optional)
                    </label>
                    <select name="seller_id" x-model="form.seller_id" class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 transition">
                        <option value="">None (Platform Global Public Brand)</option>
                        @foreach($sellers as $seller)
                            <option value="{{ $seller->id }}">{{ $seller->business_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="sm:pt-5">
                    <label class="flex items-center space-x-2.5 p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                        <input type="checkbox" name="is_verified" value="1" x-model="form.is_verified" class="rounded text-indigo-600 focus:ring-indigo-500 h-4 w-4">
                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">Verified Official Brand Badge</span>
                    </label>
                </div>
            </div>

            <!-- Row 3: Brand Logo Section with Live Preview Box -->
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700/80 space-y-3">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                    Brand Logo Asset
                </label>
                
                <div class="flex items-center space-x-4">
                    <!-- Live Logo Preview Box -->
                    <div class="w-16 h-16 rounded-xl bg-white dark:bg-slate-850 border border-slate-200 dark:border-slate-700 flex items-center justify-center shrink-0 overflow-hidden shadow-2xs p-1">
                        <template x-if="logoPreview">
                            <img :src="logoPreview" alt="Logo Preview" class="w-full h-full object-contain">
                        </template>
                        <template x-if="!logoPreview">
                            <div class="flex flex-col items-center justify-center text-slate-300 dark:text-slate-600 text-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span class="text-[9px] font-bold mt-0.5">No Logo</span>
                            </div>
                        </template>
                    </div>

                    <div class="flex-1 space-y-2">
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-300 mb-1">Upload Logo File (SVG, PNG, JPG, WEBP up to 2MB)</label>
                            <input 
                                type="file" 
                                name="logo_file" 
                                accept="image/*"
                                @change="handleLogoChange($event)"
                                class="w-full text-xs text-slate-500 dark:text-slate-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-950 dark:file:text-indigo-300"
                            />
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-300 mb-1">Or Storage Path / External CDN URL</label>
                            <input 
                                type="text" 
                                name="logo" 
                                x-model="form.logo" 
                                @input="updateLogoPreviewFromPath()"
                                placeholder="brands/amul_logo.svg" 
                                class="w-full p-2 text-xs rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-indigo-500 transition"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 4: SEO Meta Section -->
            <div class="pt-2 border-t border-slate-100 dark:border-slate-800 space-y-3">
                <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200">Search Engine Optimization (SEO)</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Meta Title</label>
                        <input type="text" name="meta_title" x-model="form.meta_title" placeholder="SEO Title tag" class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 transition">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Canonical URL</label>
                        <input type="url" name="canonical_url" x-model="form.canonical_url" placeholder="https://..." class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-indigo-500 transition">
                    </div>
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Meta Description</label>
                    <textarea name="meta_description" rows="2" x-model="form.meta_description" placeholder="Short description for Google search snippet" class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 transition"></textarea>
                </div>
            </div>
        </form>

        <x-slot name="footer">
            <button type="button" @click="$dispatch('close-modal', 'brand-modal')" class="px-4 py-2 text-xs font-semibold rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 transition">Cancel</button>
            <button form="brandForm" type="submit" class="px-5 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition flex items-center space-x-1.5 cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span x-text="modalMode === 'create' ? 'Register Brand' : 'Save Changes'"></span>
            </button>
        </x-slot>
    </x-modal>
</div>

<script>
    function brandManager() {
        return {
            modalMode: 'create',
            modalAction: '{{ route("admin.catalogue.brands.store") }}',
            logoPreview: '',
            form: {
                id: null,
                name: '',
                slug: '',
                logo: '',
                seller_id: '',
                is_verified: true,
                meta_title: '',
                meta_description: '',
                canonical_url: ''
            },

            handleLogoChange(e) {
                const file = e.target.files && e.target.files[0];
                if (file) {
                    this.logoPreview = URL.createObjectURL(file);
                }
            },

            updateLogoPreviewFromPath() {
                if (this.form.logo && this.form.logo.trim() !== '') {
                    const raw = this.form.logo.trim();
                    if (raw.startsWith('http://') || raw.startsWith('https://') || raw.startsWith('data:image')) {
                        this.logoPreview = raw;
                    } else {
                        const clean = raw.replace(/^\/?(storage\/)?/, '');
                        this.logoPreview = '{{ asset("storage") }}/' + clean;
                    }
                } else {
                    this.logoPreview = '';
                }
            },

            openCreateModal() {
                this.modalMode = 'create';
                this.modalAction = '{{ route("admin.catalogue.brands.store") }}';
                this.logoPreview = '';
                this.form = { 
                    id: null, 
                    name: '', 
                    slug: '', 
                    logo: '', 
                    seller_id: '', 
                    is_verified: true,
                    meta_title: '',
                    meta_description: '',
                    canonical_url: ''
                };
                this.$dispatch('open-modal', 'brand-modal');
            },

            openEditModal(brand) {
                this.modalMode = 'edit';
                this.modalAction = '{{ url("/admin/catalogue/brands") }}/' + brand.id;

                let initialPreview = '';
                if (brand.logo && brand.logo.trim() !== '') {
                    const raw = brand.logo.trim();
                    if (raw.startsWith('http://') || raw.startsWith('https://') || raw.startsWith('data:image')) {
                        initialPreview = raw;
                    } else {
                        const clean = raw.replace(/^\/?(storage\/)?/, '');
                        initialPreview = '{{ asset("storage") }}/' + clean;
                    }
                }
                this.logoPreview = initialPreview;

                this.form = {
                    id: brand.id,
                    name: brand.name,
                    slug: brand.slug,
                    logo: brand.logo || '',
                    seller_id: brand.seller_id || '',
                    is_verified: !!brand.is_verified,
                    meta_title: brand.meta_title || '',
                    meta_description: brand.meta_description || '',
                    canonical_url: brand.canonical_url || ''
                };
                this.$dispatch('open-modal', 'brand-modal');
            }
        };
    }
</script>
@endsection
