<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationTemplateController extends Controller
{
    /**
     * List all editable notification templates.
     */
    public function index(Request $request): View
    {
        $this->ensureDefaultTemplates();

        $query = NotificationTemplate::query();

        if ($request->has('channel') && $request->query('channel') !== 'all') {
            $query->where('channel', $request->query('channel'));
        }

        if ($request->has('event') && $request->query('event') !== 'all') {
            $query->where('event_key', $request->query('event'));
        }

        $templates = $query->orderBy('event_key')->orderBy('channel')->paginate(20);

        return view('admin.notifications.templates', [
            'templates' => $templates,
            'selectedChannel' => $request->query('channel', 'all'),
            'selectedEvent' => $request->query('event', 'all'),
        ]);
    }

    /**
     * Update notification template.
     */
    public function update(Request $request, NotificationTemplate $template): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string',
            'is_active' => 'nullable|boolean',
        ]);

        $template->update([
            'subject' => $validated['subject'] ?? null,
            'body' => $validated['body'],
            'is_active' => $request->has('is_active') ? (bool) $validated['is_active'] : true,
        ]);

        return back()->with('success', "Template for '{$template->event_key}' ({$template->channel}) updated successfully.");
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
            
            // 2. OTP
            ['event_key' => 'otp', 'channel' => 'sms', 'subject' => null, 'body' => 'Your OneStall verification OTP is {otp}. Valid for 10 minutes. Do not share.'],
            ['event_key' => 'otp', 'channel' => 'whatsapp', 'subject' => null, 'body' => 'Your OneStall security OTP is *{otp}*. Valid for 10 mins.'],
            ['event_key' => 'otp', 'channel' => 'mail', 'subject' => 'Your OneStall Verification Code', 'body' => 'Use code {otp} to complete your verification.'],

            // 3. Out for Delivery
            ['event_key' => 'out_for_delivery', 'channel' => 'sms', 'subject' => null, 'body' => 'OneStall: Your package #{order_number} is out for delivery with {rider_name}! Live track: {action_url}'],
            ['event_key' => 'out_for_delivery', 'channel' => 'whatsapp', 'subject' => null, 'body' => '🛵 Rider {rider_name} is on the way with your order #{order_number}! ETA: {eta}. View live map: {action_url}'],
            ['event_key' => 'out_for_delivery', 'channel' => 'database', 'subject' => 'Order Out for Delivery! 🛵', 'body' => 'Your order #{order_number} is out for delivery.'],

            // 4. Delivered
            ['event_key' => 'delivered', 'channel' => 'sms', 'subject' => null, 'body' => 'OneStall: Package #{order_number} has been delivered. Enjoy your purchase!'],
            ['event_key' => 'delivered', 'channel' => 'whatsapp', 'subject' => null, 'body' => '🎉 Package #{order_number} delivered successfully. Rate your experience: {action_url}'],
            ['event_key' => 'delivered', 'channel' => 'database', 'subject' => 'Delivered Successfully! 🎉', 'body' => 'Package #{order_number} has been delivered.'],

            // 5. Promotional
            ['event_key' => 'promotional', 'channel' => 'whatsapp', 'subject' => null, 'body' => '🔥 Exclusive Deal: Use code {coupon} to get {discount} OFF your next order! Shop now: {action_url}'],
            ['event_key' => 'promotional', 'channel' => 'mail', 'subject' => 'Special Deal: {discount} OFF Just for you!', 'body' => 'Enjoy an exclusive {discount} discount using promo code {coupon}. Valid for a limited time.'],
        ];

        foreach ($defaults as $def) {
            NotificationTemplate::firstOrCreate(
                ['event_key' => $def['event_key'], 'channel' => $def['channel']],
                ['subject' => $def['subject'], 'body' => $def['body'], 'is_active' => true]
            );
        }
    }
}
