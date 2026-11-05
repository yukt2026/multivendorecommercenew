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
        Schema::create('store_delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('type', 32)->default('radius'); // radius, pincode_list
            $table->decimal('radius_km', 8, 2)->nullable();
            $table->json('pincodes')->nullable();
            $table->decimal('min_order_value', 12, 2)->default(0.00); // Rule 6: decimal(12,2)
            $table->decimal('delivery_fee', 12, 2)->default(0.00);   // Rule 6: decimal(12,2)
            $table->boolean('is_quick')->default(false)->index();
            $table->timestamps();

            $table->index(['store_id', 'is_quick']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_delivery_zones');
    }
};
