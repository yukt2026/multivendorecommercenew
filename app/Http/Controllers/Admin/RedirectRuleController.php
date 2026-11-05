<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RedirectRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RedirectRuleController extends Controller
{
    /**
     * Display list of redirect rules.
     */
    public function index(Request $request): View
    {
        $query = RedirectRule::query();

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('source_path', 'like', "%{$s}%")
                    ->orWhere('target_path', 'like', "%{$s}%");
            });
        }

        if ($request->filled('code') && $request->code !== 'all') {
            $query->where('status_code', (int) $request->code);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('is_active', $request->status === 'active');
        }

        $rules = $query->latest('id')->paginate(20)->withQueryString();

        $stats = [
            'total_rules' => RedirectRule::count(),
            'active_rules' => RedirectRule::where('is_active', true)->count(),
            'total_hits' => RedirectRule::sum('hit_count'),
        ];

        return view('admin.seo.redirects', [
            'rules' => $rules,
            'stats' => $stats,
            'search' => $request->query('search', ''),
            'selectedCode' => $request->query('code', 'all'),
            'selectedStatus' => $request->query('status', 'all'),
        ]);
    }

    /**
     * Store new redirect rule.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_path' => ['required', 'string', 'max:500'],
            'target_path' => ['required', 'string', 'max:500'],
            'status_code' => ['required', 'in:301,302'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $source = RedirectRule::normalizePath($validated['source_path']);
        $target = trim($validated['target_path']);
        if (!str_starts_with($target, 'http://') && !str_starts_with($target, 'https://')) {
            $target = RedirectRule::normalizePath($target);
        }

        if ($source === $target) {
            return back()->with('error', 'Redirect loop detected: Source and target paths cannot be identical.');
        }

        if (RedirectRule::where('source_path', $source)->exists()) {
            return back()->with('error', "A redirect rule for '{$source}' already exists.");
        }

        RedirectRule::create([
            'source_path' => $source,
            'target_path' => $target,
            'status_code' => (int) $validated['status_code'],
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        return back()->with('success', "Redirect rule from '{$source}' to '{$target}' created successfully.");
    }

    /**
     * Update existing redirect rule.
     */
    public function update(Request $request, RedirectRule $rule): RedirectResponse
    {
        $validated = $request->validate([
            'source_path' => ['required', 'string', 'max:500'],
            'target_path' => ['required', 'string', 'max:500'],
            'status_code' => ['required', 'in:301,302'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $source = RedirectRule::normalizePath($validated['source_path']);
        $target = trim($validated['target_path']);
        if (!str_starts_with($target, 'http://') && !str_starts_with($target, 'https://')) {
            $target = RedirectRule::normalizePath($target);
        }

        if ($source === $target) {
            return back()->with('error', 'Redirect loop detected: Source and target paths cannot be identical.');
        }

        $rule->update([
            'source_path' => $source,
            'target_path' => $target,
            'status_code' => (int) $validated['status_code'],
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        return back()->with('success', "Redirect rule #{$rule->id} updated successfully.");
    }

    /**
     * Toggle active status.
     */
    public function toggle(RedirectRule $rule): RedirectResponse
    {
        $rule->update(['is_active' => !$rule->is_active]);
        $statusStr = $rule->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Redirect rule #{$rule->id} has been {$statusStr}.");
    }

    /**
     * Delete redirect rule.
     */
    public function destroy(RedirectRule $rule): RedirectResponse
    {
        $rule->delete();

        return back()->with('success', "Redirect rule #{$rule->id} deleted successfully.");
    }

    /**
     * Bulk CSV import of redirects.
     */
    public function importCsv(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $file = $request->file('csv_file');
        if (!$file) {
            return back()->with('error', 'CSV file is required.');
        }

        $imported = 0;
        $skipped = 0;

        if (($handle = fopen($file->getRealPath(), 'r')) !== false) {
            $isFirst = true;
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if ($isFirst) {
                    $isFirst = false;
                    // Check if header row
                    if (isset($row[0]) && strtolower(trim($row[0])) === 'source') {
                        continue;
                    }
                }

                if (count($row) >= 2 && !empty(trim($row[0])) && !empty(trim($row[1]))) {
                    $src = RedirectRule::normalizePath(trim($row[0]));
                    $tgt = trim($row[1]);
                    $code = isset($row[2]) && in_array(trim($row[2]), ['301', '302'], true) ? (int) trim($row[2]) : 301;

                    if ($src !== $tgt) {
                        RedirectRule::updateOrCreate(
                            ['source_path' => $src],
                            ['target_path' => $tgt, 'status_code' => $code, 'is_active' => true]
                        );
                        $imported++;
                    } else {
                        $skipped++;
                    }
                }
            }
            fclose($handle);
        }

        return back()->with('success', "CSV Import completed: {$imported} redirect rules created/updated, {$skipped} skipped.");
    }
}
