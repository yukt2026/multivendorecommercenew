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
        Schema::table('delivery_partners', function (Blueprint $table) {
            $table->string('approval_status', 32)->default('approved')->after('status')->index(); // pending, under_review, approved, rejected, suspended
            $table->timestamp('approved_at')->nullable()->after('approval_status');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('approved_by');
            $table->decimal('cash_in_hand_limit', 12, 2)->default(5000.00)->after('active_orders_count');
            $table->decimal('current_cash_in_hand', 12, 2)->default(0.00)->after('cash_in_hand_limit');
            $table->string('emergency_contact_name', 100)->nullable()->after('vehicle_number');
            $table->string('emergency_contact_phone', 20)->nullable()->after('emergency_contact_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_partners', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'approval_status',
                'approved_at',
                'approved_by',
                'rejection_reason',
                'cash_in_hand_limit',
                'current_cash_in_hand',
                'emergency_contact_name',
                'emergency_contact_phone',
            ]);
        });
    }
};
