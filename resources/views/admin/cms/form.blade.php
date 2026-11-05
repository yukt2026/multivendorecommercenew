@extends('layouts.dashboard', ['portal' => 'admin', 'title' => $page->exists ? 'Edit CMS Page' : 'Create CMS Page'])

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">

    <!-- Page Header -->
    <x-page-header 
        :title="$page->exists ? 'Edit CMS Page: ' . $page->title : 'Create Static Content Page'" 
        subtitle="Manage textual and rich content for customer-facing informative and legal pages."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'CMS Pages' => route('admin.cms.index'), ($page->exists ? 'Edit' : 'New Page') => '#']"
    >
        <x-slot name="actions">
            <a 
                href="{{ route('admin.cms.index') }}" 
                class="px-4 py-2 border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold transition"
            >
                Back to Pages
            </a>
        </x-slot>
    </x-page-header>

    <form method="POST" action="{{ $page->exists ? route('admin.cms.update', $page) : route('admin.cms.store') }}" class="space-y-6">
        @csrf
        @if($page->exists)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            <!-- Main Editor Area -->
            <div class="md:col-span-2 space-y-6">
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider text-[11px]">Page Content</h3>
                    
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Page Title *</label>
                        <input 
                            type="text" 
                            name="title" 
                            value="{{ old('title', $page->title) }}" 
                            required 
                            placeholder="e.g. Terms and Conditions" 
                            class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-3 focus:ring-1 focus:ring-indigo-500 font-semibold text-sm"
                        >
                        @error('title') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">URL Slug</label>
                        <div class="flex items-center">
                            <span class="inline-flex items-center px-3 py-2 text-xs rounded-l-lg border border-r-0 border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-500 font-mono">
                                /pages/
                            </span>
                            <input 
                                type="text" 
                                name="slug" 
                                value="{{ old('slug', $page->slug) }}" 
                                placeholder="terms-and-conditions (auto-generated if empty)" 
                                class="w-full text-xs rounded-r-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2 font-mono focus:ring-1 focus:ring-indigo-500"
                            >
                        </div>
                        @error('slug') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Body Content (HTML / Markdown) *</label>
                        <textarea 
                            name="content" 
                            rows="16" 
                            required 
                            placeholder="Type or paste the static page content here..." 
                            class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-3 font-mono leading-relaxed focus:ring-1 focus:ring-indigo-500"
                        >{{ old('content', $page->content) }}</textarea>
                        @error('content') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <!-- Sidebar / Settings & SEO -->
            <div class="space-y-6">

                <!-- Publish Card -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider text-[11px]">Publishing</h3>
                    
                    <div class="flex items-center space-x-2">
                        <input 
                            type="checkbox" 
                            name="is_active" 
                            id="cms_is_active" 
                            value="1" 
                            {{ old('is_active', $page->exists ? $page->is_active : true) ? 'checked' : '' }} 
                            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                        >
                        <label for="cms_is_active" class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Visible to public
                        </label>
                    </div>

                    <div class="pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button 
                            type="submit" 
                            class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm transition"
                        >
                            {{ $page->exists ? 'Update Page' : 'Save & Publish' }}
                        </button>
                    </div>
                </div>

                <!-- SEO Card -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider text-[11px]">SEO Metadata</h3>
                    
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Meta Title</label>
                        <input 
                            type="text" 
                            name="meta_title" 
                            value="{{ old('meta_title', $page->meta_title) }}" 
                            placeholder="Optional custom HTML title" 
                            class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                        >
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Meta Description</label>
                        <textarea 
                            name="meta_description" 
                            rows="4" 
                            placeholder="Summary snippet for search engine indexation..." 
                            class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white p-2.5 focus:ring-1 focus:ring-indigo-500"
                        >{{ old('meta_description', $page->meta_description) }}</textarea>
                    </div>
                </div>

            </div>

        </div>
    </form>

</div>
@endsection
