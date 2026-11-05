<?php

declare(strict_types=1);

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
        Schema::table('carts', function (Blueprint $table) {
            $table->string('coupon_code', 50)->nullable()->after('is_active');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->boolean('is_saved_for_later')->default(false)->after('price_snapshot')->index();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('wishlist_share_token', 64)->nullable()->unique()->after('dob');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('wishlist_share_token');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn('is_saved_for_later');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('coupon_code');
        });
    }
};
