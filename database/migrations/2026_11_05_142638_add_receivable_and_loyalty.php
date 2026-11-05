<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->boolean('loyalty_enabled')->default(false);
            $table->unsignedInteger('loyalty_earn_per')->default(10000);   // tiap Rp ini = 1 poin
            $table->unsignedInteger('loyalty_point_value')->default(100);  // 1 poin = Rp ini saat ditukar
            $table->unsignedInteger('loyalty_min_redeem')->default(10);    // minimal poin yang boleh ditukar
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->integer('points')->default(0);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('due_amount')->default(0);        // sisa bon saat transaksi
            $table->unsignedInteger('points_used')->default(0);
            $table->unsignedBigInteger('points_discount')->default(0);
            $table->unsignedInteger('points_earned')->default(0);
        });

        Schema::create('receivable_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->uuid('batch_id');                       // satu kali bayar bisa melunasi beberapa struk
            $table->string('method');
            $table->unsignedBigInteger('amount');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['customer_id', 'created_at']);
            $table->index('order_id');
        });

        Schema::create('point_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');                         // earn, redeem, reverse, adjust
            $table->integer('points');                      // + masuk, - keluar
            $table->integer('balance_after');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_movements');
        Schema::dropIfExists('receivable_payments');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['due_amount', 'points_used', 'points_discount', 'points_earned']);
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('points');
        });
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['loyalty_enabled', 'loyalty_earn_per', 'loyalty_point_value', 'loyalty_min_redeem']);
        });
    }
};
