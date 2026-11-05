<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Payment\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Display payments transaction ledger.
     */
    public function index(Request $request): View
    {
        $query = Payment::query()->with(['order.customer.user', 'refunds'])->latest('id');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('gateway_payment_id', 'like', "%{$search}%")
                  ->orWhere('gateway_order_id', 'like', "%{$search}%")
                  ->orWhereHas('order', fn($oq) => $oq->where('order_number', 'like', "%{$search}%"));
            });
        }

        if ($gateway = $request->query('gateway')) {
            if ($gateway !== 'all') {
                $query->where('gateway', $gateway);
            }
        }

        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        $payments = $query->paginate(20)->withQueryString();

        $metrics = [
            'total_volume' => (float) Payment::where('status', PaymentStatus::PAID)->sum('amount'),
            'paid_count' => Payment::where('status', PaymentStatus::PAID)->count(),
            'refunded_amount' => (float) Refund::where('status', 'processed')->sum('amount'),
            'cod_count' => Payment::where('method', 'cod')->count(),
        ];

        return view('admin.payments.index', compact('payments', 'metrics'));
    }

    /**
     * Display individual payment details with gateway audit log.
     */
    public function show(Payment $payment): View
    {
        $payment->load(['order.customer.user', 'order.fulfilmentOrders', 'refunds']);

        $totalRefunded = (float) $payment->refunds()->where('status', 'processed')->sum('amount');
        $refundableBalance = max(0.00, (float) $payment->amount - $totalRefunded);

        return view('admin.payments.show', compact('payment', 'totalRefunded', 'refundableBalance'));
    }

    /**
     * Process refund on a paid payment.
     */
    public function refund(Request $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $refund = $this->paymentService->processRefund(
                $payment,
                (float) $validated['amount'],
                $validated['reason']
            );

            if (function_exists('activity')) {
                activity('payment')
                    ->performedOn($payment)
                    ->causedBy(auth()->user())
                    ->withProperties(['refund_id' => $refund->id, 'amount' => $validated['amount']])
                    ->log("Refund of " . format_currency((float)$validated['amount']) . " initiated for Payment #{$payment->id}");
            }

            return back()->with('success', "Refund of " . format_currency((float)$validated['amount']) . " processed successfully.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Display list of all refunds.
     */
    public function refunds(Request $request): View
    {
        $query = Refund::query()->with(['payment.order.customer.user', 'fulfilmentOrder'])->latest('id');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('gateway_refund_id', 'like', "%{$search}%")
                  ->orWhereHas('payment.order', fn($oq) => $oq->where('order_number', 'like', "%{$search}%"));
            });
        }

        $refunds = $query->paginate(20)->withQueryString();

        $metrics = [
            'total_refunded' => (float) Refund::where('status', 'processed')->sum('amount'),
            'total_refunds_count' => Refund::count(),
            'pending_refunds_count' => Refund::where('status', 'pending')->count(),
        ];

        return view('admin.payments.refunds', compact('refunds', 'metrics'));
    }

    /**
     * Export payments ledger as CSV.
     */
    public function export(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="payments-' . now()->format('Ymd-His') . '.csv"',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Payment ID', 'Order #', 'Customer', 'Gateway', 'Method', 'Amount', 'Status', 'Gateway Payment ID', 'Verified At']);

            Payment::with('order.customer.user')->chunk(100, function ($payments) use ($handle) {
                foreach ($payments as $p) {
                    fputcsv($handle, [
                        $p->id,
                        $p->order?->order_number ?? 'N/A',
                        $p->order?->customer?->user?->name ?? 'N/A',
                        $p->gateway,
                        $p->method?->value ?? (string) $p->method,
                        $p->amount,
                        $p->status?->value ?? (string) $p->status,
                        $p->gateway_payment_id ?? '',
                        $p->verified_at?->toDateTimeString() ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}
