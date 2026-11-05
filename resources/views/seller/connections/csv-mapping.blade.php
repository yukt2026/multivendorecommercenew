@extends('layouts.dashboard', ['portal' => 'seller', 'title' => 'Configure Column Mapping'])

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="Column Mapping &amp; Dry-Run Preview" 
        subtitle="Map spreadsheet columns to catalog fields and verify sample row parsing before queuing."
        :breadcrumbs="['Seller Studio' => route('seller.dashboard'), 'CSV Feed' => route('seller.connections.csv'), 'Mapping' => '#']"
    />

    <form method="POST" action="{{ route('seller.connections.csv.import', $import) }}" class="space-y-6">
        @csrf

        <!-- Mapping Configuration Card -->
        <x-card title="Map Spreadsheet Headers to Catalog Fields">
            <div class="p-3 mb-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700/60 flex items-center justify-between text-xs">
                <div>
                    <span class="font-bold text-slate-900 dark:text-white">File: {{ $import->file_name }}</span>
                    <span class="text-slate-500 ml-2">&bull; Target Store: {{ $store->name }} &bull; {{ $totalRows }} total rows detected</span>
                </div>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400">
                    Auto-Mapping Applied
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                @php
                    $fields = [
                        'sku' => ['label' => 'SKU / Barcode', 'required' => false],
                        'title' => ['label' => 'Product Title', 'required' => true],
                        'category' => ['label' => 'Category', 'required' => false],
                        'selling_price' => ['label' => 'Selling Price (₹)', 'required' => true],
                        'mrp' => ['label' => 'MRP (₹)', 'required' => false],
                        'quantity' => ['label' => 'Stock Quantity', 'required' => false],
                        'weight_grams' => ['label' => 'Weight (Grams)', 'required' => false],
                        'description' => ['label' => 'Description', 'required' => false],
                    ];
                @endphp

                @foreach($fields as $fieldKey => $meta)
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                            {{ $meta['label'] }}
                            @if($meta['required']) <span class="text-rose-500">*</span> @endif
                        </label>
                        <select name="mapping[{{ $fieldKey }}]" class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white font-mono">
                            <option value="">-- Ignore Column --</option>
                            @foreach($headers as $h)
                                <option value="{{ $h }}" {{ ($autoMapping[$fieldKey] ?? '') === $h ? 'selected' : '' }}>
                                    {{ $h }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>
        </x-card>

        <!-- Preview Rows Card -->
        <x-card title="First 5 Sample Rows Preview" :padded="false">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            @foreach($headers as $h)
                                <th class="py-2.5 px-3 font-mono">{{ $h }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                        @foreach($previewRows as $row)
                            <tr class="hover:bg-slate-50/50">
                                @foreach($headers as $h)
                                    <td class="py-2.5 px-3 text-slate-700 dark:text-slate-300 truncate max-w-xs">
                                        {{ $row[$h] ?? '-' }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <!-- Actions -->
        <div class="flex items-center justify-between">
            <a href="{{ route('seller.connections.csv') }}" class="text-xs font-semibold text-slate-500 hover:underline">
                &larr; Back to Imports
            </a>

            <div class="flex items-center space-x-3">
                <button type="submit" name="is_dry_run" value="1" class="px-4 py-2 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                    Perform Dry Run Test
                </button>
                <x-button variant="primary" size="sm" type="submit">
                    Execute Bulk Import &rarr;
                </x-button>
            </div>
        </div>
    </form>
</div>
@endsection
