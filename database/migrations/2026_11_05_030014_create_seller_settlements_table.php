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
        Schema::create('seller_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->string('settlement_number')->unique()->index();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('gross', 12, 2);
            $table->decimal('commission', 12, 2);
            $table->decimal('adjustments', 12, 2)->default(0.00);
            $table->decimal('net', 12, 2);
            $table->string('status', 32)->default('pending')->index(); // pending, processing, paid, on_hold
            $table->timestamp('paid_at')->nullable();
            $table->string('reference')->nullable()->index();
            $table->timestamps();

            $table->index(['seller_id', 'status']);
            $table->index(['created_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_settlements');
    }
};
