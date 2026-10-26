<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');                       // dus, lusin, pack
            $table->unsignedInteger('conversion');        // isi per satuan (1 dus = 24 pcs)
            $table->unsignedBigInteger('sell_price');     // harga per satuan ini
            $table->string('barcode')->nullable();
            $table->timestamps();
            $table->index('product_id');
            $table->index(['business_id', 'barcode']);
        });

        Schema::create('product_price_tiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('min_qty');           // dalam satuan dasar
            $table->unsignedBigInteger('price');          // harga per satuan dasar
            $table->timestamps();
            $table->unique(['product_id', 'min_qty']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_tiers');
        Schema::dropIfExists('product_units');
    }
};
