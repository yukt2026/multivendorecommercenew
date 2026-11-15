@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'CMS & Static Content Pages'])

@section('content')
<div class="space-y-6">

    <!-- Page Header -->
    <x-page-header 
        title="Content Management (CMS)" 
        subtitle="Manage static policy pages, terms of service, return policies, about us, and institutional information."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'CMS Pages' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-400 border border-purple-200 dark:border-purple-800">
                {{ $metrics['total'] ?? $pages->total() }} Pages
            </span>
        </x-slot>

        <x-slot name="actions">
            <a 
                href="{{ route('admin.cms.create') }}" 
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm flex items-center space-x-1.5 transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Create New Page</span>
            </a>
        </x-slot>
    </x-page-header>

    <!-- Metrics Strip -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Total CMS Pages</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $metrics['total'] ?? $pages->total() }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Published Pages</div>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ $metrics['published'] ?? 0 }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm">
            <div class="text-xs font-medium text-amber-600 dark:text-amber-400">Draft / Offline Pages</div>
            <div class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ $metrics['draft'] ?? 0 }}</div>
        </div>
    </div>

    <!-- Filters Strip -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm flex flex-col md:flex-row gap-4 justify-between items-center">
        <form method="GET" action="{{ route('admin.cms.index') }}" class="flex flex-wrap items-center gap-2 w-full">
            <div class="relative flex-1 min-w-[200px]">
                <input 
                    type="text" 
                    name="search" 
                    value="{{ request('search') }}" 
                    placeholder="Search by title or slug..." 
                    class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white pl-8 pr-3 py-2 focus:ring-1 focus:ring-indigo-500"
                >
                <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            <select name="status" class="text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white py-2 px-3">
                <option value="all">All Statuses</option>
                <option value="published" {{ request('status') === 'published' ? 'selected' : '' }}>Published Only</option>
                <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft Only</option>
            </select>

            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow-sm transition">
                Filter
            </button>
            <a href="{{ route('admin.cms.index') }}" class="px-3 py-2 text-xs text-slate-500 hover:text-slate-800 dark:hover:text-slate-200">
                Reset
            </a>
        </form>
    </div>

    <!-- CMS Pages Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Page Title</th>
                        <th class="px-6 py-3.5">Storefront URL</th>
                        <th class="px-6 py-3.5">SEO Meta Title</th>
                        <th class="px-6 py-3.5">Last Updated</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($pages as $page)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        <td class="px-6 py-4 font-bold text-slate-900 dark:text-white">
                            <div class="font-bold text-slate-900 dark:text-white text-sm">{{ $page->title }}</div>
                            <div class="text-[10px] text-slate-400 mt-0.5 font-mono">ID: #{{ $page->id }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center space-x-1.5">
                                <span class="font-mono text-slate-600 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded text-[11px]">
                                    /page/{{ $page->slug }}
                                </span>
                                @if($page->is_active)
                                <a 
                                    href="{{ route('pages.show', $page->slug) }}" 
                                    target="_blank" 
                                    class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 p-1"
                                    title="View live page in storefront"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-300 max-w-xs truncate">
                            {{ $page->meta_title ?: '—' }}
                        </td>
                        <td class="px-6 py-4 text-slate-500">
                            {{ $page->updated_at->format('d M Y, h:i A') }}
                        </td>
                        <td class="px-6 py-4">
                            <form method="POST" action="{{ route('admin.cms.toggle', $page) }}">
                                @csrf
                                <button 
                                    type="submit" 
                                    class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold border transition hover:opacity-80
                                        {{ $page->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-400 dark:border-emerald-800' : 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700' }}"
                                    title="Click to toggle published / draft state"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $page->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                    {{ $page->is_active ? 'Published' : 'Draft' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end space-x-1.5">
                                @if($page->is_active)
                                <a 
                                    href="{{ route('pages.show', $page->slug) }}" 
                                    target="_blank" 
                                    class="p-1.5 text-slate-500 hover:text-emerald-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition" 
                                    title="View Live Page"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                @endif
                                <a 
                                    href="{{ route('admin.cms.edit', $page) }}" 
                                    class="p-1.5 text-slate-500 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition" 
                                    title="Edit Page"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                <form method="POST" action="{{ route('admin.cms.destroy', $page) }}" onsubmit="return confirm('Permanently delete this CMS page?');">
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
                        <td colspan="6" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                            No CMS pages found matching criteria.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-200 dark:border-slate-800">
            <x-pagination :paginator="$pages" />
        </div>
    </div>

</div>
@endsection
