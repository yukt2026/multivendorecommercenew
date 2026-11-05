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
        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained('inventory')->cascadeOnDelete();
            $table->string('order_ref')->nullable()->index();
            $table->string('cart_ref')->nullable()->index();
            $table->unsignedInteger('quantity');
            $table->timestamp('expires_at')->index();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();

            $table->index(['inventory_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_reservations');
    }
};
