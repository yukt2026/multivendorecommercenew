<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RedirectRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RedirectRuleController extends Controller
{
    /**
     * Ensure current user has permission to manage SEO redirects.
     */
    protected function authorizeAccess(): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        if (!$user->hasAnyRole(['super_admin', 'admin']) && !$user->can('settings.view') && !$user->can('settings.manage')) {
            abort(403, 'You do not have administrative authorization to manage URL redirects.');
        }
    }

    /**
     * Display list of redirect rules.
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess();

        $rules = $this->buildFilteredQuery($request)
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'total_rules' => RedirectRule::count(),
            'active_rules' => RedirectRule::where('is_active', true)->count(),
            'total_hits' => (int) RedirectRule::sum('hit_count'),
            'permanent_rules' => RedirectRule::where('status_code', 301)->count(),
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
        $this->authorizeAccess();

        $validated = $request->validate([
            'source_path' => ['required', 'string', 'max:500'],
            'target_path' => ['required', 'string', 'max:500'],
            'status_code' => ['required', 'in:301,302'],
            'is_active' => ['nullable'],
        ]);

        $source = RedirectRule::normalizePath($validated['source_path']);
        $target = trim($validated['target_path']);
        if (!str_starts_with($target, 'http://') && !str_starts_with($target, 'https://')) {
            $target = RedirectRule::normalizePath($target);
        }

        if ($source === $target) {
            return back()->with('error', 'Redirect loop detected: Source and target paths cannot be identical.')->withInput();
        }

        if (RedirectRule::where('source_path', $source)->exists()) {
            return back()->with('error', "A redirect rule for '{$source}' already exists.")->withInput();
        }

        if (RedirectRule::where('source_path', $target)->where('target_path', $source)->exists()) {
            return back()->with('error', "Circular redirect loop detected: '{$target}' already redirects back to '{$source}'.")->withInput();
        }

        $isActive = $request->has('is_active') ? $request->boolean('is_active') : true;

        $rule = RedirectRule::create([
            'source_path' => $source,
            'target_path' => $target,
            'status_code' => (int) $validated['status_code'],
            'is_active' => $isActive,
        ]);

        if (function_exists('activity')) {
            activity('settings')
                ->performedOn($rule)
                ->causedBy(auth()->user())
                ->withProperties(['source' => $source, 'target' => $target, 'code' => $validated['status_code']])
                ->log("Redirect rule created: '{$source}' -> '{$target}' ({$validated['status_code']})");
        }

        return back()->with('success', "Redirect rule from '{$source}' to '{$target}' created successfully.");
    }

    /**
     * Update existing redirect rule.
     */
    public function update(Request $request, RedirectRule $rule): RedirectResponse
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'source_path' => ['required', 'string', 'max:500'],
            'target_path' => ['required', 'string', 'max:500'],
            'status_code' => ['required', 'in:301,302'],
            'is_active' => ['nullable'],
        ]);

        $source = RedirectRule::normalizePath($validated['source_path']);
        $target = trim($validated['target_path']);
        if (!str_starts_with($target, 'http://') && !str_starts_with($target, 'https://')) {
            $target = RedirectRule::normalizePath($target);
        }

        if ($source === $target) {
            return back()->with('error', 'Redirect loop detected: Source and target paths cannot be identical.');
        }

        if (RedirectRule::where('source_path', $source)->where('id', '!=', $rule->id)->exists()) {
            return back()->with('error', "Another redirect rule for '{$source}' already exists.");
        }

        if (RedirectRule::where('source_path', $target)->where('target_path', $source)->where('id', '!=', $rule->id)->exists()) {
            return back()->with('error', "Circular redirect loop detected: '{$target}' already redirects back to '{$source}'.");
        }

        $isActive = $request->boolean('is_active');

        $rule->update([
            'source_path' => $source,
            'target_path' => $target,
            'status_code' => (int) $validated['status_code'],
            'is_active' => $isActive,
        ]);

        if (function_exists('activity')) {
            activity('settings')
                ->performedOn($rule)
                ->causedBy(auth()->user())
                ->withProperties(['source' => $source, 'target' => $target, 'is_active' => $isActive])
                ->log("Redirect rule #{$rule->id} updated");
        }

        return back()->with('success', "Redirect rule #{$rule->id} updated successfully.");
    }

    /**
     * Toggle active status.
     */
    public function toggle(RedirectRule $rule): RedirectResponse
    {
        $this->authorizeAccess();

        $rule->update(['is_active' => !$rule->is_active]);
        $statusStr = $rule->is_active ? 'activated' : 'deactivated';

        if (function_exists('activity')) {
            activity('settings')
                ->performedOn($rule)
                ->causedBy(auth()->user())
                ->log("Redirect rule #{$rule->id} {$statusStr}");
        }

        return back()->with('success', "Redirect rule #{$rule->id} has been {$statusStr}.");
    }

    /**
     * Delete redirect rule.
     */
    public function destroy(RedirectRule $rule): RedirectResponse
    {
        $this->authorizeAccess();

        $id = $rule->id;
        $source = $rule->source_path;
        $rule->delete();

        if (function_exists('activity')) {
            activity('settings')
                ->causedBy(auth()->user())
                ->log("Redirect rule #{$id} ({$source}) deleted");
        }

        return back()->with('success', "Redirect rule #{$id} deleted successfully.");
    }

    /**
     * Export redirect rules as CSV honoring active filters.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorizeAccess();

        $filename = 'redirect-rules-' . now()->format('Ymd-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($request) {
            $handle = fopen('php://output', 'w');

            // Insert UTF-8 BOM
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'ID',
                'Source Path (Old URL)',
                'Target Destination (New URL)',
                'HTTP Status Code',
                'State',
                'Hit Count',
                'Last Accessed At',
            ]);

            $this->buildFilteredQuery($request)->chunk(250, function ($rules) use ($handle) {
                foreach ($rules as $r) {
                    fputcsv($handle, [
                        $r->id,
                        $r->source_path,
                        $r->target_path,
                        $r->status_code,
                        $r->is_active ? 'Active' : 'Disabled',
                        $r->hit_count,
                        $r->last_accessed_at?->toDateTimeString() ?? 'Never',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Bulk CSV import of redirects.
     */
    public function importCsv(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

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
                    // Strip UTF-8 BOM if present on first column
                    if (isset($row[0])) {
                        $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
                    }
                    // Check if header row
                    $firstCol = strtolower(trim((string) ($row[0] ?? '')));
                    if (in_array($firstCol, ['source', 'source_path', 'old_url', 'source path', 'id'], true)) {
                        continue;
                    }
                }

                if (count($row) >= 2 && !empty(trim((string) $row[0])) && !empty(trim((string) $row[1]))) {
                    $src = RedirectRule::normalizePath(trim((string) $row[0]));
                    $tgt = trim((string) $row[1]);
                    if (!str_starts_with($tgt, 'http://') && !str_starts_with($tgt, 'https://')) {
                        $tgt = RedirectRule::normalizePath($tgt);
                    }

                    $code = isset($row[2]) && in_array(trim((string) $row[2]), ['301', '302'], true) ? (int) trim((string) $row[2]) : 301;

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

        if (function_exists('activity')) {
            activity('settings')
                ->causedBy(auth()->user())
                ->withProperties(['imported' => $imported, 'skipped' => $skipped])
                ->log("Bulk CSV import: {$imported} redirect rules created/updated, {$skipped} skipped");
        }

        return back()->with('success', "CSV Import completed: {$imported} redirect rules created/updated, {$skipped} skipped.");
    }

    /**
     * Shared filter builder for listing and export.
     */
    protected function buildFilteredQuery(Request $request): Builder
    {
        $query = RedirectRule::query();

        if ($request->filled('search')) {
            $s = trim((string) $request->search);
            $query->where(function (Builder $q) use ($s) {
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

        return $query;
    }
}
