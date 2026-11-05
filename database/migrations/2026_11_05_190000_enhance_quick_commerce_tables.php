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
        // 1. Stores: Quick Commerce capacity & configuration
        Schema::table('stores', function (Blueprint $table) {
            $table->unsignedInteger('quick_max_orders')->default(15)->after('accepts_quick_commerce');
            $table->unsignedInteger('quick_prep_time_minutes')->default(10)->after('quick_max_orders');
            $table->unsignedInteger('quick_order_timeout_seconds')->default(120)->after('quick_prep_time_minutes');
            $table->boolean('quick_auto_assign_rider')->default(true)->after('quick_order_timeout_seconds');
        });

        // 2. Fulfilment Orders: Delivery OTP, Timestamps & Reassignment tracking
        Schema::table('fulfilment_orders', function (Blueprint $table) {
            $table->string('delivery_otp', 6)->nullable()->after('fulfilment_type');
            $table->timestamp('accepted_at')->nullable()->after('delivery_otp');
            $table->timestamp('packed_at')->nullable()->after('accepted_at');
            $table->timestamp('ready_at')->nullable()->after('packed_at');
            $table->foreignId('reassigned_from_store_id')->nullable()->after('store_id')->constrained('stores')->nullOnDelete();
        });

        // 3. Delivery Partners: Realtime status, capacity & active tracking
        Schema::table('delivery_partners', function (Blueprint $table) {
            $table->string('status', 32)->default('available')->after('is_active')->index(); // available, busy, offline
            $table->unsignedInteger('active_orders_count')->default(0)->after('status');
            $table->timestamp('last_location_updated_at')->nullable()->after('current_lng');
        });

        // 4. Delivery Assignments: OTP verification status & notes
        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->boolean('otp_verified')->default(false)->after('status');
            $table->text('notes')->nullable()->after('otp_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->dropColumn(['otp_verified', 'notes']);
        });

        Schema::table('delivery_partners', function (Blueprint $table) {
            $table->dropColumn(['status', 'active_orders_count', 'last_location_updated_at']);
        });

        Schema::table('fulfilment_orders', function (Blueprint $table) {
            $table->dropForeign(['reassigned_from_store_id']);
            $table->dropColumn(['delivery_otp', 'accepted_at', 'packed_at', 'ready_at', 'reassigned_from_store_id']);
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['quick_max_orders', 'quick_prep_time_minutes', 'quick_order_timeout_seconds', 'quick_auto_assign_rider']);
        });
    }
};
