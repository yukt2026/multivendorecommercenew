<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    /**
     * Standard administrative log categories.
     */
    protected array $standardCategories = [
        'settings',
        'staff',
        'role',
        'store',
        'payment',
        'support',
        'cms',
        'banner',
        'campaign',
        'coupon',
        'customer',
        'auth',
        'inventory',
        'shipping',
        'settlement',
    ];

    /**
     * Ensure current user has permission to access audit ledger.
     */
    protected function authorizeAccess(): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        if (!$user->hasAnyRole(['super_admin', 'admin']) && !$user->can('audit_logs.view')) {
            abort(403, 'You do not have administrative authorization to view system audit logs.');
        }
    }

    /**
     * Display searchable & filterable platform audit trail.
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess();

        $logs = $this->buildFilteredQuery($request)
            ->paginate(25)
            ->withQueryString();

        $existingLogNames = Activity::select('log_name')
            ->whereNotNull('log_name')
            ->distinct()
            ->pluck('log_name')
            ->toArray();

        $logNames = collect(array_unique(array_merge($this->standardCategories, $existingLogNames)))
            ->sort()
            ->values();

        $events = Activity::select('event')
            ->whereNotNull('event')
            ->distinct()
            ->orderBy('event')
            ->pluck('event');

        $metrics = [
            'total_logs' => Activity::count(),
            'today_logs' => Activity::whereDate('created_at', today())->count(),
            'admin_actions' => Activity::whereNotNull('causer_id')->count(),
            'system_events' => Activity::whereNull('causer_id')->count(),
        ];

        return view('admin.audit.index', compact('logs', 'logNames', 'events', 'metrics'));
    }

    /**
     * Export audit logs as CSV honoring all active filters.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorizeAccess();

        $filename = 'audit-logs-' . now()->format('Ymd-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($request) {
            $handle = fopen('php://output', 'w');

            // Insert UTF-8 Byte Order Mark (BOM) for native Excel UTF-8 support
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'Log ID',
                'Timestamp (UTC)',
                'Module / Category',
                'Event',
                'Description',
                'Actor Name',
                'Actor Email',
                'Subject Type',
                'Subject ID',
                'Properties (JSON)',
            ]);

            $this->buildFilteredQuery($request)->chunk(250, function ($logs) use ($handle) {
                foreach ($logs as $l) {
                    $propertiesJson = '';
                    if ($l->properties && $l->properties->isNotEmpty()) {
                        $propertiesJson = json_encode($l->properties->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    }

                    fputcsv($handle, [
                        $l->id,
                        $l->created_at?->toDateTimeString() ?? 'N/A',
                        $l->log_name ?? 'default',
                        $l->event ?? 'action',
                        $l->description,
                        $l->causer?->name ?? 'System Process',
                        $l->causer?->email ?? 'N/A',
                        $l->subject_type ? class_basename($l->subject_type) : 'N/A',
                        $l->subject_id ?? 'N/A',
                        $propertiesJson,
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Purge historical audit logs older than retention cutoff (Super Admin only).
     */
    public function purge(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Only Super Administrators are authorized to purge system audit logs.');
        }

        $validated = $request->validate([
            'days' => ['required', 'integer', 'in:30,60,90,180,365'],
        ]);

        $days = (int) $validated['days'];
        $cutoff = Carbon::now()->subDays($days);

        $deletedCount = Activity::where('created_at', '<', $cutoff)->delete();

        if (function_exists('activity')) {
            activity('audit')
                ->causedBy(auth()->user())
                ->withProperties(['days' => $days, 'deleted_count' => $deletedCount])
                ->log("Purged {$deletedCount} historical audit log records older than {$days} days");
        }

        return redirect()->route('admin.audit-logs.index')->with(
            'success',
            "Retention maintenance complete: {$deletedCount} records older than {$days} days were successfully purged."
        );
    }

    /**
     * Shared filter query builder for listing and export.
     */
    protected function buildFilteredQuery(Request $request): Builder
    {
        $query = Activity::query()->with(['causer', 'subject'])->latest('id');

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('log_name', 'like', "%{$search}%")
                  ->orWhere('event', 'like', "%{$search}%")
                  ->orWhere('subject_type', 'like', "%{$search}%")
                  ->orWhere('subject_id', 'like', "%{$search}%")
                  ->orWhereHasMorph('causer', [User::class], function (Builder $uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($event = $request->query('event')) {
            if ($event !== 'all') {
                $query->where('event', $event);
            }
        }

        if ($logName = $request->query('log_name')) {
            if ($logName !== 'all') {
                $query->where('log_name', $logName);
            }
        }

        if ($from = $request->query('from_date')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('to_date')) {
            $query->whereDate('created_at', '<=', $to);
        }

        if ($date = $request->query('date')) {
            $query->whereDate('created_at', $date);
        }

        return $query;
    }
}
