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
                <p class="text-xs text-slate-500">Reorder categories using position controls or create nested multi-level subcategories.</p>
            </div>
            <div class="flex items-center space-x-2">
                <button 
                    type="button" 
                    @click="expandAll = !expandAll" 
                    class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition"
                    x-text="expandAll ? 'Collapse All Nodes' : 'Expand All Nodes'"
                ></button>
            </div>
        </div>

        @if($categoryTree->count() > 0)
            @php 
                $rootIdsJson = $categoryTree->pluck('id')->values()->toJson(); 
            @endphp
            <div class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                @foreach($categoryTree as $root)
                    <div class="py-3" x-data="{ open: true }" x-init="$watch('expandAll', val => open = val)">
                        <!-- Root Node Row -->
                        <div class="p-3.5 rounded-xl bg-slate-50/70 dark:bg-slate-850/60 border border-slate-200/80 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-center space-x-3">
                                <div class="flex items-center space-x-1.5 shrink-0">
                                    <button 
                                        type="button" 
                                        @click="open = !open" 
                                        class="w-6 h-6 rounded-md bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center text-xs hover:bg-slate-300 transition"
                                        title="Toggle Subcategories"
                                    >
                                        <span x-show="!open">+</span>
                                        <span x-show="open">&minus;</span>
                                    </button>

                                    <!-- Root Image with Fallback -->
                                    <div class="relative w-8 h-8 rounded-lg overflow-hidden shrink-0">
                                        @if($root->image_url)
                                            <img src="{{ $root->image_url }}" alt="{{ $root->name }}" class="w-8 h-8 rounded-lg object-cover border border-slate-200 dark:border-slate-700 shrink-0" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                                        @endif
                                        <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800 {{ $root->image_url ? 'hidden' : 'flex' }} items-center justify-center font-bold text-indigo-600 text-xs shrink-0">
                                            {{ substr($root->name, 0, 2) }}
                                        </div>
                                    </div>
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
                            <div class="flex items-center space-x-1.5 self-end sm:self-auto">
                                @if($root->is_quick_eligible)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                        10-30m Quick Eligible
                                    </span>
                                @endif

                                @if($root->commission_percent !== null)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200">
                                        {{ $root->commission_percent }}% Comm.
                                    </span>
                                @endif

                                <!-- Move Up / Move Down -->
                                <div class="inline-flex items-center rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-0.5 space-x-0.5">
                                    <button 
                                        type="button" 
                                        @click="shiftOrder({{ $root->id }}, -1, {{ $rootIdsJson }})" 
                                        @if($loop->first) disabled @endif
                                        class="p-1 rounded text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-white disabled:opacity-30 disabled:cursor-not-allowed transition" 
                                        title="Move Up"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
                                    </button>
                                    <button 
                                        type="button" 
                                        @click="shiftOrder({{ $root->id }}, 1, {{ $rootIdsJson }})" 
                                        @if($loop->last) disabled @endif
                                        class="p-1 rounded text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-white disabled:opacity-30 disabled:cursor-not-allowed transition" 
                                        title="Move Down"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                </div>

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
                        <div x-show="open" class="pl-6 sm:pl-10 pt-2 space-y-2">
                            @php 
                                $subIdsJson = $root->children->pluck('id')->values()->toJson(); 
                            @endphp
                            @foreach($root->children as $sub)
                                <div class="space-y-1.5" x-data="{ subOpen: true }" x-init="$watch('expandAll', val => subOpen = val)">
                                    <div class="p-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs shadow-2xs">
                                        <div class="flex items-center space-x-2">
                                            @if($sub->children->count() > 0)
                                                <button 
                                                    type="button" 
                                                    @click="subOpen = !subOpen" 
                                                    class="w-5 h-5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center text-[10px] hover:bg-slate-200 transition"
                                                    title="Toggle Level 3 Items"
                                                >
                                                    <span x-show="!subOpen">+</span>
                                                    <span x-show="subOpen">&minus;</span>
                                                </button>
                                            @else
                                                <span class="text-slate-400 font-mono pl-1">&rdsh;</span>
                                            @endif

                                            <!-- Subcategory Image with Fallback -->
                                            <div class="relative w-7 h-7 rounded-md overflow-hidden shrink-0">
                                                @if($sub->image_url)
                                                    <img src="{{ $sub->image_url }}" alt="{{ $sub->name }}" class="w-7 h-7 rounded-md object-cover border border-slate-200 dark:border-slate-700 shrink-0" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                                                @endif
                                                <div class="w-7 h-7 rounded-md bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 {{ $sub->image_url ? 'hidden' : 'flex' }} items-center justify-center font-bold text-slate-500 text-[10px] shrink-0">
                                                    {{ substr($sub->name, 0, 2) }}
                                                </div>
                                            </div>

                                            <div>
                                                <div class="flex items-center space-x-2">
                                                    <span class="font-bold text-slate-800 dark:text-slate-200">{{ $sub->name }}</span>
                                                    <span class="text-[10px] font-mono text-slate-400">/{{ $sub->slug }}</span>
                                                    @if(!$sub->is_active)
                                                        <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-rose-100 text-rose-800">Inactive</span>
                                                    @endif
                                                </div>
                                                <div class="text-[10px] text-slate-400 flex items-center space-x-1.5">
                                                    <span>Order: #{{ $sub->sort_order }}</span>
                                                    <span>&bull;</span>
                                                    <span>{{ $sub->products_count }} Products</span>
                                                    @if($sub->children->count() > 0)
                                                        <span>&bull;</span>
                                                        <span>{{ $sub->children->count() }} Level-3 Subcategories</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex items-center space-x-1.5 self-end sm:self-auto">
                                            @if($sub->is_quick_eligible)
                                                <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-50 text-amber-700">Quick</span>
                                            @endif
                                            @if($sub->commission_percent !== null)
                                                <span class="text-[10px] font-mono text-indigo-600 font-semibold">({{ $sub->commission_percent }}%)</span>
                                            @endif

                                            <!-- Move Up / Move Down -->
                                            <div class="inline-flex items-center rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-0.5">
                                                <button 
                                                    type="button" 
                                                    @click="shiftOrder({{ $sub->id }}, -1, {{ $subIdsJson }})" 
                                                    @if($loop->first) disabled @endif
                                                    class="p-0.5 rounded text-slate-400 hover:text-indigo-600 disabled:opacity-30 disabled:cursor-not-allowed" 
                                                    title="Move Up"
                                                >
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
                                                </button>
                                                <button 
                                                    type="button" 
                                                    @click="shiftOrder({{ $sub->id }}, 1, {{ $subIdsJson }})" 
                                                    @if($loop->last) disabled @endif
                                                    class="p-0.5 rounded text-slate-400 hover:text-indigo-600 disabled:opacity-30 disabled:cursor-not-allowed" 
                                                    title="Move Down"
                                                >
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                                </button>
                                            </div>

                                            <button 
                                                type="button" 
                                                @click="openCreateModal({{ $sub->id }})" 
                                                class="px-2 py-0.5 text-[11px] font-semibold rounded bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 hover:bg-indigo-100"
                                                title="Add Level 3 Child"
                                            >
                                                + Subcategory
                                            </button>

                                            <button 
                                                type="button" 
                                                @click="openEditModal(@js($sub))" 
                                                class="px-2 py-0.5 text-[11px] font-medium rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200"
                                            >
                                                Edit
                                            </button>

                                            <form method="POST" action="{{ route('admin.catalogue.categories.destroy', $sub) }}" class="inline" onsubmit="return confirm('Delete this subcategory? Any nested children will be re-assigned to parent.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="p-1 text-slate-400 hover:text-rose-600" title="Delete">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <!-- Level 3 Grandchildren -->
                                    @if($sub->children->count() > 0)
                                        <div x-show="subOpen" class="pl-6 sm:pl-10 space-y-1">
                                            @php 
                                                $grandIdsJson = $sub->children->pluck('id')->values()->toJson(); 
                                            @endphp
                                            @foreach($sub->children as $grand)
                                                <div class="p-2 rounded-lg bg-slate-50/80 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                                                    <div class="flex items-center space-x-2">
                                                        <span class="text-slate-400 font-mono text-[10px] pl-2">&bull;&minus;&gt;</span>
                                                        <!-- Grandchild Image with Fallback -->
                                                        <div class="relative w-6 h-6 rounded-md overflow-hidden shrink-0">
                                                            @if($grand->image_url)
                                                                <img src="{{ $grand->image_url }}" alt="{{ $grand->name }}" class="w-6 h-6 rounded-md object-cover border border-slate-200 dark:border-slate-700 shrink-0" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                                                            @endif
                                                            <div class="w-6 h-6 rounded-md bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 {{ $grand->image_url ? 'hidden' : 'flex' }} items-center justify-center font-bold text-slate-500 text-[9px] shrink-0">
                                                                {{ substr($grand->name, 0, 2) }}
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <div class="flex items-center space-x-1.5">
                                                                <span class="font-medium text-slate-800 dark:text-slate-200">{{ $grand->name }}</span>
                                                                <span class="text-[9px] font-mono text-slate-400">/{{ $grand->slug }}</span>
                                                                @if(!$grand->is_active)
                                                                    <span class="px-1 py-0.2 rounded text-[8px] font-bold bg-rose-100 text-rose-800">Inactive</span>
                                                                @endif
                                                            </div>
                                                            <div class="text-[9px] text-slate-400">
                                                                Order: #{{ $grand->sort_order }} &bull; {{ $grand->products_count }} Products
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="flex items-center space-x-1.5 self-end sm:self-auto">
                                                        @if($grand->is_quick_eligible)
                                                            <span class="px-1 py-0.2 rounded text-[8px] font-bold bg-amber-50 text-amber-700">Quick</span>
                                                        @endif
                                                        @if($grand->commission_percent !== null)
                                                            <span class="text-[9px] font-mono text-indigo-600 font-semibold">({{ $grand->commission_percent }}%)</span>
                                                        @endif

                                                        <!-- Move Up / Move Down -->
                                                        <div class="inline-flex items-center rounded border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-0.5">
                                                            <button 
                                                                type="button" 
                                                                @click="shiftOrder({{ $grand->id }}, -1, {{ $grandIdsJson }})" 
                                                                @if($loop->first) disabled @endif
                                                                class="p-0.5 rounded text-slate-400 hover:text-indigo-600 disabled:opacity-30 disabled:cursor-not-allowed" 
                                                                title="Move Up"
                                                            >
                                                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
                                                            </button>
                                                            <button 
                                                                type="button" 
                                                                @click="shiftOrder({{ $grand->id }}, 1, {{ $grandIdsJson }})" 
                                                                @if($loop->last) disabled @endif
                                                                class="p-0.5 rounded text-slate-400 hover:text-indigo-600 disabled:opacity-30 disabled:cursor-not-allowed" 
                                                                title="Move Down"
                                                            >
                                                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                                            </button>
                                                        </div>

                                                        <button 
                                                            type="button" 
                                                            @click="openEditModal(@js($grand))" 
                                                            class="px-1.5 py-0.5 text-[10px] font-medium rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200"
                                                        >
                                                            Edit
                                                        </button>

                                                        <form method="POST" action="{{ route('admin.catalogue.categories.destroy', $grand) }}" class="inline" onsubmit="return confirm('Delete this level-3 category?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="p-0.5 text-slate-400 hover:text-rose-600" title="Delete">
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
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
    <x-modal name="category-modal" size="xl">
        <x-slot name="header">
            <h3 class="text-base font-bold text-slate-900 dark:text-white" x-text="modalMode === 'create' ? 'Create New Category' : 'Edit Category Details'"></h3>
        </x-slot>
        <form id="categoryForm" :action="modalAction" method="POST" enctype="multipart/form-data" class="space-y-4">
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
                        class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
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
                            <option value="{{ $cat->id }}" :disabled="form.id == {{ $cat->id }}">
                                {{ $cat->parent_id ? '— ' : '' }}{{ $cat->name }}
                            </option>
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

                <!-- Toggles: Quick Commerce & Active in Public Catalogue -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Catalogue Visibility & Flags
                    </label>
                    <div class="p-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 flex flex-col space-y-1.5">
                        <label class="flex items-center space-x-2 text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
                            <input type="checkbox" name="is_quick_eligible" value="1" x-model="form.is_quick_eligible" class="rounded text-indigo-600 focus:ring-indigo-500">
                            <span>Eligible for 10-30 min Quick Commerce</span>
                        </label>

                        <label class="flex items-center space-x-2 text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="rounded text-indigo-600 focus:ring-indigo-500">
                            <span>Active in Public Catalogue</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Image Asset Section with Live Preview Box -->
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700/80 space-y-3">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                    Category Visual Image Asset
                </label>
                
                <div class="flex items-center space-x-4">
                    <!-- Live Image Preview Box -->
                    <div class="w-16 h-16 rounded-xl bg-white dark:bg-slate-850 border border-slate-200 dark:border-slate-700 flex items-center justify-center shrink-0 overflow-hidden shadow-2xs">
                        <template x-if="imagePreview">
                            <img :src="imagePreview" alt="Preview" class="w-full h-full object-cover">
                        </template>
                        <template x-if="!imagePreview">
                            <div class="flex flex-col items-center justify-center text-slate-300 dark:text-slate-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span class="text-[9px] font-bold mt-0.5">No Asset</span>
                            </div>
                        </template>
                    </div>

                    <div class="flex-1 space-y-2">
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-300 mb-1">Upload Image File (PNG, JPG, WEBP up to 2MB)</label>
                            <input 
                                type="file" 
                                name="image_file" 
                                accept="image/*"
                                @change="handleImageChange($event)"
                                class="w-full text-xs text-slate-500 dark:text-slate-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-950 dark:file:text-indigo-300"
                            />
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-300 mb-1">Or Storage Path / CDN URL</label>
                            <input 
                                type="text" 
                                name="image" 
                                x-model="form.image" 
                                @input="updatePreviewFromPath()"
                                placeholder="categories/fresh-fruits.jpg"
                                class="w-full p-2 text-xs rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono"
                            />
                        </div>
                    </div>
                </div>
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
        </form>

        <x-slot name="footer">
            <button type="button" @click="$dispatch('close-modal', 'category-modal')" class="px-4 py-2 text-xs font-semibold rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 transition">
                Cancel
            </button>
            <button form="categoryForm" type="submit" class="px-5 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition flex items-center space-x-1.5 cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span x-text="modalMode === 'create' ? 'Create Category' : 'Save Changes'"></span>
            </button>
        </x-slot>
    </x-modal>
</div>

<script>
    function categoryManager() {
        return {
            expandAll: true,
            isReordering: false,
            modalMode: 'create',
            modalAction: '{{ route("admin.catalogue.categories.store") }}',
            imagePreview: '',
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

            handleImageChange(e) {
                const file = e.target.files && e.target.files[0];
                if (file) {
                    this.imagePreview = URL.createObjectURL(file);
                }
            },

            updatePreviewFromPath() {
                if (this.form.image && this.form.image.trim() !== '') {
                    const raw = this.form.image.trim();
                    if (raw.startsWith('http://') || raw.startsWith('https://') || raw.startsWith('data:image')) {
                        this.imagePreview = raw;
                    } else {
                        const clean = raw.replace(/^\/?(storage\/)?/, '');
                        this.imagePreview = '{{ asset("storage") }}/' + clean;
                    }
                } else {
                    this.imagePreview = '';
                }
            },

            async shiftOrder(categoryId, direction, siblingIds) {
                if (this.isReordering) return;
                const index = siblingIds.indexOf(categoryId);
                if (index === -1) return;

                const targetIndex = index + direction;
                if (targetIndex < 0 || targetIndex >= siblingIds.length) return;

                // Swap in array
                const updatedSiblings = [...siblingIds];
                const temp = updatedSiblings[index];
                updatedSiblings[index] = updatedSiblings[targetIndex];
                updatedSiblings[targetIndex] = temp;

                // Build reorder payload
                const payload = updatedSiblings.map((id, sortIndex) => ({
                    id: id,
                    sort_order: sortIndex
                }));

                this.isReordering = true;
                try {
                    const response = await fetch('{{ route("admin.catalogue.categories.reorder") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ categories: payload })
                    });

                    const data = await response.json();
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert(data.message || 'Failed to reorder categories');
                        this.isReordering = false;
                    }
                } catch (error) {
                    console.error('Reorder error:', error);
                    alert('Error connecting to server. Please try again.');
                    this.isReordering = false;
                }
            },

            openCreateModal(parentId = null) {
                this.modalMode = 'create';
                this.modalAction = '{{ route("admin.catalogue.categories.store") }}';
                this.imagePreview = '';
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

                let initialPreview = '';
                if (cat.image && cat.image.trim() !== '') {
                    const raw = cat.image.trim();
                    if (raw.startsWith('http://') || raw.startsWith('https://') || raw.startsWith('data:image')) {
                        initialPreview = raw;
                    } else {
                        const clean = raw.replace(/^\/?(storage\/)?/, '');
                        initialPreview = '{{ asset("storage") }}/' + clean;
                    }
                }
                this.imagePreview = initialPreview;

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
