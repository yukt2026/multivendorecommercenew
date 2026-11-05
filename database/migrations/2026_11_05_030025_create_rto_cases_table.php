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
        Schema::create('rto_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('reason');
            $table->string('status', 32)->default('initiated')->index(); // initiated, in_transit, delivered_to_origin, closed
            $table->timestamp('initiated_at');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['shipment_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rto_cases');
    }
};
