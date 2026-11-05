@extends('layouts.storefront')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    <!-- Breadcrumbs -->
    <nav class="flex text-xs text-slate-500 space-x-2" aria-label="Breadcrumb">
        <a href="{{ route('home') }}" class="hover:text-slate-900 transition">Home</a>
        <span>/</span>
        <span class="text-slate-900 font-semibold">{{ $page->title }}</span>
    </nav>

    <!-- Page Header -->
    <div class="border-b border-slate-200 pb-5">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
            {{ $page->title }}
        </h1>
        <p class="text-xs text-slate-400 mt-1">
            Last updated: {{ $page->updated_at->format('F d, Y') }}
        </p>
    </div>

    <!-- Article Content -->
    <div class="prose prose-slate max-w-none text-sm text-slate-700 leading-relaxed space-y-4">
        {!! nl2br(e($page->content)) !!}
    </div>

</div>
@endsection
