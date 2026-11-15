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
        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->string('pickup_otp', 6)->nullable()->after('otp_verified');
            $table->boolean('pickup_otp_verified')->default(false)->after('pickup_otp');
            $table->string('rejection_reason', 255)->nullable()->after('notes');
            $table->timestamp('rejected_at')->nullable()->after('rejection_reason');
            $table->timestamp('failed_at')->nullable()->after('delivered_at');
            $table->string('failure_reason', 64)->nullable()->after('failed_at'); // customer_unreachable, address_not_found, customer_refused, store_cancelled, accident_breakdown
            $table->text('failure_notes')->nullable()->after('failure_reason');
            $table->string('proof_of_delivery_image', 255)->nullable()->after('failure_notes');
            $table->decimal('cod_amount_collected', 12, 2)->default(0.00)->after('proof_of_delivery_image');
            $table->timestamp('cod_collected_at')->nullable()->after('cod_amount_collected');
            $table->decimal('rider_earnings_amount', 12, 2)->default(0.00)->after('cod_collected_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->dropColumn([
                'pickup_otp',
                'pickup_otp_verified',
                'rejection_reason',
                'rejected_at',
                'failed_at',
                'failure_reason',
                'failure_notes',
                'proof_of_delivery_image',
                'cod_amount_collected',
                'cod_collected_at',
                'rider_earnings_amount',
            ]);
        });
    }
};
