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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique()->index();
            $table->text('short_desc')->nullable();
            $table->longText('description')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->string('product_type', 32)->default('simple');
            $table->string('hsn_code', 20)->nullable();
            $table->decimal('tax_percent', 5, 2)->default(0.00); // e.g. 18.00%
            $table->unsignedSmallInteger('return_window_days')->default(7);
            $table->boolean('is_returnable')->default(true);
            $table->boolean('is_cod_allowed')->default(true);
            $table->boolean('is_quick_capable')->default(false)->index(); // Quick Commerce 10-30m candidate
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['seller_id', 'status']);
            $table->index(['category_id', 'status']);
            $table->index(['brand_id', 'status']);
            $table->index(['is_quick_capable', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
