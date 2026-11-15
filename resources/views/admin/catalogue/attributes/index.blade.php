@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Attributes & Variant Options'])

@section('content')
<div class="space-y-6" x-data="attributeManager()">
    <x-page-header 
        title="Product Attributes & Filtering Vocabulary" 
        subtitle="Manage standardized product attributes (Size, Color, Flavour, Dimensions) used for catalogue search filters and variable SKUs."
        :breadcrumbs="['Command Central' => route('admin.dashboard'), 'Catalogue' => '#', 'Attributes' => '#']"
    >
        <x-slot name="badge">
            <x-badge variant="primary" size="sm">
                {{ $attributes->count() }} Standard Attributes
            </x-badge>
        </x-slot>

        <x-slot name="actions">
            <button 
                type="button" 
                @click="openCreateModal()"
                class="inline-flex items-center px-4 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition"
            >
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create Attribute Group
            </button>
        </x-slot>
    </x-page-header>

    <!-- Top Metric Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- Total Attributes -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Attribute Groups</span>
                <span class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/></svg>
                </span>
            </div>
            <div class="mt-2 text-2xl font-black text-slate-900 dark:text-white font-mono">{{ $stats['total_attributes'] ?? $attributes->count() }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Standardized product facets</div>
        </div>

        <!-- Total Defined Option Values -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Total Option Values</span>
                <span class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="mt-2 text-2xl font-black text-emerald-600 dark:text-emerald-400 font-mono">{{ $stats['total_values'] ?? 0 }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Filterable option choices</div>
        </div>

        <!-- Color / Swatch Groups -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-purple-600 dark:text-purple-400">Color / Swatches</span>
                <span class="p-2 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4 4 4 0 014-4h4a4 4 0 014 4 4 4 0 01-4 4H7z"/></svg>
                </span>
            </div>
            <div class="mt-2 text-2xl font-black text-purple-600 dark:text-purple-400 font-mono">{{ $stats['color_attributes'] ?? 0 }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">Hex visual swatches</div>
        </div>
    </div>

    <!-- Attributes Cards Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @forelse($attributes as $attribute)
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-4 flex flex-col justify-between">
                <div class="space-y-3">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                        <div>
                            <div class="flex items-center space-x-2">
                                <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $attribute->name }}</h3>
                                <span class="font-mono text-xs text-slate-400">/{{ $attribute->slug }}</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-0.5">{{ $attribute->values_count }} pre-defined option value(s)</p>
                        </div>

                        <div class="flex items-center space-x-2">
                            <button 
                                type="button" 
                                @click="openEditModal(@js($attribute))"
                                class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 transition"
                            >
                                Edit
                            </button>
                            <form method="POST" action="{{ route('admin.catalogue.attributes.destroy', $attribute) }}" onsubmit="return confirm('Delete this attribute group and all associated values?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1 text-slate-400 hover:text-rose-600 transition" title="Delete Attribute">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Existing Attribute Values Badges -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Option Values</label>
                        <div class="flex flex-wrap gap-2">
                            @forelse($attribute->values as $val)
                                <div class="inline-flex items-center space-x-1.5 py-1 px-2.5 rounded-lg bg-slate-50 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700 text-xs">
                                    @if($val->code)
                                        <span class="w-3.5 h-3.5 rounded-full border border-slate-300/80 shrink-0 shadow-2xs" style="background-color: {{ $val->code }};" title="{{ $val->code }}"></span>
                                    @endif
                                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ $val->value }}</span>
                                    @if($val->code)
                                        <span class="text-[10px] font-mono text-slate-400">({{ $val->code }})</span>
                                    @endif
                                    <form method="POST" action="{{ route('admin.catalogue.attributes.values.destroy', [$attribute, $val]) }}" class="inline" onsubmit="return confirm('Remove value {{ $val->value }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-slate-400 hover:text-rose-500 pl-1 leading-none font-bold text-sm" title="Remove value">&times;</button>
                                    </form>
                                </div>
                            @empty
                                <span class="text-xs text-slate-400 italic">No option values configured yet.</span>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Quick Inline Add Value Form with Color Picker Sync -->
                <div x-data="{ colorCode: '#4F46E5', isColor: false }" class="pt-3 border-t border-slate-100 dark:border-slate-800">
                    <form method="POST" action="{{ route('admin.catalogue.attributes.values.store', $attribute) }}" class="flex items-center gap-2">
                        @csrf
                        <input 
                            type="text" 
                            name="value" 
                            required 
                            placeholder="Add value (e.g. XL, 1kg, Navy Blue)..." 
                            class="flex-1 py-1.5 px-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                        />
                        <div class="flex items-center space-x-1 shrink-0">
                            <input 
                                type="color" 
                                x-model="colorCode"
                                class="w-7 h-7 p-0.5 rounded-lg border border-slate-200 dark:border-slate-700 cursor-pointer bg-transparent"
                                title="Pick visual swatch color"
                            />
                            <input 
                                type="text" 
                                name="code" 
                                x-model="colorCode"
                                placeholder="#Hex code" 
                                class="w-24 py-1.5 px-2 text-xs font-mono rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                            />
                        </div>
                        <button type="submit" class="px-3.5 py-1.5 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition shrink-0">
                            + Add
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="col-span-2">
                <x-empty-state 
                    title="No attribute groups found" 
                    description="Create standard attributes like Size, Color, or Weight to empower product filters." 
                />
            </div>
        @endforelse
    </div>

    <!-- Create / Edit Attribute Modal -->
    <x-modal name="attribute-modal" size="md">
        <x-slot name="header">
            <h3 class="text-base font-bold text-slate-900 dark:text-white" x-text="modalMode === 'create' ? 'Create Attribute Group' : 'Edit Attribute Group'"></h3>
        </x-slot>
        <form id="attributeForm" :action="modalAction" method="POST" class="space-y-4">
            @csrf
            <template x-if="modalMode === 'edit'">
                <input type="hidden" name="_method" value="PUT">
            </template>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                    Attribute Label Name <span class="text-rose-500">*</span>
                </label>
                <input 
                    type="text" 
                    name="name" 
                    x-model="form.name" 
                    required 
                    placeholder="e.g. Size, Color, Flavor, Material" 
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
                    placeholder="e.g. size" 
                    class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-indigo-500 transition"
                />
            </div>
        </form>

        <x-slot name="footer">
            <button type="button" @click="$dispatch('close-modal', 'attribute-modal')" class="px-4 py-2 text-xs font-semibold rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 transition">Cancel</button>
            <button form="attributeForm" type="submit" class="px-5 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition flex items-center space-x-1.5 cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span x-text="modalMode === 'create' ? 'Create Group' : 'Save Changes'"></span>
            </button>
        </x-slot>
    </x-modal>
</div>

<script>
    function attributeManager() {
        return {
            modalMode: 'create',
            modalAction: '{{ route("admin.catalogue.attributes.store") }}',
            form: { id: null, name: '', slug: '' },

            openCreateModal() {
                this.modalMode = 'create';
                this.modalAction = '{{ route("admin.catalogue.attributes.store") }}';
                this.form = { id: null, name: '', slug: '' };
                this.$dispatch('open-modal', 'attribute-modal');
            },

            openEditModal(attr) {
                this.modalMode = 'edit';
                this.modalAction = '{{ url("/admin/catalogue/attributes") }}/' + attr.id;
                this.form = { id: attr.id, name: attr.name, slug: attr.slug };
                this.$dispatch('open-modal', 'attribute-modal');
            }
        };
    }
</script>
@endsection
