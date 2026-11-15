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
        Schema::create('rider_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_partner_id')->constrained('delivery_partners')->cascadeOnDelete();
            $table->foreignId('delivery_assignment_id')->constrained('delivery_assignments')->cascadeOnDelete();
            $table->foreignId('fulfilment_order_id')->constrained('fulfilment_orders')->cascadeOnDelete();
            $table->decimal('base_payout', 12, 2)->default(0.00);
            $table->decimal('distance_km', 8, 2)->default(0.00);
            $table->decimal('distance_payout', 12, 2)->default(0.00);
            $table->decimal('surge_bonus', 12, 2)->default(0.00);
            $table->decimal('customer_tip', 12, 2)->default(0.00);
            $table->decimal('total_payout', 12, 2)->default(0.00);
            $table->string('status', 32)->default('credited')->index(); // credited, held, settled
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['delivery_partner_id', 'status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rider_earnings');
    }
};
