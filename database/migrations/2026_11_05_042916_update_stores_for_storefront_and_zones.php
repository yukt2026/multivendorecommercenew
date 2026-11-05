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
        Schema::table('stores', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('email');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->decimal('delivery_radius_km', 8, 2)->nullable()->after('meta_description');
            $table->json('category_ids')->nullable()->after('delivery_radius_km');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description', 'delivery_radius_km', 'category_ids']);
        });
    }
};

