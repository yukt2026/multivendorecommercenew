<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Audit Trail for All AI Actions
        Schema::create('ai_action_logs', function (Blueprint $table) {
            $table->id();
            $table->string('feature', 50)->index();
            $table->string('action', 80)->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $table->string('prompt_summary')->nullable();
            $table->json('input_payload')->nullable();
            $table->json('output_payload')->nullable();
            $table->string('model_or_driver', 50)->default('rule_based');
            $table->unsignedInteger('tokens_used')->default(0);
            $table->decimal('latency_ms', 8, 2)->default(0.00);
            $table->string('status', 30)->default('success')->index();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'feature']);
            $table->index(['created_at']);
        });

        // 2. Order Fraud & Risk Scoring Assessments
        Schema::create('order_fraud_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete()->unique();
            $table->unsignedSmallInteger('risk_score')->default(0); // 0-100
            $table->string('risk_level', 20)->default('low')->index(); // 'low', 'medium', 'high'
            $table->json('reasons')->nullable();
            $table->json('signals')->nullable();
            $table->string('decision', 30)->default('approved')->index(); // 'approved', 'flagged_for_review', 'rejected'
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 3. Low-Stock & Demand Forecasting (Per-Seller Scoped)
        Schema::create('seller_demand_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('daily_sales_velocity', 8, 2)->default(0.00);
            $table->decimal('days_to_stockout', 8, 1)->nullable();
            $table->decimal('predicted_demand_30d', 8, 2)->default(0.00);
            $table->unsignedInteger('recommended_reorder_qty')->default(0);
            $table->string('stock_status', 30)->default('healthy')->index(); // 'healthy', 'low_stock', 'critical_stockout', 'overstocked'
            $table->json('forecast_metadata')->nullable();
            $table->timestamps();

            $table->unique(['seller_id', 'variant_id']);
            $table->index(['seller_id', 'stock_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_demand_forecasts');
        Schema::dropIfExists('order_fraud_assessments');
        Schema::dropIfExists('ai_action_logs');
    }
};
