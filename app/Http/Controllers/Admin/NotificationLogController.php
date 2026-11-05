<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Services\Notification\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationLogController extends Controller
{
    /**
     * Display notification audit logs.
     */
    public function index(Request $request): View
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

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('recipient', 'like', "%{$s}%")
                    ->orWhere('content', 'like', "%{$s}%")
                    ->orWhere('error_message', 'like', "%{$s}%");
            });
        }

        $logs = $query->latest('id')->paginate(20)->withQueryString();

        $stats = [
            'total' => NotificationLog::count(),
            'sent' => NotificationLog::where('status', 'sent')->count(),
            'failed' => NotificationLog::where('status', 'failed')->count(),
            'retried' => NotificationLog::where('status', 'retried')->count(),
        ];

        return view('admin.notifications.logs', [
            'logs' => $logs,
            'stats' => $stats,
            'selectedChannel' => $request->query('channel', 'all'),
            'selectedStatus' => $request->query('status', 'all'),
            'selectedEvent' => $request->query('event', 'all'),
            'search' => $request->query('search', ''),
        ]);
    }

    /**
     * Retry a failed notification.
     */
    public function retry(NotificationLog $log, NotificationService $service): RedirectResponse
    {
        if (!$log->isFailed()) {
            return back()->with('error', 'Only failed notifications can be retried.');
        }

        $success = $service->retryFailedLog($log);

        if ($success) {
            return back()->with('success', "Notification #{$log->id} re-sent successfully.");
        }

        return back()->with('error', "Failed to re-send notification #{$log->id}: " . ($log->error_message ?? 'Unknown error'));
    }
}
