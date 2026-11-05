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
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('business_name')->index();
            $table->string('legal_name');
            $table->string('seller_type', 32)->default('marketplace')->index();
            $table->string('pan', 20)->nullable()->index();
            $table->string('gstin', 20)->nullable()->index();
            $table->string('status', 32)->default('pending')->index();
            $table->decimal('commission_override', 5, 2)->nullable(); // e.g. 8.50%
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sellers');
    }
};
