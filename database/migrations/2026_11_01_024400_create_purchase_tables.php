<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('business_id');
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUID dari browser (cegah simpan ganda)
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number');
            $table->date('purchase_date');
            $table->unsignedBigInteger('total');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['business_id', 'number']);
            $table->index(['business_id', 'purchase_date']);
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name_snapshot');
            $table->string('unit_name')->nullable();
            $table->unsignedInteger('conversion')->default(1);
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('cost');      // harga beli per satuan yang dibeli
            $table->unsignedBigInteger('subtotal');
            $table->timestamps();
            $table->index('purchase_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('min_stock')->default(0); // 0 = peringatan mati
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('min_stock');
        });
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('suppliers');
    }
};