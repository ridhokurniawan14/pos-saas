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
        Schema::table('product_price_tiers', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'min_qty']);
            $table->string('unit_name')->nullable()->after('product_id');
            $table->index(['product_id', 'unit_name', 'min_qty']);
        });
    }

    public function down(): void
    {
        Schema::table('product_price_tiers', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'unit_name', 'min_qty']);
            $table->dropColumn('unit_name');
            $table->unique(['product_id', 'min_qty']);
        });
    }
};
