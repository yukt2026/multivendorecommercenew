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
        Schema::create('ndr_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('reason');
            $table->unsignedInteger('attempts')->default(1);
            $table->string('status', 32)->default('pending')->index(); // pending, reattempt_scheduled, rto_requested, delivered, closed
            $table->string('customer_action')->nullable();
            $table->text('action_notes')->nullable();
            $table->timestamps();

            $table->index(['shipment_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ndr_cases');
    }
};
