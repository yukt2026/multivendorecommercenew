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
        Schema::create('settlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_settlement_id')->constrained('seller_settlements')->cascadeOnDelete();
            $table->foreignId('fulfilment_order_id')->nullable()->constrained('fulfilment_orders')->nullOnDelete();
            $table->foreignId('commission_entry_id')->nullable()->constrained('commission_entries')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('type', 32)->default('order_payout'); // order_payout, adjustment, refund_deduction
            $table->timestamps();

            $table->index('seller_settlement_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlement_items');
    }
};
