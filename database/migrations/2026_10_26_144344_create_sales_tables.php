<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUID dari client
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('order_number');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('tax')->default(0);
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('paid');
            $table->unsignedBigInteger('change_amount')->default(0);
            $table->string('status')->default('completed'); // completed, void, refunded
            $table->string('note')->nullable();
            $table->timestamp('created_at_client')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'order_number']);
            $table->index(['business_id', 'outlet_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name_snapshot');
            $table->string('unit_name')->nullable();          // null = satuan dasar
            $table->unsignedInteger('conversion')->default(1);
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('price_snapshot');     // harga per satuan yang dijual
            $table->unsignedBigInteger('cost_snapshot');      // modal per satuan yang dijual
            $table->unsignedBigInteger('subtotal');
            $table->timestamps();
            $table->index('order_id');
        });

        Schema::create('order_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            $table->string('method'); // cash, qris, debit, transfer
            $table->unsignedBigInteger('amount');
            $table->string('ref')->nullable();
            $table->timestamps();
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
