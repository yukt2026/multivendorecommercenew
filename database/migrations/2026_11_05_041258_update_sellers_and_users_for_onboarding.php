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
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
        });

        Schema::table('sellers', function (Blueprint $table) {
            $table->dropIndex(['pan']);
            $table->dropIndex(['gstin']);
        });

        Schema::table('sellers', function (Blueprint $table) {
            $table->text('pan')->nullable()->change();
            $table->text('gstin')->nullable()->change();
            $table->unsignedTinyInteger('onboarding_step')->default(1)->after('status');
            $table->string('category_focus')->nullable()->after('seller_type');
            $table->timestamp('submitted_at')->nullable()->after('approved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn(['onboarding_step', 'category_focus', 'submitted_at']);
            $table->string('pan', 20)->nullable()->change();
            $table->string('gstin', 20)->nullable()->change();
            $table->index('pan');
            $table->index('gstin');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone_verified_at');
        });
    }
};

