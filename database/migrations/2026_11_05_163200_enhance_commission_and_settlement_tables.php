<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_entries', function (Blueprint $table) {
            $table->decimal('tax_on_commission', 12, 2)->default(0.00)->after('commission');
            $table->string('entry_type', 32)->default('standard')->index()->after('net_payable'); // standard, reversal
            $table->text('notes')->nullable()->after('entry_type');
        });

        Schema::table('seller_settlements', function (Blueprint $table) {
            $table->decimal('tax_on_commission', 12, 2)->default(0.00)->after('commission');
            $table->decimal('cod_collected', 12, 2)->default(0.00)->after('tax_on_commission');
            $table->string('hold_reason')->nullable()->after('status');
            $table->text('notes')->nullable()->after('hold_reason');
        });

        Schema::table('settlement_items', function (Blueprint $table) {
            $table->string('description')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('settlement_items', function (Blueprint $table) {
            $table->dropColumn(['description']);
        });

        Schema::table('seller_settlements', function (Blueprint $table) {
            $table->dropColumn(['tax_on_commission', 'cod_collected', 'hold_reason', 'notes']);
        });

        Schema::table('commission_entries', function (Blueprint $table) {
            $table->dropColumn(['tax_on_commission', 'entry_type', 'notes']);
        });
    }
};
