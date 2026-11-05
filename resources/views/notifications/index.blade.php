@extends('layouts.dashboard', ['portal' => (auth()->user()?->role === 'seller' ? 'seller' : (auth()->user()?->role === 'admin' ? 'admin' : 'customer')), 'title' => 'Notifications & Communication Preferences'])

@section('content')
<div class="space-y-6" x-data="{ activeTab: 'inbox' }">

    <!-- Page Header -->
    <x-page-header 
        title="Notification Center" 
        subtitle="Review account alerts, shipment updates, and customize your delivery channels."
        :breadcrumbs="['Dashboard' => '#', 'Notifications' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold {{ $unreadCount > 0 ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' }} border border-amber-200 dark:border-amber-800">
                {{ $unreadCount }} Unread
            </span>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center space-x-2">
                @if($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.mark_all_read') }}" class="inline">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/50 dark:hover:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 transition">
                        Mark All Read
                    </button>
                </form>
                @endif
                <form method="POST" action="{{ route('notifications.clear_all') }}" onsubmit="return confirm('Are you sure you want to delete all notifications?');" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/50 dark:hover:bg-rose-900/50 text-rose-700 dark:text-rose-300 transition">
                        Clear All
                    </button>
                </form>
            </div>
        </x-slot>
    </x-page-header>

    <!-- Navigation Tabs -->
    <div class="border-b border-slate-200 dark:border-slate-800">
        <nav class="flex space-x-8" aria-label="Tabs">
            <button 
                @click="activeTab = 'inbox'"
                :class="activeTab === 'inbox' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 font-medium'"
                class="py-3 px-1 border-b-2 text-sm flex items-center space-x-2 transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                <span>Inbox Alerts</span>
                @if($unreadCount > 0)
                <span class="ml-2 bg-amber-500 text-white rounded-full text-[10px] font-bold px-2 py-0.5">{{ $unreadCount }}</span>
                @endif
            </button>
            <button 
                @click="activeTab = 'preferences'"
                :class="activeTab === 'preferences' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 font-medium'"
                class="py-3 px-1 border-b-2 text-sm flex items-center space-x-2 transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                <span>Channel Preferences &amp; Marketing Consent</span>
            </button>
        </nav>
    </div>

    <!-- Tab 1: In-App Notifications Feed -->
    <div x-show="activeTab === 'inbox'" class="space-y-4">
        @if($notifications->isEmpty())
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-12 text-center shadow-sm">
                <div class="w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mx-auto text-slate-400">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                </div>
                <h3 class="mt-4 text-base font-bold text-slate-900 dark:text-white">You're all caught up!</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">No active alerts or messages in your inbox.</p>
            </div>
        @else
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden divide-y divide-slate-100 dark:divide-slate-800">
                @foreach($notifications as $n)
                    @php
                        $data = $n->data;
                        $isUnread = is_null($n->read_at);
                    @endphp
                    <div class="p-4 sm:p-5 flex items-start justify-between space-x-4 {{ $isUnread ? 'bg-indigo-50/40 dark:bg-indigo-950/20' : 'hover:bg-slate-50/50 dark:hover:bg-slate-800/40' }} transition">
                        <div class="flex items-start space-x-3.5">
                            <div class="p-2 rounded-xl {{ $isUnread ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-500' }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div>
                                <div class="flex items-center space-x-2">
                                    <h4 class="text-sm font-bold text-slate-900 dark:text-white">
                                        {{ $data['title'] ?? 'Notification' }}
                                    </h4>
                                    @if($isUnread)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400">New</span>
                                    @endif
                                </div>
                                <p class="text-xs text-slate-600 dark:text-slate-300 mt-1 leading-relaxed">
                                    {{ $data['message'] ?? '' }}
                                </p>
                                <div class="mt-2.5 flex items-center space-x-4 text-[11px] text-slate-400">
                                    <span>{{ $n->created_at->diffForHumans() }}</span>
                                    @if(!empty($data['action_url']))
                                        <a href="{{ $data['action_url'] }}" class="font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center space-x-1">
                                            <span>{{ $data['action_text'] ?? 'View Details' }}</span>
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 flex-shrink-0">
                            @if($isUnread)
                                <form method="POST" action="{{ route('notifications.read', $n->id) }}">
                                    @csrf
                                    <button type="submit" class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg hover:bg-white dark:hover:bg-slate-800 transition" title="Mark as Read">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-4">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>

    <!-- Tab 2: Channel Preferences & Promotional Consent -->
    <div x-show="activeTab === 'preferences'" class="space-y-6" style="display: none;">
        <form method="POST" action="{{ route('notifications.preferences.update') }}" class="space-y-6">
            @csrf

            <!-- Channels Matrix Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm p-6">
                <div class="border-b border-slate-100 dark:border-slate-800 pb-4 mb-6">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Delivery Channel Subscriptions</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Configure where you receive transactional alerts, status updates, and critical security notices.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 font-semibold uppercase tracking-wider">
                                <th class="py-3 px-4">Event Category</th>
                                <th class="py-3 px-4 text-center">In-App Bell</th>
                                <th class="py-3 px-4 text-center">Email</th>
                                <th class="py-3 px-4 text-center">SMS</th>
                                <th class="py-3 px-4 text-center">WhatsApp</th>
                                <th class="py-3 px-4 text-center">Push</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach(['orders' => 'Order Lifecycle & Shipping', 'account' => 'Account & Settlements', 'security' => 'Security & OTP Verification', 'promotions' => 'Discounts & Special Offers'] as $catKey => $catLabel)
                                @php
                                    $p = $preferences[$catKey] ?? null;
                                @endphp
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                                    <td class="py-4 px-4 font-semibold text-slate-900 dark:text-white">
                                        {{ $catLabel }}
                                        <div class="text-[11px] font-normal text-slate-400 mt-0.5">
                                            @if($catKey === 'orders') Delivery tracking, order accepted, packing, out for delivery.
                                            @elseif($catKey === 'security') Login verifications, password reset codes, OTPs.
                                            @elseif($catKey === 'account') Payout settlements, low stock alerts, support replies.
                                            @else Exclusive member coupons, seasonal flash sales.
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-4 px-4 text-center">
                                        <input type="checkbox" name="preferences[{{ $catKey }}][database]" value="1" {{ ($p?->channel_database ?? true) ? 'checked' : '' }} class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-700">
                                    </td>
                                    <td class="py-4 px-4 text-center">
                                        <input type="checkbox" name="preferences[{{ $catKey }}][mail]" value="1" {{ ($p?->channel_mail ?? true) ? 'checked' : '' }} class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-700">
                                    </td>
                                    <td class="py-4 px-4 text-center">
                                        <input type="checkbox" name="preferences[{{ $catKey }}][sms]" value="1" {{ ($p?->channel_sms ?? false) ? 'checked' : '' }} class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-700">
                                    </td>
                                    <td class="py-4 px-4 text-center">
                                        <input type="checkbox" name="preferences[{{ $catKey }}][whatsapp]" value="1" {{ ($p?->channel_whatsapp ?? false) ? 'checked' : '' }} class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-700">
                                    </td>
                                    <td class="py-4 px-4 text-center">
                                        <input type="checkbox" name="preferences[{{ $catKey }}][push]" value="1" {{ ($p?->channel_push ?? true) ? 'checked' : '' }} class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-700">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Promotional & Marketing Consent Guard -->
            <div class="bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800/60 rounded-2xl p-6">
                <div class="flex items-start space-x-3.5">
                    <div class="p-2 rounded-xl bg-amber-500 text-white mt-0.5">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-sm font-bold text-amber-900 dark:text-amber-200">Explicit Promotional Consent (Strict Opt-In)</h4>
                        <p class="text-xs text-amber-800/80 dark:text-amber-300/80 mt-1 leading-relaxed">
                            Under compliance rules and platform privacy governance, marketing announcements and promotional vouchers are strictly prohibited unless you explicitly provide consent. You may revoke consent at any time.
                        </p>

                        <div class="mt-4 flex items-center space-x-3">
                            <input 
                                type="checkbox" 
                                id="promotional_consent" 
                                name="promotional_consent" 
                                value="1" 
                                {{ ($preferences['promotions']?->promotional_consent ?? false) ? 'checked' : '' }}
                                class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-amber-300 dark:border-amber-700"
                            >
                            <label for="promotional_consent" class="text-xs font-semibold text-slate-800 dark:text-slate-200 cursor-pointer">
                                I agree to receive curated deals, flash sale alerts, and promotional coupons from OneStall.
                            </label>
                        </div>

                        @if(!empty($preferences['promotions']?->promotional_consent_at))
                            <div class="mt-2 text-[11px] text-amber-700 dark:text-amber-400">
                                ✓ Consent recorded on {{ $preferences['promotions']->promotional_consent_at->format('M d, Y H:i') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs shadow-sm transition">
                    Save Communication Preferences
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
