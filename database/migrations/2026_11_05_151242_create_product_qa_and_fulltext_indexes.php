<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create product questions table
        Schema::create('product_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('question');
            $table->boolean('is_approved')->default(true)->index();
            $table->unsignedInteger('upvotes')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'is_approved']);
        });

        // 2. Create product answers table
        Schema::create('product_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('product_questions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('answer');
            $table->boolean('is_seller_answer')->default(false);
            $table->boolean('is_approved')->default(true)->index();
            $table->unsignedInteger('upvotes')->default(0);
            $table->timestamps();

            $table->index(['question_id', 'is_approved']);
        });

        // 3. MySQL Fulltext indexes on products, stores, brands (skip on SQLite in-memory test driver)
        if (DB::connection()->getDriverName() === 'mysql') {
            try {
                DB::statement('ALTER TABLE products ADD FULLTEXT product_fulltext_idx (title, short_desc)');
                DB::statement('ALTER TABLE stores ADD FULLTEXT store_fulltext_idx (name, description)');
                DB::statement('ALTER TABLE brands ADD FULLTEXT brand_fulltext_idx (name)');
            } catch (\Throwable $e) {
                // If already exists or MySQL version doesn't support, continue safely
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            try {
                DB::statement('ALTER TABLE products DROP INDEX product_fulltext_idx');
                DB::statement('ALTER TABLE stores DROP INDEX store_fulltext_idx');
                DB::statement('ALTER TABLE brands DROP INDEX brand_fulltext_idx');
            } catch (\Throwable $e) {
                // Ignore if dropping fails
            }
        }

        Schema::dropIfExists('product_answers');
        Schema::dropIfExists('product_questions');
    }
};
