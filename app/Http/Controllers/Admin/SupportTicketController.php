<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportTicketController extends Controller
{
    /**
     * Display support tickets list.
     */
    public function index(Request $request): View
    {
        $tickets = $this->buildTicketsQuery($request)->paginate(15)->withQueryString();

        $metrics = [
            'total_tickets' => SupportTicket::count(),
            'open_tickets' => SupportTicket::where('status', TicketStatus::OPEN)->count(),
            'in_progress' => SupportTicket::where('status', TicketStatus::IN_PROGRESS)->count(),
            'resolved' => SupportTicket::where('status', TicketStatus::RESOLVED)->count(),
        ];

        $staff = $this->getStaffUsers();

        return view('admin.support.index', compact('tickets', 'metrics', 'staff'));
    }

    /**
     * Display single ticket thread.
     */
    public function show(SupportTicket $ticket): View
    {
        $ticket->load(['user', 'seller', 'order', 'assignedAgent', 'messages.user']);
        $staff = $this->getStaffUsers();

        return view('admin.support.show', compact('ticket', 'staff'));
    }

    /**
     * Post a reply message or internal note.
     */
    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
        ]);

        TicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'message' => $validated['message'],
            'is_internal' => $request->boolean('is_internal'),
        ]);

        if (!$request->boolean('is_internal') && $ticket->status === TicketStatus::OPEN) {
            $ticket->status = TicketStatus::IN_PROGRESS;
            $ticket->save();
        }

        if (function_exists('activity')) {
            activity('support')
                ->performedOn($ticket)
                ->causedBy(auth()->user())
                ->log("Replied to ticket #{$ticket->ticket_number}" . ($request->boolean('is_internal') ? ' (internal note)' : ''));
        }

        return back()->with('success', 'Reply posted successfully.');
    }

    /**
     * Assign ticket to an agent.
     */
    public function assign(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $ticket->assigned_to = $validated['assigned_to'] ? (int)$validated['assigned_to'] : null;
        if ($ticket->status === TicketStatus::OPEN && $ticket->assigned_to) {
            $ticket->status = TicketStatus::IN_PROGRESS;
        }
        $ticket->save();

        return back()->with('success', 'Ticket assignment updated.');
    }

    /**
     * Update ticket status.
     */
    public function updateStatus(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:open,in_progress,waiting_customer,resolved,closed'],
        ]);

        $ticket->status = TicketStatus::from($validated['status']);
        $ticket->save();

        if (function_exists('activity')) {
            activity('support')
                ->performedOn($ticket)
                ->causedBy(auth()->user())
                ->log("Ticket #{$ticket->ticket_number} status changed to {$validated['status']}");
        }

        return back()->with('success', "Ticket marked as {$ticket->status->label()}.");
    }

    /**
     * AI: Generate contextual automated support reply or draft.
     */
    public function generateAiReply(Request $request, SupportTicket $ticket, \App\Services\AI\Contracts\SupportAiReplyInterface $aiReplyService): \Illuminate\Http\JsonResponse|RedirectResponse
    {
        $autoSend = $request->boolean('auto_send', false);
        $result = $aiReplyService->generateReply($ticket, $request->input('message'), $autoSend);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        }

        if ($result['auto_posted']) {
            return back()->with('success', "AI assistant posted reply (Confidence: " . round($result['confidence'] * 100) . "%).");
        }

        return back()->with('info', "AI Suggested Reply:\n\n{$result['reply_text']}");
    }

    /**
     * Alias for updateStatus for route compatibility.
     */
    public function status(Request $request, SupportTicket $ticket): RedirectResponse
    {
        return $this->updateStatus($request, $ticket);
    }

    /**
     * Export support tickets as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="tickets-' . now()->format('Ymd-His') . '.csv"',
        ];

        $query = $this->buildTicketsQuery($request);

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
            fputcsv($handle, ['Ticket #', 'Subject', 'Customer', 'Category', 'Priority', 'Status', 'Assigned Agent', 'Created At']);

            $query->chunk(100, function ($tickets) use ($handle) {
                foreach ($tickets as $t) {
                    $stVal = is_object($t->status) ? $t->status->value : (string) $t->status;
                    $prVal = is_object($t->priority) ? $t->priority->value : (string) $t->priority;
                    fputcsv($handle, [
                        $t->ticket_number,
                        $t->subject,
                        $t->user?->name ?? 'N/A',
                        $t->category ?? 'General',
                        ucfirst($prVal),
                        ucfirst(str_replace('_', ' ', $stVal)),
                        $t->assignedAgent?->name ?? 'Unassigned',
                        $t->created_at?->toDateTimeString() ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Build base query for tickets listing and export.
     */
    protected function buildTicketsQuery(Request $request)
    {
        $query = SupportTicket::query()->with(['user', 'seller', 'order', 'assignedAgent'])->latest('id');

        if ($search = $request->query('search')) {
            $search = trim((string) $search);
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        if ($priority = $request->query('priority')) {
            if ($priority !== 'all') {
                $query->where('priority', $priority);
            }
        }

        return $query;
    }

    /**
     * Safely resolve admin & support staff users.
     */
    protected function getStaffUsers()
    {
        $adminRoles = ['super_admin', 'admin', 'support_admin'];
        $existingAdminRoles = \Spatie\Permission\Models\Role::whereIn('name', $adminRoles)->pluck('name')->toArray();
        return !empty($existingAdminRoles) ? User::role($existingAdminRoles)->get() : collect();
    }
}
