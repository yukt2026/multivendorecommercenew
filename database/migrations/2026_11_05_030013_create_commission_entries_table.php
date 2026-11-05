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
        Schema::create('commission_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fulfilment_order_id')->constrained('fulfilment_orders')->cascadeOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('commission_rules')->nullOnDelete();
            $table->decimal('base_amount', 12, 2);
            $table->decimal('commission', 12, 2);
            $table->decimal('gateway_fee', 12, 2)->default(0.00);
            $table->decimal('shipping_adjustment', 12, 2)->default(0.00);
            $table->decimal('net_payable', 12, 2);
            $table->timestamps();

            $table->index('fulfilment_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_entries');
    }
};
