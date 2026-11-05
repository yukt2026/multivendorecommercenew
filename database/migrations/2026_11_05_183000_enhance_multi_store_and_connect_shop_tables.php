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
        // 1. Enhance store_connections with API credentials & webhooks
        Schema::table('store_connections', function (Blueprint $table) {
            if (!Schema::hasColumn('store_connections', 'name')) {
                $table->string('name', 100)->nullable()->after('type');
            }
            if (!Schema::hasColumn('store_connections', 'api_key')) {
                $table->string('api_key', 64)->nullable()->unique()->after('status');
            }
            if (!Schema::hasColumn('store_connections', 'api_secret_hash')) {
                $table->string('api_secret_hash', 128)->nullable()->after('api_key');
            }
            if (!Schema::hasColumn('store_connections', 'webhook_url')) {
                $table->string('webhook_url', 500)->nullable()->after('config');
            }
            if (!Schema::hasColumn('store_connections', 'webhook_secret')) {
                $table->string('webhook_secret', 128)->nullable()->after('webhook_url');
            }
        });

        // 2. Enhance store_staff with invitations & status
        Schema::table('store_staff', function (Blueprint $table) {
            if (!Schema::hasColumn('store_staff', 'status')) {
                $table->string('status', 32)->default('active')->index()->after('role');
            }
            if (!Schema::hasColumn('store_staff', 'invite_token')) {
                $table->string('invite_token', 64)->nullable()->unique()->after('status');
            }
            if (!Schema::hasColumn('store_staff', 'invited_by')) {
                $table->foreignId('invited_by')->nullable()->after('permissions')->constrained('users')->nullOnDelete();
            }
        });

        // 3. Create catalog_imports for CSV/Excel bulk upload tracking
        if (!Schema::hasTable('catalog_imports')) {
            Schema::create('catalog_imports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
                $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
                $table->string('file_name');
                $table->string('file_path');
                $table->string('type', 32)->default('products'); // products, inventory
                $table->string('status', 32)->default('pending')->index(); // pending, processing, completed, failed
                $table->unsignedInteger('total_rows')->default(0);
                $table->unsignedInteger('imported_rows')->default(0);
                $table->unsignedInteger('failed_rows')->default(0);
                $table->json('column_mapping')->nullable();
                $table->json('errors')->nullable();
                $table->timestamps();

                $table->index(['seller_id', 'created_at']);
                $table->index(['store_id', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_imports');

        Schema::table('store_staff', function (Blueprint $table) {
            $table->dropForeign(['invited_by']);
            $table->dropColumn(['status', 'invite_token', 'invited_by']);
        });

        Schema::table('store_connections', function (Blueprint $table) {
            $table->dropColumn(['name', 'api_key', 'api_secret_hash', 'webhook_url', 'webhook_secret']);
        });
    }
};
