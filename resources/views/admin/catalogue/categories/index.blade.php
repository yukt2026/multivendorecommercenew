@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Categories & Hierarchy'])

@section('content')
<div class="space-y-6" x-data="categoryManager()">
    <!-- Page Header -->
    <x-page-header 
        title="Category Hierarchy & Visual Tree" 
        subtitle="Manage multi-level taxonomy, drag-and-drop sort order, commission overrides, and quick commerce eligibility."
        :breadcrumbs="['Command Central' => route('admin.dashboard'), 'Catalogue' => '#', 'Categories' => '#']"
    >
        <x-slot name="badge">
            <x-badge variant="primary" size="sm">
                {{ $flatCategories->count() }} Categories Live
            </x-badge>
        </x-slot>

        <x-slot name="actions">
            <button 
                type="button" 
                @click="openCreateModal(null)"
                class="inline-flex items-center px-4 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition"
            >
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create Root Category
            </button>
        </x-slot>
    </x-page-header>

    <!-- Visual Category Tree Card -->
    <x-card padding="p-6">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Taxonomy Structure</h3>
                <p class="text-xs text-slate-500">Reorder categories using position controls or create nested subcategories.</p>
            </div>
            <div class="flex items-center space-x-2">
                <button 
                    type="button" 
                    @click="expandAll = !expandAll" 
                    class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200"
                    x-text="expandAll ? 'Collapse All Nodes' : 'Expand All Nodes'"
                ></button>
            </div>
        </div>

        @if($categoryTree->count() > 0)
            <div class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                @foreach($categoryTree as $root)
                    <div class="py-3" x-data="{ open: true }" x-init="$watch('expandAll', val => open = val)">
                        <!-- Root Node Row -->
                        <div class="p-3.5 rounded-xl bg-slate-50/70 dark:bg-slate-850/60 border border-slate-200/80 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-center space-x-3">
                                <button 
                                    type="button" 
                                    @click="open = !open" 
                                    class="w-6 h-6 rounded-md bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center text-xs hover:bg-slate-300 transition"
                                >
                                    <span x-show="!open">+</span>
                                    <span x-show="open">&minus;</span>
                                </button>

                                <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800 flex items-center justify-center font-bold text-indigo-600 text-xs shrink-0">
                                    {{ substr($root->name, 0, 2) }}
                                </div>

                                <div>
                                    <div class="flex items-center space-x-2">
                                        <span class="font-bold text-slate-900 dark:text-white text-xs">{{ $root->name }}</span>
                                        <span class="text-[10px] font-mono text-slate-400">/{{ $root->slug }}</span>
                                        @if(!$root->is_active)
                                            <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-rose-100 text-rose-800">Inactive</span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-slate-400 mt-0.5 flex items-center space-x-2">
                                        <span>Order: <strong class="text-slate-700 dark:text-slate-300 font-mono">#{{ $root->sort_order }}</strong></span>
                                        <span>&bull;</span>
                                        <span>{{ $root->products_count }} Products</span>
                                        <span>&bull;</span>
                                        <span>{{ $root->children->count() }} Subcategories</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Badges and Actions -->
                            <div class="flex items-center space-x-2 self-end sm:self-auto">
                                @if($root->is_quick_eligible)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                        10-30m Quick Eligible
                                    </span>
                                @endif

                                @if($root->commission_percent !== null)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200">
                                        {{ $root->commission_percent }}% Commission
                                    </span>
                                @endif

                                <button 
                                    type="button" 
                                    @click="openCreateModal({{ $root->id }})" 
                                    class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 hover:bg-indigo-100 transition"
                                >
                                    + Subcategory
                                </button>

                                <button 
                                    type="button" 
                                    @click="openEditModal(@js($root))" 
                                    class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition"
                                >
                                    Edit
                                </button>

                                <form method="POST" action="{{ route('admin.catalogue.categories.destroy', $root) }}" class="inline" onsubmit="return confirm('Delete this category? Any child categories will be moved to root.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1 text-slate-400 hover:text-rose-600 transition" title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Level 2 Subcategories -->
                        <div x-show="open" class="pl-8 sm:pl-12 pt-2 space-y-2">
                            @foreach($root->children as $sub)
                                <div class="p-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 flex items-center justify-between text-xs">
                                    <div class="flex items-center space-x-2">
                                        <span class="text-slate-400">&rdsh;</span>
                                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ $sub->name }}</span>
                                        <span class="text-[10px] font-mono text-slate-400">/{{ $sub->slug }}</span>
                                        @if($sub->is_quick_eligible)
                                            <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-50 text-amber-700">Quick</span>
                                        @endif
                                        @if($sub->commission_percent !== null)
                                            <span class="text-[10px] font-mono text-indigo-600">({{ $sub->commission_percent }}%)</span>
                                        @endif
                                    </div>

                                    <div class="flex items-center space-x-1.5">
                                        <button 
                                            type="button" 
                                            @click="openEditModal(@js($sub))" 
                                            class="px-2 py-0.5 text-[11px] font-medium rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200"
                                        >
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('admin.catalogue.categories.destroy', $sub) }}" class="inline" onsubmit="return confirm('Delete this subcategory?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1 text-slate-400 hover:text-rose-600" title="Delete">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <x-empty-state 
                title="No categories initialized" 
                description="Create your first parent category to build your catalogue taxonomy."
            />
        @endif
    </x-card>

    <!-- Category Create / Edit Modal -->
    <x-modal name="category-modal" size="lg">
        <x-slot:header>
            <h3 class="text-base font-bold text-slate-900 dark:text-white" x-text="modalMode === 'create' ? 'Create New Category' : 'Edit Category Details'"></h3>
        </x-slot:header>
        <form :action="modalAction" method="POST" class="space-y-4">
            @csrf
            <template x-if="modalMode === 'edit'">
                <input type="hidden" name="_method" value="PUT">
            </template>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Name -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Category Name <span class="text-rose-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="name" 
                        x-model="form.name" 
                        required 
                        placeholder="e.g. Groceries & Essentials"
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                    />
                </div>

                <!-- Slug -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        URL Slug (leave blank to auto-generate)
                    </label>
                    <input 
                        type="text" 
                        name="slug" 
                        x-model="form.slug" 
                        placeholder="e.g. groceries-essentials"
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono"
                    />
                </div>

                <!-- Parent Category -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Parent Category (Root if empty)
                    </label>
                    <select 
                        name="parent_id" 
                        x-model="form.parent_id" 
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                    >
                        <option value="">None (Top-Level Root Category)</option>
                        @foreach($flatCategories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Commission Percent Override -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Category Commission % Override
                    </label>
                    <input 
                        type="number" 
                        step="0.01" 
                        min="0" 
                        max="100" 
                        name="commission_percent" 
                        x-model="form.commission_percent" 
                        placeholder="e.g. 8.50 (leave empty for default)"
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono"
                    />
                </div>

                <!-- Sort Order -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Sort Sequence Order
                    </label>
                    <input 
                        type="number" 
                        name="sort_order" 
                        x-model="form.sort_order" 
                        placeholder="0"
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono"
                    />
                </div>

                <!-- Image Path -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Category Image Path / URL
                    </label>
                    <input 
                        type="text" 
                        name="image" 
                        x-model="form.image" 
                        placeholder="categories/groceries.png"
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                    />
                </div>
            </div>

            <!-- Toggles: Quick Commerce & Active -->
            <div class="flex items-center space-x-6 pt-1">
                <label class="flex items-center space-x-2 text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
                    <input type="checkbox" name="is_quick_eligible" value="1" x-model="form.is_quick_eligible" class="rounded text-indigo-600 focus:ring-indigo-500">
                    <span>Eligible for 10-30 min Quick Commerce</span>
                </label>

                <label class="flex items-center space-x-2 text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="rounded text-indigo-600 focus:ring-indigo-500">
                    <span>Active in Public Catalogue</span>
                </label>
            </div>

            <!-- SEO Meta Fields -->
            <div class="pt-2 border-t border-slate-100 dark:border-slate-800 space-y-3">
                <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200">Search Engine Optimization (SEO)</h4>
                <div>
                    <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Meta Title</label>
                    <input type="text" name="meta_title" x-model="form.meta_title" placeholder="SEO Title tag" class="w-full p-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Meta Description</label>
                    <textarea name="meta_description" rows="2" x-model="form.meta_description" placeholder="Short description for Google search snippet" class="w-full p-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700"></textarea>
                </div>
            </div>

            <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                <button type="button" @click="$dispatch('close-modal', 'category-modal')" class="px-3 py-1.5 text-xs text-slate-600 hover:text-slate-900">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition">
                    Save Category
                </button>
            </div>
        </form>
    </x-modal>
</div>

<script>
    function categoryManager() {
        return {
            expandAll: true,
            modalMode: 'create',
            modalAction: '{{ route("admin.catalogue.categories.store") }}',
            form: {
                id: null,
                name: '',
                slug: '',
                parent_id: '',
                commission_percent: '',
                is_quick_eligible: true,
                is_active: true,
                sort_order: 0,
                image: '',
                meta_title: '',
                meta_description: ''
            },

            openCreateModal(parentId = null) {
                this.modalMode = 'create';
                this.modalAction = '{{ route("admin.catalogue.categories.store") }}';
                this.form = {
                    id: null,
                    name: '',
                    slug: '',
                    parent_id: parentId || '',
                    commission_percent: '',
                    is_quick_eligible: true,
                    is_active: true,
                    sort_order: 0,
                    image: '',
                    meta_title: '',
                    meta_description: ''
                };
                this.$dispatch('open-modal', 'category-modal');
            },

            openEditModal(cat) {
                this.modalMode = 'edit';
                this.modalAction = '{{ url("/admin/catalogue/categories") }}/' + cat.id;
                this.form = {
                    id: cat.id,
                    name: cat.name,
                    slug: cat.slug,
                    parent_id: cat.parent_id || '',
                    commission_percent: cat.commission_percent || '',
                    is_quick_eligible: !!cat.is_quick_eligible,
                    is_active: !!cat.is_active,
                    sort_order: cat.sort_order || 0,
                    image: cat.image || '',
                    meta_title: cat.meta_title || '',
                    meta_description: cat.meta_description || ''
                };
                this.$dispatch('open-modal', 'category-modal');
            }
        };
    }
</script>
@endsection
