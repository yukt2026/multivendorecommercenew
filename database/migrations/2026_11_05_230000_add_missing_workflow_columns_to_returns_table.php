<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            if (!Schema::hasColumn('returns', 'type')) {
                $table->string('type', 20)->default('return')->index()->after('return_number');
            }
            if (!Schema::hasColumn('returns', 'customer_notes')) {
                $table->text('customer_notes')->nullable()->after('reason');
            }
            if (!Schema::hasColumn('returns', 'photos')) {
                $table->json('photos')->nullable()->after('customer_notes');
            }
            if (!Schema::hasColumn('returns', 'pickup_waybill')) {
                $table->string('pickup_waybill', 100)->nullable()->after('pickup_shipment_id');
            }
            if (!Schema::hasColumn('returns', 'qc_notes')) {
                $table->text('qc_notes')->nullable()->after('qc_status');
            }
            if (!Schema::hasColumn('returns', 'is_resellable')) {
                $table->boolean('is_resellable')->default(true)->after('qc_notes');
            }
            if (!Schema::hasColumn('returns', 'refund_mode')) {
                $table->string('refund_mode', 20)->default('gateway')->after('refund_id');
            }
            if (!Schema::hasColumn('returns', 'refund_amount')) {
                $table->decimal('refund_amount', 10, 2)->nullable()->after('refund_mode');
            }
            if (!Schema::hasColumn('returns', 'seller_notes')) {
                $table->text('seller_notes')->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('returns', 'admin_notes')) {
                $table->text('admin_notes')->nullable()->after('seller_notes');
            }
            if (!Schema::hasColumn('returns', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('admin_notes');
            }
            if (!Schema::hasColumn('returns', 'picked_up_at')) {
                $table->timestamp('picked_up_at')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('returns', 'received_at')) {
                $table->timestamp('received_at')->nullable()->after('picked_up_at');
            }
            if (!Schema::hasColumn('returns', 'qc_completed_at')) {
                $table->timestamp('qc_completed_at')->nullable()->after('received_at');
            }
            if (!Schema::hasColumn('returns', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable()->after('qc_completed_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $columns = [
                'type',
                'customer_notes',
                'photos',
                'pickup_waybill',
                'qc_notes',
                'is_resellable',
                'refund_mode',
                'refund_amount',
                'seller_notes',
                'admin_notes',
                'approved_at',
                'picked_up_at',
                'received_at',
                'qc_completed_at',
                'refunded_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('returns', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
