<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Services\Notification\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotificationLogController extends Controller
{
    /**
     * Ensure current user has permission to view notification delivery telemetry.
     */
    protected function authorizeAccess(): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        if (!$user->hasAnyRole(['super_admin', 'admin']) && !$user->can('settings.view') && !$user->can('audit_logs.view')) {
            abort(403, 'You do not have administrative authorization to view notification delivery logs.');
        }
    }

    /**
     * Display notification audit logs with telemetry metrics.
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess();

        $logs = $this->buildFilteredQuery($request)
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'total' => NotificationLog::count(),
            'sent' => NotificationLog::where('status', 'sent')->count(),
            'failed' => NotificationLog::where('status', 'failed')->count(),
            'retried' => NotificationLog::where('retry_count', '>', 0)->count(),
        ];

        return view('admin.notifications.logs', [
            'logs' => $logs,
            'stats' => $stats,
            'selectedChannel' => $request->query('channel', 'all'),
            'selectedStatus' => $request->query('status', 'all'),
            'selectedEvent' => $request->query('event', 'all'),
            'search' => $request->query('search', ''),
            'selectedDate' => $request->query('date', ''),
        ]);
    }

    /**
     * Retry a single failed notification dispatch.
     */
    public function retry(NotificationLog $log, NotificationService $service): RedirectResponse
    {
        $this->authorizeAccess();

        if (!$log->isFailed()) {
            return back()->with('error', 'Only failed notification dispatches can be retried.');
        }

        $success = $service->retryFailedLog($log);

        if (function_exists('activity')) {
            activity('notification')
                ->performedOn($log)
                ->causedBy(auth()->user())
                ->withProperties([
                    'channel' => $log->channel,
                    'recipient' => $log->recipient,
                    'success' => $success,
                ])
                ->log("Manual retry executed for notification #{$log->id}");
        }

        if ($success) {
            return back()->with('success', "Notification #{$log->id} re-sent successfully via {$log->channel}.");
        }

        return back()->with('error', "Failed to re-send notification #{$log->id}: " . ($log->error_message ?? 'Gateway rejected transmission.'));
    }

    /**
     * Bulk retry all pending failed notification logs (up to 50 at a time).
     */
    public function retryAllFailed(NotificationService $service): RedirectResponse
    {
        $this->authorizeAccess();

        $failedLogs = NotificationLog::where('status', 'failed')
            ->latest('id')
            ->limit(50)
            ->get();

        if ($failedLogs->isEmpty()) {
            return back()->with('info', 'There are no pending failed notifications to retry.');
        }

        $successCount = 0;
        $failedCount = 0;

        foreach ($failedLogs as $log) {
            if ($service->retryFailedLog($log)) {
                $successCount++;
            } else {
                $failedCount++;
            }
        }

        if (function_exists('activity')) {
            activity('notification')
                ->causedBy(auth()->user())
                ->withProperties([
                    'total_retried' => $failedLogs->count(),
                    'success_count' => $successCount,
                    'failed_count' => $failedCount,
                ])
                ->log("Bulk retry executed: {$successCount} recovered, {$failedCount} failed");
        }

        if ($failedCount === 0) {
            return back()->with('success', "All {$successCount} failed notifications were successfully re-sent.");
        }

        return back()->with(
            'warning',
            "Batch retry finished: {$successCount} notifications recovered, {$failedCount} still failing. Check gateway logs."
        );
    }

    /**
     * Export notification delivery logs as CSV honoring active filters.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorizeAccess();

        $filename = 'notification-logs-' . now()->format('Ymd-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($request) {
            $handle = fopen('php://output', 'w');

            // Insert UTF-8 Byte Order Mark for Excel
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'Log ID',
                'Timestamp (UTC)',
                'Channel',
                'Event Key',
                'Recipient',
                'Subject',
                'Status',
                'Retry Count',
                'Content Payload',
                'Error Message',
            ]);

            $this->buildFilteredQuery($request)->chunk(250, function ($logs) use ($handle) {
                foreach ($logs as $l) {
                    fputcsv($handle, [
                        $l->id,
                        $l->created_at?->toDateTimeString() ?? 'N/A',
                        $l->channel,
                        $l->event_key,
                        $l->recipient,
                        $l->subject ?? 'N/A',
                        ucfirst($l->status),
                        $l->retry_count,
                        $l->content,
                        $l->error_message ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Shared filter builder for listing and export.
     */
    protected function buildFilteredQuery(Request $request): Builder
    {
        $query = NotificationLog::query();

        if ($request->filled('channel') && $request->channel !== 'all') {
            $query->where('channel', $request->channel);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('event') && $request->event !== 'all') {
            $query->where('event_key', $request->event);
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        if ($request->filled('search')) {
            $s = trim((string) $request->search);
            $query->where(function (Builder $q) use ($s) {
                $q->where('recipient', 'like', "%{$s}%")
                    ->orWhere('subject', 'like', "%{$s}%")
                    ->orWhere('content', 'like', "%{$s}%")
                    ->orWhere('error_message', 'like', "%{$s}%")
                    ->orWhere('event_key', 'like', "%{$s}%");
            });
        }

        return $query;
    }
}
