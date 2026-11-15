<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    /**
     * Display searchable & filterable customer list.
     */
    public function index(Request $request): View
    {
        $query = Customer::query()->with(['user', 'defaultAddress'])->latest('id');

        if ($search = $request->query('search')) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                $query->whereHas('user', function ($q) use ($status) {
                    if ($status === 'blocked' || $status === 'suspended') {
                        $q->whereIn('status', [UserStatus::SUSPENDED, 'suspended', 'blocked']);
                    } else {
                        $q->where('status', $status);
                    }
                });
            }
        }

        $customers = $query->paginate(15)->withQueryString();

        $metrics = [
            'total_customers' => Customer::count(),
            'active_customers' => Customer::whereHas('user', fn($q) => $q->where('status', UserStatus::ACTIVE))->count(),
            'blocked_customers' => Customer::whereHas('user', fn($q) => $q->whereIn('status', [UserStatus::SUSPENDED, 'suspended', 'blocked']))->count(),
            'new_this_month' => Customer::where('created_at', '>=', now()->startOfMonth())->count(),
        ];

        return view('admin.customers.index', compact('customers', 'metrics'));
    }

    /**
     * Display customer detail with address and order history.
     */
    public function show(Customer $customer): View
    {
        $customer->load(['user', 'addresses', 'orders.fulfilmentOrders', 'wishlists.product']);

        $lifetimeSpend = (float) $customer->orders()->where('payment_status', PaymentStatus::PAID)->sum('total');
        $ordersCount = $customer->orders()->count();

        return view('admin.customers.show', compact('customer', 'lifetimeSpend', 'ordersCount'));
    }

    /**
     * Toggle block/unblock status for a customer account.
     */
    public function toggleStatus(Customer $customer): RedirectResponse
    {
        $user = $customer->user;
        if (!$user) {
            return back()->with('error', 'Associated user account not found.');
        }

        $isSuspended = ($user->status === UserStatus::SUSPENDED || $user->status === 'suspended' || $user->status === 'blocked');
        $newStatus = $isSuspended ? UserStatus::ACTIVE : UserStatus::SUSPENDED;
        $user->status = $newStatus;
        $user->save();

        if (function_exists('activity')) {
            activity('customer')
                ->performedOn($customer)
                ->causedBy(auth()->user())
                ->withProperties(['status' => $newStatus->value])
                ->log("Customer account #{$customer->id} ({$user->name}) was {$newStatus->value}");
        }

        return back()->with('success', "Customer {$user->name} has been " . ($newStatus === UserStatus::ACTIVE ? 'unblocked' : 'blocked') . ".");
    }

    /**
     * Export customer list as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="customers-' . now()->format('Ymd-His') . '.csv"',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Customer ID', 'Name', 'Email', 'Phone', 'Status', 'Total Orders', 'Joined At']);

            Customer::with(['user', 'orders'])->chunk(100, function ($customers) use ($handle) {
                foreach ($customers as $c) {
                    $status = $c->user?->status;
                    $statusStr = $status instanceof UserStatus ? $status->value : (string) ($status ?? 'active');
                    fputcsv($handle, [
                        $c->id,
                        $c->user?->name ?? 'N/A',
                        $c->user?->email ?? 'N/A',
                        $c->user?->phone ?? 'N/A',
                        $statusStr,
                        $c->orders->count(),
                        $c->created_at->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}
