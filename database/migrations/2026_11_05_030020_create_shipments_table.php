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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fulfilment_order_id')->constrained('fulfilment_orders')->cascadeOnDelete();
            $table->string('shipment_number')->unique()->index();
            $table->string('provider', 50)->default('onestall_cargo'); // onestall_cargo, bluedart, delhivery, shadowfax
            $table->string('awb', 100)->nullable()->index();
            $table->string('courier', 50)->nullable();
            $table->string('service', 50)->nullable(); // surface, air, express, hyperlocal
            $table->string('status', 32)->default('pending')->index();
            $table->string('pickup_status', 32)->default('pending');
            $table->string('label_url')->nullable();
            $table->decimal('cod_amount', 12, 2)->default(0.00);
            $table->decimal('weight', 8, 2)->default(0.00); // weight in kg
            $table->decimal('charges', 12, 2)->default(0.00); // freight charges
            $table->timestamps();

            $table->index(['fulfilment_order_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
