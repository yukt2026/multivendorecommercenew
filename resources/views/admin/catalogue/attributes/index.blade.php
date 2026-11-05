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

    <!-- Attributes Cards Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @forelse($attributes as $attribute)
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <div class="flex items-center space-x-2">
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $attribute->name }}</h3>
                            <span class="font-mono text-xs text-slate-400">/{{ $attribute->slug }}</span>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-0.5">{{ $attribute->values_count }} pre-defined option values</p>
                    </div>

                    <div class="flex items-center space-x-2">
                        <button 
                            type="button" 
                            @click="openEditModal(@js($attribute))"
                            class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300"
                        >
                            Edit
                        </button>
                        <form method="POST" action="{{ route('admin.catalogue.attributes.destroy', $attribute) }}" onsubmit="return confirm('Delete this attribute group and all associated values?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="p-1 text-slate-400 hover:text-rose-600" title="Delete Attribute">
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
                                    <span class="w-3 h-3 rounded-full border border-slate-300 shrink-0" style="background-color: {{ $val->code }};"></span>
                                @endif
                                <span class="font-medium text-slate-800 dark:text-slate-200">{{ $val->value }}</span>
                                <form method="POST" action="{{ route('admin.catalogue.attributes.values.destroy', [$attribute, $val]) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-slate-400 hover:text-rose-500 pl-1" title="Remove value">&times;</button>
                                </form>
                            </div>
                        @empty
                            <span class="text-xs text-slate-400 italic">No option values configured yet.</span>
                        @endforelse
                    </div>
                </div>

                <!-- Quick Inline Add Value Form -->
                <form method="POST" action="{{ route('admin.catalogue.attributes.values.store', $attribute) }}" class="pt-2 flex items-center gap-2">
                    @csrf
                    <input 
                        type="text" 
                        name="value" 
                        required 
                        placeholder="Add value (e.g. XL, 1kg, Red)..." 
                        class="flex-1 py-1.5 px-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                    />
                    <input 
                        type="text" 
                        name="code" 
                        placeholder="Hex / Code (#FF0000)" 
                        class="w-32 py-1.5 px-3 text-xs font-mono rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                    />
                    <button type="submit" class="px-3 py-1.5 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs">
                        + Add
                    </button>
                </form>
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
    <x-modal name="attribute-modal" size="sm">
        <x-slot:header>
            <h3 class="text-base font-bold text-slate-900 dark:text-white" x-text="modalMode === 'create' ? 'Create Attribute Group' : 'Edit Attribute Group'"></h3>
        </x-slot:header>
        <form :action="modalAction" method="POST" class="space-y-4">
            @csrf
            <template x-if="modalMode === 'edit'">
                <input type="hidden" name="_method" value="PUT">
            </template>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                    Attribute Label Name <span class="text-rose-500">*</span>
                </label>
                <input 
                    type="text" 
                    name="name" 
                    x-model="form.name" 
                    required 
                    placeholder="e.g. Size, Color, Flavor, Material" 
                    class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"
                />
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                    Slug (optional)
                </label>
                <input 
                    type="text" 
                    name="slug" 
                    x-model="form.slug" 
                    placeholder="e.g. size" 
                    class="w-full p-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white font-mono"
                />
            </div>

            <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                <button type="button" @click="$dispatch('close-modal', 'attribute-modal')" class="px-3 py-1.5 text-xs text-slate-600">Cancel</button>
                <button type="submit" class="px-4 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs">Save Attribute</button>
            </div>
        </form>
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
