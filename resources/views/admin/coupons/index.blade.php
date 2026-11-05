@extends('layouts.dashboard', ['portal' => 'admin', 'title' => 'Coupons & Discounts'])

@section('content')
<div class="space-y-6" x-data="couponManager()">

    <!-- Page Header -->
    <x-page-header 
        title="Platform &amp; Seller Coupons" 
        subtitle="Manage promotional discount codes, minimum basket thresholds, usage caps, and validity windows."
        :breadcrumbs="['Admin Central' => route('admin.dashboard'), 'Coupons' => '#']"
    >
        <x-slot name="badge">
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                {{ $metrics['total_coupons'] }} Codes
            </span>
        </x-slot>

        <x-slot name="actions">
            <div class="flex items-center space-x-2">
                <button 
                    @click="openModal()" 
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm flex items-center space-x-1.5 transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Create Coupon</span>
                </button>
                <a href="{{ route('admin.coupons.export') }}" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Export CSV</span>
                </a>
            </div>
        </x-slot>
    </x-page-header>

    <!-- Coupons Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800 font-semibold">
                    <tr>
                        <th class="px-6 py-3.5">Coupon Code</th>
                        <th class="px-6 py-3.5">Discount Value</th>
                        <th class="px-6 py-3.5">Min Basket / Max Disc</th>
                        <th class="px-6 py-3.5">Scope</th>
                        <th class="px-6 py-3.5">Limits</th>
                        <th class="px-6 py-3.5">Validity</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($coupons as $c)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                        <td class="px-6 py-4 font-mono font-bold text-slate-900 dark:text-white">
                            <span class="px-2.5 py-1 rounded-md bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 tracking-wider">
                                {{ $c->code }}
                            </span>
                        </td>
                        <td class="px-6 py-4 font-bold text-slate-900 dark:text-white">
                            {{ $c->type === 'percent' ? $c->value . '%' : format_currency((float)$c->value) }}
                            <span class="text-[10px] text-slate-400 block font-normal uppercase">{{ $c->type }}</span>
                        </td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                            <div>Min: {{ format_currency((float)$c->min_order) }}</div>
                            <div class="text-[10px] text-slate-400">Max Cap: {{ $c->max_discount ? format_currency((float)$c->max_discount) : 'Unlimited' }}</div>
                        </td>
                        <td class="px-6 py-4">
                            @if($c->seller)
                                <div class="font-medium text-slate-800 dark:text-slate-200">{{ $c->seller->business_name }}</div>
                                <div class="text-[10px] text-slate-400">Seller Specific</div>
                            @else
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400">Platform Wide</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-300 font-mono text-[11px]">
                            <div>Limit: {{ $c->usage_limit ?? '∞' }}</div>
                            <div class="text-[10px] text-slate-400">Per User: {{ $c->per_user_limit }}</div>
                        </td>
                        <td class="px-6 py-4 text-slate-500 font-mono text-[11px]">
                            @if($c->starts_at || $c->ends_at)
                                <div>{{ $c->starts_at ? $c->starts_at->format('d M') : 'Start' }} &rarr; {{ $c->ends_at ? $c->ends_at->format('d M Y') : 'Indefinite' }}</div>
                            @else
                                <span class="text-slate-400">Permanent</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ $c->is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' }}">
                                {{ $c->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <button @click="openModal({{ json_encode($c) }})" class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition" title="Edit Coupon">
                                <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <form method="POST" action="{{ route('admin.coupons.destroy', $c) }}" class="inline" onsubmit="return confirm('Delete this coupon code?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 transition" title="Delete Coupon">
                                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-slate-400">No coupon promotions found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800">
            {{ $coupons->links() }}
        </div>
    </div>

    <!-- Create/Edit Modal -->
    <div x-show="isModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div @click.away="closeModal()" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl w-full max-w-lg p-6">
            <h3 class="text-base font-bold text-slate-900 dark:text-white mb-3" x-text="editingCoupon ? 'Edit Coupon Promotion' : 'Create New Discount Coupon'"></h3>

            <form :action="editingCoupon ? ('/admin/coupons/' + editingCoupon.id) : '{{ route('admin.coupons.store') }}'" method="POST" class="space-y-4">
                @csrf
                <template x-if="editingCoupon">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Coupon Code</label>
                        <input type="text" name="code" required x-model="form.code" placeholder="e.g. FESTIVE20" class="w-full text-xs font-mono uppercase rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Discount Type</label>
                        <select name="type" x-model="form.type" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                            <option value="percent">Percentage (%)</option>
                            <option value="flat">Flat Amount (₹)</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Value</label>
                        <input type="number" step="0.01" name="value" required x-model="form.value" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Min Order (₹)</label>
                        <input type="number" step="1" name="min_order" x-model="form.min_order" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Max Disc (₹)</label>
                        <input type="number" step="1" name="max_discount" x-model="form.max_discount" placeholder="Optional" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Total Usage Limit</label>
                        <input type="number" name="usage_limit" x-model="form.usage_limit" placeholder="Unlimited" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Per User Limit</label>
                        <input type="number" name="per_user_limit" required x-model="form.per_user_limit" value="1" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Seller Scope (Optional)</label>
                    <select name="seller_id" x-model="form.seller_id" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                        <option value="">Platform Wide (All Sellers)</option>
                        @foreach($sellers as $s)
                            <option value="{{ $s->id }}">{{ $s->business_name }} (#{{ $s->id }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Valid From</label>
                        <input type="datetime-local" name="starts_at" x-model="form.starts_at" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Valid Until</label>
                        <input type="datetime-local" name="ends_at" x-model="form.ends_at" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-indigo-500">
                    </div>
                </div>

                <div class="flex items-center space-x-2 pt-1">
                    <input type="checkbox" id="coupon_active_box" name="is_active" value="1" x-model="form.is_active" class="rounded border-slate-300 dark:border-slate-700 text-indigo-600 focus:ring-indigo-500">
                    <label for="coupon_active_box" class="text-xs font-medium text-slate-700 dark:text-slate-300">Coupon is active immediately</label>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" @click="closeModal()" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow-sm">Save Coupon</button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function couponManager() {
    return {
        isModalOpen: false,
        editingCoupon: null,
        form: {
            code: '',
            type: 'percent',
            value: 10,
            min_order: 0,
            max_discount: '',
            usage_limit: '',
            per_user_limit: 1,
            seller_id: '',
            starts_at: '',
            ends_at: '',
            is_active: true
        },
        openModal(coupon = null) {
            this.editingCoupon = coupon;
            if (coupon) {
                this.form = {
                    code: coupon.code,
                    type: coupon.type,
                    value: coupon.value,
                    min_order: coupon.min_order,
                    max_discount: coupon.max_discount || '',
                    usage_limit: coupon.usage_limit || '',
                    per_user_limit: coupon.per_user_limit,
                    seller_id: coupon.seller_id || '',
                    starts_at: coupon.starts_at ? coupon.starts_at.slice(0, 16) : '',
                    ends_at: coupon.ends_at ? coupon.ends_at.slice(0, 16) : '',
                    is_active: Boolean(coupon.is_active)
                };
            } else {
                this.form = {
                    code: '',
                    type: 'percent',
                    value: 10,
                    min_order: 0,
                    max_discount: '',
                    usage_limit: '',
                    per_user_limit: 1,
                    seller_id: '',
                    starts_at: '',
                    ends_at: '',
                    is_active: true
                };
            }
            this.isModalOpen = true;
        },
        closeModal() {
            this.isModalOpen = false;
            this.editingCoupon = null;
        }
    };
}
</script>
@endsection
