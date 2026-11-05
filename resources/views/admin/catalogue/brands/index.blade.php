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
                {{ $brands->total() }} Brands Registered
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
                    class="w-full py-2 px-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
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
                    <a href="{{ route('admin.catalogue.brands.index') }}" class="py-2 px-3 text-xs font-semibold rounded-xl bg-slate-200 dark:bg-slate-750 text-slate-700 dark:text-slate-300">Reset</a>
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
                                    <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center font-bold text-slate-700 dark:text-slate-200 shrink-0">
                                        {{ substr($brand->name, 0, 2) }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900 dark:text-white flex items-center space-x-1.5">
                                            <span>{{ $brand->name }}</span>
                                            @if($brand->is_verified)
                                                <svg class="w-4 h-4 text-indigo-500 fill-current" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                            @endif
                                        </div>
                                        <div class="text-[11px] text-slate-400">Created {{ $brand->created_at->format('M Y') }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 font-mono text-slate-500">
                                /{{ $brand->slug }}
                            </td>
                            <td class="py-3.5 px-4 text-slate-600 dark:text-slate-300">
                                @if($brand->seller)
                                    <span class="font-medium text-slate-900 dark:text-white">{{ $brand->seller->business_name }}</span>
                                    <div class="text-[10px] text-slate-400">Private Merchant Brand</div>
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
                                        class="px-2.5 py-0.5 rounded-full text-[10px] font-bold transition border {{ $brand->is_verified ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : 'bg-slate-100 text-slate-500 border-slate-200 hover:bg-slate-200' }}"
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
                                        <button type="submit" class="p-1 text-slate-400 hover:text-rose-600" title="Delete">
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
    <x-modal name="brand-modal" size="md">
        <x-slot:header>
            <h3 class="text-base font-bold text-slate-900 dark:text-white" x-text="modalMode === 'create' ? 'Register New Brand' : 'Edit Brand Profile'"></h3>
        </x-slot:header>
        <form :action="modalAction" method="POST" class="space-y-4">
            @csrf
            <template x-if="modalMode === 'edit'">
                <input type="hidden" name="_method" value="PUT">
            </template>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                    Brand Name <span class="text-rose-500">*</span>
                </label>
                <input 
                    type="text" 
                    name="name" 
                    x-model="form.name" 
                    required 
                    placeholder="e.g. Tata Consumer Products, Amul, Apple" 
                    class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                />
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                    URL Slug (auto-generated if empty)
                </label>
                <input 
                    type="text" 
                    name="slug" 
                    x-model="form.slug" 
                    placeholder="e.g. amul" 
                    class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono"
                />
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                    Logo Image URL / Path
                </label>
                <input 
                    type="text" 
                    name="logo" 
                    x-model="form.logo" 
                    placeholder="brands/amul_logo.svg" 
                    class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                />
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                    Merchant Owner (Optional)
                </label>
                <select name="seller_id" x-model="form.seller_id" class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white">
                    <option value="">None (Platform Public Brand)</option>
                    @foreach($sellers as $seller)
                        <option value="{{ $seller->id }}">{{ $seller->business_name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="pt-1">
                <label class="flex items-center space-x-2 text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
                    <input type="checkbox" name="is_verified" value="1" x-model="form.is_verified" class="rounded text-indigo-600 focus:ring-indigo-500">
                    <span>Verified Official Brand Certification Badge</span>
                </label>
            </div>

            <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                <button type="button" @click="$dispatch('close-modal', 'brand-modal')" class="px-3 py-1.5 text-xs text-slate-600">Cancel</button>
                <button type="submit" class="px-4 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition">Save Brand</button>
            </div>
        </form>
    </x-modal>
</div>

<script>
    function brandManager() {
        return {
            modalMode: 'create',
            modalAction: '{{ route("admin.catalogue.brands.store") }}',
            form: {
                id: null,
                name: '',
                slug: '',
                logo: '',
                seller_id: '',
                is_verified: true
            },

            openCreateModal() {
                this.modalMode = 'create';
                this.modalAction = '{{ route("admin.catalogue.brands.store") }}';
                this.form = { id: null, name: '', slug: '', logo: '', seller_id: '', is_verified: true };
                this.$dispatch('open-modal', 'brand-modal');
            },

            openEditModal(brand) {
                this.modalMode = 'edit';
                this.modalAction = '{{ url("/admin/catalogue/brands") }}/' + brand.id;
                this.form = {
                    id: brand.id,
                    name: brand.name,
                    slug: brand.slug,
                    logo: brand.logo || '',
                    seller_id: brand.seller_id || '',
                    is_verified: !!brand.is_verified
                };
                this.$dispatch('open-modal', 'brand-modal');
            }
        };
    }
</script>
@endsection
