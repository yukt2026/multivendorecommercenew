<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Payment\PaymentService;
use Illuminate\Database\Eloquent\Builder;
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
     * Shared query builder for payments table and CSV exports.
     */
    private function buildPaymentsQuery(Request $request): Builder
    {
        $query = Payment::query()->with(['order.customer.user', 'refunds'])->latest('id');

        if ($search = trim((string) $request->query('search', ''))) {
            $isExplicitId = str_starts_with($search, '#') && is_numeric(substr($search, 1));
            if ($isExplicitId) {
                $id = (int) substr($search, 1);
                $query->where(function (Builder $q) use ($id) {
                    $q->where('id', $id)->orWhere('order_id', $id);
                });
            } else {
                $query->where(function (Builder $q) use ($search) {
                    if (is_numeric($search)) {
                        $q->orWhere('id', (int) $search);
                    }
                    $q->orWhere('gateway_payment_id', 'like', "%{$search}%")
                      ->orWhere('gateway_order_id', 'like', "%{$search}%")
                      ->orWhereHas('order', fn(Builder $oq) => $oq->where('order_number', 'like', "%{$search}%"))
                      ->orWhereHas('order.customer.user', function (Builder $uq) use ($search) {
                          $uq->where('name', 'like', "%{$search}%")
                             ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }
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

        if ($method = $request->query('method')) {
            if ($method !== 'all') {
                $query->where('method', $method);
            }
        }

        if ($fromDate = $request->query('from_date')) {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate = $request->query('to_date')) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        return $query;
    }

    /**
     * Shared query builder for refunds table and CSV exports.
     */
    private function buildRefundsQuery(Request $request): Builder
    {
        $query = Refund::query()->with(['payment.order.customer.user', 'fulfilmentOrder'])->latest('id');

        if ($search = trim((string) $request->query('search', ''))) {
            $isExplicitId = str_starts_with($search, '#') && is_numeric(substr($search, 1));
            if ($isExplicitId) {
                $id = (int) substr($search, 1);
                $query->where(function (Builder $q) use ($id) {
                    $q->where('id', $id)->orWhere('payment_id', $id);
                });
            } else {
                $query->where(function (Builder $q) use ($search) {
                    if (is_numeric($search)) {
                        $q->orWhere('id', (int) $search)->orWhere('payment_id', (int) $search);
                    }
                    $q->orWhere('gateway_refund_id', 'like', "%{$search}%")
                      ->orWhere('reason', 'like', "%{$search}%")
                      ->orWhereHas('payment.order', fn(Builder $oq) => $oq->where('order_number', 'like', "%{$search}%"))
                      ->orWhereHas('payment.order.customer.user', fn(Builder $uq) => $uq->where('name', 'like', "%{$search}%"));
                });
            }
        }

        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        if ($fromDate = $request->query('from_date')) {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate = $request->query('to_date')) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        return $query;
    }

    /**
     * Display payments transaction ledger.
     */
    public function index(Request $request): View
    {
        $payments = $this->buildPaymentsQuery($request)->paginate(20)->withQueryString();

        $metrics = [
            'total_volume' => (float) Payment::where('status', PaymentStatus::PAID)->sum('amount'),
            'paid_count' => Payment::where('status', PaymentStatus::PAID)->count(),
            'refunded_amount' => (float) Refund::where('status', RefundStatus::PROCESSED)->sum('amount'),
            'cod_count' => Payment::where('method', PaymentMethod::COD)->count(),
            'pending_count' => Payment::whereIn('status', [PaymentStatus::PENDING, PaymentStatus::COD_PENDING])->count(),
            'failed_count' => Payment::where('status', PaymentStatus::FAILED)->count(),
        ];

        return view('admin.payments.index', compact('payments', 'metrics'));
    }

    /**
     * Display individual payment details with gateway audit log.
     */
    public function show(Payment $payment): View
    {
        $payment->load(['order.customer.user', 'order.fulfilmentOrders', 'refunds']);

        $totalRefunded = (float) $payment->refunds()->where('status', RefundStatus::PROCESSED)->sum('amount');
        $refundableBalance = max(0.00, round((float) $payment->amount - $totalRefunded, 2));

        return view('admin.payments.show', compact('payment', 'totalRefunded', 'refundableBalance'));
    }

    /**
     * Process refund on a paid or partially refunded payment.
     */
    public function refund(Request $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
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
     * Display list of all refunds with filter and metric cards.
     */
    public function refunds(Request $request): View
    {
        $refunds = $this->buildRefundsQuery($request)->paginate(20)->withQueryString();

        $metrics = [
            'total_refunded' => (float) Refund::where('status', RefundStatus::PROCESSED)->sum('amount'),
            'total_refunds_count' => Refund::count(),
            'pending_refunds_count' => Refund::where('status', RefundStatus::PENDING)->count(),
            'processed_count' => Refund::where('status', RefundStatus::PROCESSED)->count(),
        ];

        return view('admin.payments.refunds', compact('refunds', 'metrics'));
    }

    /**
     * Export payments ledger as CSV respecting current filters.
     */
    public function export(Request $request): StreamedResponse
    {
        $fileName = 'payments-' . now()->format('Ymd-His') . '.csv';

        return response()->stream(function () use ($request) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
            fputcsv($handle, [
                'Payment ID',
                'Parent Order #',
                'Customer Name',
                'Customer Email',
                'Gateway',
                'Method',
                'Amount (INR)',
                'Currency',
                'Status',
                'Gateway Payment ID',
                'Gateway Order ID',
                'Verified At',
                'Created At',
            ]);

            $this->buildPaymentsQuery($request)->chunk(200, function ($payments) use ($handle) {
                foreach ($payments as $p) {
                    fputcsv($handle, [
                        '#' . $p->id,
                        $p->order?->order_number ?? 'N/A',
                        $p->order?->customer?->user?->name ?? 'Guest',
                        $p->order?->customer?->user?->email ?? 'N/A',
                        strtoupper((string) $p->gateway),
                        $p->method instanceof PaymentMethod ? $p->method->label() : (string) $p->method,
                        number_format((float) $p->amount, 2, '.', ''),
                        $p->currency ?? 'INR',
                        $p->status instanceof PaymentStatus ? $p->status->label() : (string) $p->status,
                        $p->gateway_payment_id ?? '',
                        $p->gateway_order_id ?? '',
                        $p->verified_at?->toDateTimeString() ?? '',
                        $p->created_at?->toDateTimeString() ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    /**
     * Export refunds ledger as CSV respecting current filters.
     */
    public function exportRefunds(Request $request): StreamedResponse
    {
        $fileName = 'refunds-' . now()->format('Ymd-His') . '.csv';

        return response()->stream(function () use ($request) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
            fputcsv($handle, [
                'Refund ID',
                'Payment ID',
                'Order #',
                'Customer Name',
                'Reason',
                'Amount (INR)',
                'Status',
                'Gateway Refund ID',
                'Processed At',
                'Created At',
            ]);

            $this->buildRefundsQuery($request)->chunk(200, function ($refunds) use ($handle) {
                foreach ($refunds as $rf) {
                    fputcsv($handle, [
                        '#' . $rf->id,
                        '#' . $rf->payment_id,
                        $rf->payment?->order?->order_number ?? 'N/A',
                        $rf->payment?->order?->customer?->user?->name ?? 'N/A',
                        $rf->reason,
                        number_format((float) $rf->amount, 2, '.', ''),
                        $rf->status instanceof RefundStatus ? $rf->status->label() : (string) $rf->status,
                        $rf->gateway_refund_id ?? 'N/A',
                        $rf->processed_at?->toDateTimeString() ?? '',
                        $rf->created_at?->toDateTimeString() ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }
}
