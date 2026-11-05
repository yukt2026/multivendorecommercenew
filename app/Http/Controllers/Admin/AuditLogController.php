<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    /**
     * Display searchable & filterable platform audit trail.
     */
    public function index(Request $request): View
    {
        $query = Activity::query()->with(['causer', 'subject'])->latest('id');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('log_name', 'like', "%{$search}%")
                  ->orWhere('event', 'like', "%{$search}%");
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

        if ($date = $request->query('date')) {
            $query->whereDate('created_at', $date);
        }

        $logs = $query->paginate(20)->withQueryString();

        $logNames = Activity::select('log_name')->distinct()->pluck('log_name');
        $events = Activity::select('event')->whereNotNull('event')->distinct()->pluck('event');

        $metrics = [
            'total_logs' => Activity::count(),
            'today_logs' => Activity::whereDate('created_at', today())->count(),
        ];

        return view('admin.audit.index', compact('logs', 'logNames', 'events', 'metrics'));
    }

    /**
     * Export audit logs as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="audit-logs-' . now()->format('Ymd-His') . '.csv"',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Timestamp', 'Log Category', 'Action / Event', 'Description', 'Actor (User)', 'Subject Type', 'Subject ID']);

            Activity::with('causer')->latest('id')->chunk(100, function ($logs) use ($handle) {
                foreach ($logs as $l) {
                    fputcsv($handle, [
                        $l->id,
                        $l->created_at->toDateTimeString(),
                        $l->log_name ?? 'default',
                        $l->event ?? 'action',
                        $l->description,
                        $l->causer?->name ?? 'System',
                        $l->subject_type ? class_basename($l->subject_type) : 'N/A',
                        $l->subject_id ?? 'N/A',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}
