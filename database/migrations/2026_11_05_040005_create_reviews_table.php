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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating')->default(5); // 1 to 5
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->json('media')->nullable(); // photos/videos uploaded by customer
            $table->boolean('is_verified_purchase')->default(false);
            $table->string('status', 32)->default('approved')->index(); // pending, approved, rejected
            $table->timestamps();

            $table->index(['product_id', 'status', 'rating']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
