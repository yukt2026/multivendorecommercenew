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
        Schema::create('rider_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_partner_id')->unique()->constrained('delivery_partners')->cascadeOnDelete();
            $table->decimal('balance', 12, 2)->default(0.00);
            $table->decimal('pending_payout', 12, 2)->default(0.00);
            $table->decimal('total_earned', 12, 2)->default(0.00);
            $table->timestamps();
        });

        Schema::create('rider_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_wallet_id')->constrained('rider_wallets')->cascadeOnDelete();
            $table->string('type', 20)->index(); // credit, debit
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('category', 32)->index(); // delivery_payout, distance_bonus, tip, cod_deduction, admin_adjustment, bank_withdrawal
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description', 255);
            $table->timestamps();

            $table->index(['rider_wallet_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rider_wallet_transactions');
        Schema::dropIfExists('rider_wallets');
    }
};
