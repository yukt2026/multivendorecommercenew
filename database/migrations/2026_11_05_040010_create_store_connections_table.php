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
        Schema::create('store_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('type', 32); // physical, woocommerce, shopify, custom_api, csv, manual
            $table->string('status', 32)->default('active')->index(); // active, paused, error, disconnected
            $table->text('config')->nullable(); // encrypted json config / tokens
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_connections');
    }
};
