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
        // 1. Notification Templates (Editable from Admin with variable placeholders)
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 64)->index(); // order_confirmed, otp, payment_confirmed, etc.
            $table->string('channel', 32)->index();   // database, mail, sms, whatsapp, push
            $table->string('subject')->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['event_key', 'channel']);
        });

        // 2. User Notification Preferences (Channel opt-ins & stored promotional consent)
        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 64)->default('orders'); // orders, account, security, promotions
            $table->boolean('channel_database')->default(true);
            $table->boolean('channel_mail')->default(true);
            $table->boolean('channel_sms')->default(true);
            $table->boolean('channel_whatsapp')->default(true);
            $table->boolean('channel_push')->default(true);
            $table->boolean('promotional_consent')->default(false);
            $table->timestamp('promotional_consent_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'category']);
        });

        // 3. Notification Dispatch Logs (Auditing, Delivery Telemetry & Retry Tracking)
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('notifiable');
            $table->string('event_key', 64)->index();
            $table->string('channel', 32)->index();
            $table->string('recipient');
            $table->string('subject')->nullable();
            $table->text('content');
            $table->string('status', 32)->default('sent')->index(); // sent, failed, retried
            $table->text('error_message')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['event_key', 'status']);
            $table->index(['created_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('user_notification_preferences');
        Schema::dropIfExists('notification_templates');
    }
};
