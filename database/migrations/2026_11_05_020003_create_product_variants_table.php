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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku')->unique()->index();
            $table->string('barcode', 50)->nullable()->index();
            $table->string('name');
            $table->json('attributes')->nullable(); // e.g. {"size": "500g", "color": "Blue"}
            $table->decimal('mrp', 12, 2);           // Rule 6: decimal(12,2)
            $table->decimal('selling_price', 12, 2); // Rule 6: decimal(12,2)
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->unsignedInteger('weight_grams')->default(0);
            $table->decimal('length', 8, 2)->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'is_active', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
