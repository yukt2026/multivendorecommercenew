@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'CSV / Excel Bulk Catalog Feed'])

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="CSV / Excel Bulk Catalog Feed" 
        subtitle="Upload bulk spreadsheet files to create products, variants, and stock balances with dry-run validation."
        :breadcrumbs="['Seller Studio' => route('seller.dashboard'), 'Connect My Shop' => route('seller.connections.index'), 'CSV Import' => '#']"
    >
        <x-slot name="actions">
            <x-button variant="outline" size="sm" href="{{ route('seller.connections.csv.template') }}" icon="download">
                Download CSV Template
            </x-button>
        </x-slot>
    </x-page-header>

    <!-- Upload Card -->
    <x-card title="Upload Catalog Spreadsheet">
        <form method="POST" action="{{ route('seller.connections.csv.upload') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Target Physical Store / Hub <span class="text-rose-500">*</span></label>
                    <select name="store_id" required class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white font-semibold">
                        @foreach($stores as $st)
                            <option value="{{ $st->id }}" {{ $activeStore && $activeStore->id === $st->id ? 'selected' : '' }}>
                                {{ $st->name }} ({{ $st->location?->city ?? 'Central Hub' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Select File (.csv) <span class="text-rose-500">*</span></label>
                    <input type="file" name="csv_file" accept=".csv,text/csv" required class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-1.5 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                </div>
            </div>

            <div class="flex items-center justify-between pt-2">
                <p class="text-xs text-slate-500">Supports standard CSV formatting. Maximum file size: 10MB.</p>
                <x-button variant="primary" size="sm" type="submit">Upload &amp; Configure Mapping &rarr;</x-button>
            </div>
        </form>
    </x-card>

    <!-- Import History Table -->
    <x-card title="Import History &amp; Queue Status" :padded="false">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        <th class="py-3 px-4">File Name</th>
                        <th class="py-3 px-4">Target Store</th>
                        <th class="py-3 px-4">Progress</th>
                        <th class="py-3 px-4 text-center">Imported</th>
                        <th class="py-3 px-4 text-center">Failed</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Uploaded At</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                    @forelse($imports as $imp)
                        <tr>
                            <td class="py-3 px-4 font-bold text-slate-900 dark:text-white flex items-center space-x-2">
                                <span class="text-slate-400">📄</span>
                                <span class="truncate max-w-xs">{{ $imp->file_name }}</span>
                            </td>
                            <td class="py-3 px-4 text-slate-800 dark:text-slate-200">
                                {{ $imp->store->name }}
                            </td>
                            <td class="py-3 px-4 w-44">
                                <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2 overflow-hidden mb-1">
                                    <div class="bg-indigo-600 h-2 rounded-full" style="width: {{ $imp->progress_percent }}%"></div>
                                </div>
                                <span class="text-[10px] text-slate-400 font-mono">{{ $imp->imported_rows + $imp->failed_rows }} of {{ $imp->total_rows }} rows ({{ $imp->progress_percent }}%)</span>
                            </td>
                            <td class="py-3 px-4 text-center font-mono font-bold text-emerald-600">
                                {{ $imp->imported_rows }}
                            </td>
                            <td class="py-3 px-4 text-center font-mono font-bold text-rose-500">
                                {{ $imp->failed_rows }}
                            </td>
                            <td class="py-3 px-4">
                                <x-badge :variant="$imp->status === 'completed' ? 'success' : ($imp->status === 'processing' ? 'warning' : ($imp->status === 'failed' ? 'danger' : 'neutral'))" size="sm" dot>
                                    {{ ucfirst($imp->status) }}
                                </x-badge>
                            </td>
                            <td class="py-3 px-4 text-right text-slate-400 text-[11px]">
                                {{ $imp->created_at->diffForHumans() }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-6 text-center text-slate-400">
                                No spreadsheet imports uploaded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($imports->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $imports->links() }}
            </div>
        @endif
    </x-card>
</div>
@endsection
