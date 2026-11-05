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
        // 1. 301 / 302 Redirect Rules Table
        Schema::create('redirect_rules', function (Blueprint $table) {
            $table->id();
            $table->string('source_path')->unique()->index();
            $table->string('target_path');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('hit_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();
        });

        // 2. SEO enhancement columns for brands
        Schema::table('brands', function (Blueprint $table) {
            if (!Schema::hasColumn('brands', 'meta_title')) {
                $table->string('meta_title')->nullable()->after('name');
            }
            if (!Schema::hasColumn('brands', 'meta_description')) {
                $table->text('meta_description')->nullable()->after('meta_title');
            }
            if (!Schema::hasColumn('brands', 'og_image')) {
                $table->string('og_image')->nullable()->after('logo');
            }
            if (!Schema::hasColumn('brands', 'canonical_url')) {
                $table->string('canonical_url')->nullable()->after('og_image');
            }
        });

        // 3. SEO enhancement columns for products
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'og_image')) {
                $table->string('og_image')->nullable()->after('meta_description');
            }
            if (!Schema::hasColumn('products', 'canonical_url')) {
                $table->string('canonical_url')->nullable()->after('og_image');
            }
            if (!Schema::hasColumn('products', 'noindex')) {
                $table->boolean('noindex')->default(false)->after('canonical_url');
            }
        });

        // 4. SEO enhancement columns for categories
        Schema::table('categories', function (Blueprint $table) {
            if (!Schema::hasColumn('categories', 'og_image')) {
                $table->string('og_image')->nullable()->after('meta_description');
            }
            if (!Schema::hasColumn('categories', 'canonical_url')) {
                $table->string('canonical_url')->nullable()->after('og_image');
            }
        });

        // 5. SEO enhancement columns for stores
        Schema::table('stores', function (Blueprint $table) {
            if (!Schema::hasColumn('stores', 'og_image')) {
                $table->string('og_image')->nullable()->after('meta_description');
            }
            if (!Schema::hasColumn('stores', 'canonical_url')) {
                $table->string('canonical_url')->nullable()->after('og_image');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('redirect_rules');

        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description', 'og_image', 'canonical_url']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['og_image', 'canonical_url', 'noindex']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['og_image', 'canonical_url']);
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['og_image', 'canonical_url']);
        });
    }
};
