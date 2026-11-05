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
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique()->index();
            $table->string('type', 20)->default('return')->index(); // return, cancellation
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->string('reason');
            $table->text('customer_notes')->nullable();
            $table->json('photos')->nullable();
            $table->string('status', 32)->default('requested')->index();
            $table->foreignId('pickup_shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
            $table->string('pickup_waybill', 100)->nullable();
            $table->string('qc_status', 32)->default('pending')->index();
            $table->text('qc_notes')->nullable();
            $table->boolean('is_resellable')->default(true);
            $table->foreignId('refund_id')->nullable()->constrained('refunds')->nullOnDelete();
            $table->string('refund_mode', 20)->default('gateway'); // gateway, wallet
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('seller_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('qc_completed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['order_item_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('returns');
    }
};
