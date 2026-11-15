<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationTemplateController extends Controller
{
    /**
     * Ensure current user has permission to manage platform notifications.
     */
    protected function authorizeAccess(): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        if (!$user->hasAnyRole(['super_admin', 'admin']) && !$user->can('settings.view') && !$user->can('settings.manage')) {
            abort(403, 'You do not have administrative authorization to view notification templates.');
        }
    }

    /**
     * List all editable notification templates with filtering and search.
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess();
        $this->ensureDefaultTemplates();

        $query = NotificationTemplate::query();

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('event_key', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhere('body', 'like', "%{$search}%");
            });
        }

        if ($request->filled('channel') && $request->query('channel') !== 'all') {
            $query->where('channel', $request->query('channel'));
        }

        if ($request->filled('event') && $request->query('event') !== 'all') {
            $query->where('event_key', $request->query('event'));
        }

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $isActive = $request->query('status') === 'active';
            $query->where('is_active', $isActive);
        }

        $templates = $query->orderBy('event_key')->orderBy('channel')->paginate(25)->withQueryString();

        $metrics = [
            'total_templates' => NotificationTemplate::count(),
            'active_templates' => NotificationTemplate::where('is_active', true)->count(),
            'mail_templates' => NotificationTemplate::where('channel', 'mail')->count(),
            'instant_templates' => NotificationTemplate::whereIn('channel', ['sms', 'whatsapp', 'push'])->count(),
        ];

        return view('admin.notifications.templates', [
            'templates' => $templates,
            'metrics' => $metrics,
            'selectedChannel' => $request->query('channel', 'all'),
            'selectedEvent' => $request->query('event', 'all'),
            'selectedStatus' => $request->query('status', 'all'),
            'search' => $search,
        ]);
    }

    /**
     * Update notification template message content.
     */
    public function update(Request $request, NotificationTemplate $template): RedirectResponse
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'is_active' => ['nullable'],
        ]);

        $isActive = $request->boolean('is_active');

        $template->update([
            'subject' => $validated['subject'] ?? null,
            'body' => $validated['body'],
            'is_active' => $isActive,
        ]);

        if (function_exists('activity')) {
            activity('settings')
                ->performedOn($template)
                ->causedBy(auth()->user())
                ->withProperties([
                    'event_key' => $template->event_key,
                    'channel' => $template->channel,
                    'is_active' => $isActive,
                ])
                ->log("Notification template for '{$template->event_key}' ({$template->channel}) updated");
        }

        return back()->with('success', "Template for '{$template->event_key}' ({$template->channel}) updated successfully.");
    }

    /**
     * Toggle active state directly from table row.
     */
    public function toggle(NotificationTemplate $template): RedirectResponse
    {
        $this->authorizeAccess();

        $template->is_active = !$template->is_active;
        $template->save();

        $statusStr = $template->is_active ? 'activated' : 'disabled';

        if (function_exists('activity')) {
            activity('settings')
                ->performedOn($template)
                ->causedBy(auth()->user())
                ->log("Notification template '{$template->event_key}' ({$template->channel}) {$statusStr}");
        }

        return back()->with('success', "Template '{$template->event_key}' ({$template->channel}) is now {$statusStr}.");
    }

    /**
     * Return live preview render with sample variables.
     */
    public function preview(Request $request, NotificationTemplate $template): JsonResponse
    {
        $this->authorizeAccess();

        $subject = $request->input('subject', $template->subject);
        $body = $request->input('body', $template->body);

        $temp = new NotificationTemplate([
            'event_key' => $template->event_key,
            'channel' => $template->channel,
            'subject' => $subject,
            'body' => $body,
        ]);

        $sampleVars = NotificationTemplate::sampleVariables();
        $rendered = $temp->render($sampleVars);

        return response()->json([
            'rendered_subject' => $rendered['subject'],
            'rendered_body' => $rendered['body'],
            'sample_variables' => $sampleVars,
        ]);
    }

    /**
     * Ensure core default templates exist in DB.
     */
    protected function ensureDefaultTemplates(): void
    {
        $defaults = [
            // 1. Order Confirmed
            ['event_key' => 'order_confirmed', 'channel' => 'mail', 'subject' => 'Order Confirmed #{order_number}', 'body' => 'Your order #{order_number} amounting to ₹{amount} has been successfully placed with {store_name}. Track your order here: {action_url}'],
            ['event_key' => 'order_confirmed', 'channel' => 'sms', 'subject' => null, 'body' => 'OneStall: Order #{order_number} confirmed! Amount: ₹{amount}. Track: {action_url}'],
            ['event_key' => 'order_confirmed', 'channel' => 'whatsapp', 'subject' => null, 'body' => 'Hello {name}! 🎉 Your order #{order_number} is confirmed. Total: ₹{amount}. View order details: {action_url}'],
            ['event_key' => 'order_confirmed', 'channel' => 'database', 'subject' => 'Order #{order_number} Confirmed', 'body' => 'Your order #{order_number} has been placed successfully.'],
            
            // 2. OTP Verification
            ['event_key' => 'otp', 'channel' => 'sms', 'subject' => null, 'body' => 'Your OneStall verification OTP is {otp}. Valid for 10 minutes. Do not share.'],
            ['event_key' => 'otp', 'channel' => 'whatsapp', 'subject' => null, 'body' => 'Your OneStall security OTP is *{otp}*. Valid for 10 mins.'],
            ['event_key' => 'otp', 'channel' => 'mail', 'subject' => 'Your OneStall Verification Code', 'body' => 'Use code {otp} to complete your verification.'],

            // 3. Payment Confirmed
            ['event_key' => 'payment_confirmed', 'channel' => 'mail', 'subject' => 'Payment Receipt for Order #{order_number}', 'body' => 'We received your payment of ₹{amount} via {payment_method} (Ref: {transaction_id}) for Order #{order_number}.'],
            ['event_key' => 'payment_confirmed', 'channel' => 'sms', 'subject' => null, 'body' => 'OneStall: ₹{amount} received for order #{order_number} via {payment_method}. Txn: {transaction_id}'],
            ['event_key' => 'payment_confirmed', 'channel' => 'whatsapp', 'subject' => null, 'body' => '✅ Payment of ₹{amount} confirmed for Order #{order_number}. Receipt available at: {action_url}'],
            ['event_key' => 'payment_confirmed', 'channel' => 'database', 'subject' => 'Payment Received: ₹{amount}', 'body' => 'Payment of ₹{amount} received for order #{order_number}.'],

            // 4. Order Packing
            ['event_key' => 'order_packing', 'channel' => 'sms', 'subject' => null, 'body' => 'OneStall: {store_name} is currently packing your {item_count} items for order #{order_number}.'],
            ['event_key' => 'order_packing', 'channel' => 'whatsapp', 'subject' => null, 'body' => '📦 Great news {name}! {store_name} is packing your {item_count} items for Order #{order_number}.'],
            ['event_key' => 'order_packing', 'channel' => 'database', 'subject' => 'Order #{order_number} is being packed', 'body' => '{store_name} has started packing your order.'],

            // 5. Shipment Update
            ['event_key' => 'shipment_update', 'channel' => 'sms', 'subject' => null, 'body' => 'OneStall: Order #{order_number} dispatched via {courier_name}. AWB: {tracking_number}. Track: {action_url}'],
            ['event_key' => 'shipment_update', 'channel' => 'whatsapp', 'subject' => null, 'body' => '🚚 Order #{order_number} is on its way via {courier_name} (AWB: {tracking_number}). Track live: {action_url}'],
            ['event_key' => 'shipment_update', 'channel' => 'database', 'subject' => 'Dispatched: #{order_number}', 'body' => 'Your shipment #{order_number} has been dispatched.'],

            // 6. Out for Delivery
            ['event_key' => 'out_for_delivery', 'channel' => 'sms', 'subject' => null, 'body' => 'OneStall: Your package #{order_number} is out for delivery with {rider_name}! Live track: {action_url}'],
            ['event_key' => 'out_for_delivery', 'channel' => 'whatsapp', 'subject' => null, 'body' => '🛵 Rider {rider_name} is on the way with your order #{order_number}! ETA: {eta}. View live map: {action_url}'],
            ['event_key' => 'out_for_delivery', 'channel' => 'database', 'subject' => 'Order Out for Delivery! 🛵', 'body' => 'Your order #{order_number} is out for delivery.'],

            // 7. Order Delivered
            ['event_key' => 'order_delivered', 'channel' => 'sms', 'subject' => null, 'body' => 'OneStall: Package #{order_number} has been delivered. Enjoy your purchase!'],
            ['event_key' => 'order_delivered', 'channel' => 'whatsapp', 'subject' => null, 'body' => '🎉 Package #{order_number} delivered successfully. Rate your experience: {action_url}'],
            ['event_key' => 'order_delivered', 'channel' => 'database', 'subject' => 'Delivered Successfully! 🎉', 'body' => 'Package #{order_number} has been delivered.'],

            // 8. Return & Refund
            ['event_key' => 'return_refund', 'channel' => 'mail', 'subject' => 'Refund Processed for Order #{order_number}', 'body' => 'Your refund of ₹{refund_amount} for return request #{return_id} has been initiated to your original payment method.'],
            ['event_key' => 'return_refund', 'channel' => 'sms', 'subject' => null, 'body' => 'OneStall: Refund of ₹{refund_amount} for #{order_number} has been initiated.'],
            ['event_key' => 'return_refund', 'channel' => 'database', 'subject' => 'Refund Initiated: ₹{refund_amount}', 'body' => 'Refund for return #{return_id} is in progress.'],

            // 9. Seller New Order
            ['event_key' => 'seller_new_order', 'channel' => 'mail', 'subject' => 'New Order Received #{order_number}', 'body' => 'You have received a new order #{order_number} with {item_count} items worth ₹{amount}. Please accept and pack promptly: {action_url}'],
            ['event_key' => 'seller_new_order', 'channel' => 'database', 'subject' => 'New Order #{order_number}', 'body' => 'New order #{order_number} received for ₹{amount}.'],

            // 10. Low Stock Alert
            ['event_key' => 'low_stock_alert', 'channel' => 'mail', 'subject' => 'Low Stock Warning: {product_name}', 'body' => 'Inventory for SKU {sku} ({product_name}) has fallen to {remaining_quantity} units at {store_name}. Restock advised.'],
            ['event_key' => 'low_stock_alert', 'channel' => 'database', 'subject' => 'Low Stock: {product_name}', 'body' => 'SKU {sku} has only {remaining_quantity} units remaining.'],

            // 11. Settlement Paid
            ['event_key' => 'settlement_paid', 'channel' => 'mail', 'subject' => 'Merchant Payout Settled #{settlement_id}', 'body' => 'Your merchant settlement #{settlement_id} for period {period} amounting to ₹{amount} has been paid via bank transfer (UTR: {utr}).'],
            ['event_key' => 'settlement_paid', 'channel' => 'sms', 'subject' => null, 'body' => 'OneStall: Settlement ₹{amount} credited for period {period}. UTR: {utr}'],
            ['event_key' => 'settlement_paid', 'channel' => 'database', 'subject' => 'Payout Processed: ₹{amount}', 'body' => 'Settlement #{settlement_id} has been credited.'],

            // 12. Promotional Announcement
            ['event_key' => 'promotional', 'channel' => 'whatsapp', 'subject' => null, 'body' => '🔥 Exclusive Deal: Use code {coupon} to get {discount} OFF your next order! Shop now: {action_url}'],
            ['event_key' => 'promotional', 'channel' => 'mail', 'subject' => 'Special Deal: {discount} OFF Just for you!', 'body' => 'Enjoy an exclusive {discount} discount using promo code {coupon}. Valid for a limited time.'],
            ['event_key' => 'promotional', 'channel' => 'push', 'subject' => 'Flash Sale: {discount} OFF!', 'body' => 'Use code {coupon} now to unlock exclusive savings across stores!'],
        ];

        foreach ($defaults as $def) {
            NotificationTemplate::firstOrCreate(
                ['event_key' => $def['event_key'], 'channel' => $def['channel']],
                ['subject' => $def['subject'], 'body' => $def['body'], 'is_active' => true]
            );
        }
    }
}
