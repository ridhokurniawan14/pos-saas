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
        Schema::table('businesses', function (Blueprint $table) {
            $table->boolean('allow_negative_stock')->default(false);
            $table->string('receipt_logo_path')->nullable();
        });

        Schema::table('outlets', function (Blueprint $table) {
            $table->text('receipt_header')->nullable();
            $table->text('receipt_footer')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['allow_negative_stock', 'receipt_logo_path']);
        });

        Schema::table('outlets', function (Blueprint $table) {
            $table->dropColumn('receipt_header');
        });
    }
};
