@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Banners & Visual Merchandising'])

@section('content')
<div class="space-y-6" x-data="bannerManager()">

    <!-- Page Header -->
    <x-page-header 
        title="Homepage & Promotional Banners" 
        subtitle="Manage storefront promotional carousels, category strips, deal highlights, and scheduling."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Banners' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                {{ $metrics['total_banners'] }} Banners
            </span>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center space-x-2">
                <button 
                    type="button"
                    @click="openModal()" 
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm flex items-center space-x-1.5 transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Create Banner</span>
                </button>
            </div>
        </x-slot>
    </x-page-header>

    <!-- Metrics Strip -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Total Banners</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $metrics['total_banners'] }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Active Live</div>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ $metrics['active_banners'] }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-indigo-600 dark:text-indigo-400">Hero Carousel</div>
            <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ $metrics['hero_banners'] }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-amber-600 dark:text-amber-400">Deal Banners</div>
            <div class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ $metrics['deal_banners'] }}</div>
        </div>
    </div>

    <!-- Filters Strip -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm flex flex-col md:flex-row gap-4 justify-between items-center">
        <div class="flex flex-wrap items-center gap-2">
            @php $currentPos = request('position', 'all'); @endphp
            @foreach([
                'all' => 'All Positions', 
                'hero' => 'Hero Carousel', 
                'deal_banner' => 'Deal & Promo Strips', 
                'category_strip' => 'Category Strips', 
                'sidebar' => 'Sidebar'
            ] as $posKey => $posLabel)
                <a href="{{ request()->fullUrlWithQuery(['position' => $posKey, 'page' => 1]) }}" 
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $currentPos === $posKey ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                    {{ $posLabel }}
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.banners.index') }}" class="flex items-center space-x-2 w-full md:w-auto">
            @if(request('position'))
                <input type="hidden" name="position" value="{{ request('position') }}">
            @endif
            <div class="relative w-full md:w-64">
                <input 
                    type="text" 
                    name="search" 
                    value="{{ request('search') }}" 
                    placeholder="Search banner title..." 
                    class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white pl-8 pr-3 py-2 focus:ring-1 focus:ring-indigo-500"
                >
                <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <button type="submit" class="px-3 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold transition">
                Filter
            </button>
            @if(request('search') || (request('position') && request('position') !== 'all'))
                <a href="{{ route('admin.banners.index') }}" class="px-2.5 py-2 text-xs text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                    Clear
                </a>
            @endif
        </form>
    </div>

    <!-- Banners Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Preview &amp; Title</th>
                        <th class="px-6 py-3.5">Placement</th>
                        <th class="px-6 py-3.5">Destination Link</th>
                        <th class="px-6 py-3.5">Sort Order</th>
                        <th class="px-6 py-3.5">Schedule</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($banners as $b)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        <td class="px-6 py-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-24 h-12 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 overflow-hidden flex items-center justify-center shrink-0">
                                    @if($b->image_url)
                                        <img src="{{ $b->image_url }}" alt="{{ $b->title }}" class="w-full h-full object-cover" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                        <div style="display:none;" class="w-full h-full flex items-center justify-center bg-slate-100 dark:bg-slate-800 text-[10px] text-slate-400 font-medium">No Image</div>
                                    @else
                                        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    @endif
                                </div>
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white">{{ $b->title }}</div>
                                    <div class="text-[10px] text-slate-400">ID: #{{ $b->id }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            @php
                                $pos = $b->position;
                                $isHero = in_array($pos, ['hero', 'home_hero', 'hero_carousel', 'top_slider'], true);
                                $isDeal = in_array($pos, ['deal_banner', 'home_sub', 'homepage_strip', 'promo_strip'], true);
                                $isCat = in_array($pos, ['category_strip', 'category_top'], true);
                            @endphp
                            <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider border
                                {{ $isHero ? 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/60 dark:text-indigo-400 dark:border-indigo-800' : '' }}
                                {{ $isDeal ? 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-400 dark:border-amber-800' : '' }}
                                {{ $isCat ? 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/60 dark:text-purple-400 dark:border-purple-800' : '' }}
                                {{ !$isHero && !$isDeal && !$isCat ? 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/60 dark:text-sky-400 dark:border-sky-800' : '' }}
                            ">
                                {{ $b->position_label }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @if($b->link && $b->link !== '#')
                                <a href="{{ $b->link }}" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline flex items-center space-x-1 max-w-xs truncate font-mono text-[11px]">
                                    <span class="truncate">{{ $b->link }}</span>
                                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 font-mono font-semibold text-slate-700 dark:text-slate-300">
                            {{ $b->sort ?? 0 }}
                        </td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-400">
                            @if($b->starts_at || $b->ends_at)
                                <div class="text-[11px] font-medium">From: <span class="text-slate-700 dark:text-slate-300">{{ $b->starts_at ? $b->starts_at->format('d M Y') : 'Immediate' }}</span></div>
                                <div class="text-[11px] font-medium">To: <span class="text-slate-700 dark:text-slate-300">{{ $b->ends_at ? $b->ends_at->format('d M Y') : 'Indefinite' }}</span></div>
                            @else
                                <span class="inline-flex items-center text-slate-400 text-[11px]">
                                    <svg class="w-3.5 h-3.5 mr-1 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Always active
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <form method="POST" action="{{ route('admin.banners.toggle', $b) }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold border transition hover:opacity-80
                                    {{ $b->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-400 dark:border-emerald-800' : 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700' }}">
                                    <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $b->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                    {{ $b->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end space-x-2">
                                <button 
                                    type="button"
                                    @click="editBanner(@js($b))" 
                                    class="p-1.5 text-slate-500 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition" 
                                    title="Edit Banner"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <form method="POST" action="{{ route('admin.banners.destroy', $b) }}" onsubmit="return confirm('Delete this banner?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 text-slate-500 hover:text-rose-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition" title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                            <div class="max-w-sm mx-auto text-center space-y-3">
                                <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                                <div class="text-sm font-semibold text-slate-900 dark:text-white">No promotional banners found</div>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Create promotional banners to showcase flash deals, seasonal sales, and featured categories on the storefront.</p>
                                <button 
                                    type="button" 
                                    @click="openModal()" 
                                    class="inline-flex items-center px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm transition"
                                >
                                    Create First Banner
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($banners->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $banners->links() }}
            </div>
        @endif
    </div>

    <!-- Create / Edit Banner Modal -->
    <x-modal name="banner-modal" size="xl">
        <form :action="formUrl" method="POST" enctype="multipart/form-data" class="flex flex-col min-h-0">
            @csrf
            <template x-if="isEditing">
                <input type="hidden" name="_method" value="PUT">
            </template>

            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between shrink-0 bg-white/80 dark:bg-slate-900/80 backdrop-blur-xs">
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white" x-text="isEditing ? 'Edit Promotional Banner' : 'Create Promotional Banner'"></h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Configure storefront visual banners, destination routes, and scheduling.</p>
                </div>
                <button 
                    type="button" 
                    @click="$dispatch('close-modal', 'banner-modal')" 
                    class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div class="p-6 space-y-4 overflow-y-auto flex-1 min-h-0 text-left">
                <!-- Title -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Banner Title *</label>
                    <input 
                        type="text" 
                        name="title" 
                        x-model="form.title" 
                        required 
                        placeholder="e.g. Summer Mega Sale 50% Off" 
                        class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                    >
                </div>

                <!-- Position & Sort -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Storefront Placement *</label>
                        <select 
                            name="position" 
                            x-model="form.position" 
                            required 
                            class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                        >
                            <option value="home_hero">Hero Carousel (Homepage Top)</option>
                            <option value="home_sub">Deals & Promotional Strip (Homepage Middle)</option>
                            <option value="category_strip">Category Page Top Strip</option>
                            <option value="sidebar">Sidebar / Promotion Widget</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Sort Priority Order</label>
                        <input 
                            type="number" 
                            name="sort" 
                            x-model="form.sort" 
                            min="0" 
                            placeholder="0" 
                            class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                        >
                    </div>
                </div>

                <!-- Destination Link -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Target Destination Link (URL / Route)</label>
                    <input 
                        type="text" 
                        name="link" 
                        x-model="form.link" 
                        placeholder="e.g. /categories/fresh-groceries or https://example.com" 
                        class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                    >
                </div>

                <!-- Banner Image Asset Box -->
                <div class="p-3.5 bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700/80 rounded-xl space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">Banner Visual Asset</label>
                        <span class="text-[10px] text-slate-400">PNG, JPG, WEBP (Max 5MB)</span>
                    </div>

                    <!-- Live Image Preview -->
                    <div class="flex items-center space-x-3">
                        <div class="w-32 h-16 rounded-lg bg-slate-200 dark:bg-slate-700 border border-slate-300 dark:border-slate-600 overflow-hidden flex items-center justify-center shrink-0">
                            <template x-if="previewImage">
                                <img :src="previewImage" alt="Preview" class="w-full h-full object-cover">
                            </template>
                            <template x-if="!previewImage">
                                <div class="text-center p-2 text-[10px] text-slate-400">
                                    <svg class="w-5 h-5 mx-auto mb-0.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    No Image
                                </div>
                            </template>
                        </div>
                        <div class="flex-1 space-y-2">
                            <div>
                                <label class="block text-[11px] font-medium text-slate-600 dark:text-slate-400 mb-1">Upload New Image File</label>
                                <input 
                                    type="file" 
                                    name="image" 
                                    accept="image/*" 
                                    @change="handleFileChange($event)"
                                    class="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-950/60 dark:file:text-indigo-400"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- External Image URL input -->
                    <div>
                        <label class="block text-[11px] font-medium text-slate-600 dark:text-slate-400 mb-1">Or Storage Path / CDN Image URL</label>
                        <input 
                            type="text" 
                            name="image_url" 
                            x-model="form.image_url" 
                            @input="if(!selectedFile) previewImage = form.image_url"
                            placeholder="e.g. banners/hero_groceries.jpg or https://images.unsplash.com/..." 
                            class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2 focus:ring-1 focus:ring-indigo-500"
                        >
                    </div>
                </div>

                <!-- Schedule: Starts At & Ends At -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Starts At (Schedule)</label>
                        <input 
                            type="date" 
                            name="starts_at" 
                            x-model="form.starts_at" 
                            class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Ends At (Schedule)</label>
                        <input 
                            type="date" 
                            name="ends_at" 
                            x-model="form.ends_at" 
                            class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                        >
                    </div>
                </div>

                <!-- Active Toggle -->
                <div class="flex items-center space-x-2.5 pt-1">
                    <input 
                        type="checkbox" 
                        name="is_active" 
                        id="banner_is_active" 
                        value="1" 
                        x-model="form.is_active" 
                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4"
                    >
                    <label for="banner_is_active" class="text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
                        Set active immediately on storefront
                    </label>
                </div>
            </div>

            <!-- Sticky Action Footer -->
            <div class="sticky bottom-0 px-6 py-3.5 bg-white/95 dark:bg-slate-900/95 backdrop-blur-xs border-t border-slate-200 dark:border-slate-800 flex justify-end space-x-2 shrink-0 z-10">
                <button 
                    type="button" 
                    @click="$dispatch('close-modal', 'banner-modal')" 
                    class="px-4 py-2 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                >
                    Cancel
                </button>
                <button 
                    type="submit" 
                    class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow-sm transition inline-flex items-center space-x-1.5"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span x-text="isEditing ? 'Update Banner' : 'Create Banner'"></span>
                </button>
            </div>
        </form>
    </x-modal>

</div>

<script>
function bannerManager() {
    return {
        isEditing: false,
        formUrl: '{{ route("admin.banners.store") }}',
        previewImage: '',
        selectedFile: false,
        form: {
            title: '',
            position: 'home_hero',
            sort: 0,
            link: '',
            image_url: '',
            starts_at: '',
            ends_at: '',
            is_active: true
        },
        openModal() {
            this.isEditing = false;
            this.formUrl = '{{ route("admin.banners.store") }}';
            this.previewImage = '';
            this.selectedFile = false;
            this.form = {
                title: '',
                position: 'home_hero',
                sort: 0,
                link: '',
                image_url: '',
                starts_at: '',
                ends_at: '',
                is_active: true
            };
            this.$dispatch('open-modal', 'banner-modal');
        },
        editBanner(banner) {
            this.isEditing = true;
            this.formUrl = `/admin/banners/${banner.id}`;
            this.selectedFile = false;

            // Map position aliases to standard canonical select values
            let pos = banner.position;
            if (['hero', 'hero_carousel', 'top_slider', 'home_hero'].includes(pos)) {
                pos = 'home_hero';
            } else if (['deal_banner', 'homepage_strip', 'promo_strip', 'home_sub'].includes(pos)) {
                pos = 'home_sub';
            } else if (['category_top', 'category_strip'].includes(pos)) {
                pos = 'category_strip';
            }

            this.form = {
                title: banner.title || '',
                position: pos,
                sort: banner.sort || 0,
                link: banner.link || '',
                image_url: banner.image || '',
                starts_at: banner.starts_at ? banner.starts_at.substring(0, 10) : '',
                ends_at: banner.ends_at ? banner.ends_at.substring(0, 10) : '',
                is_active: Boolean(banner.is_active)
            };

            this.previewImage = banner.image_url || '';
            this.$dispatch('open-modal', 'banner-modal');
        },
        handleFileChange(event) {
            const file = event.target.files[0];
            if (file) {
                this.selectedFile = true;
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.previewImage = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        }
    }
}
</script>
@endsection
