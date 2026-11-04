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
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['business_id', 'name']);
            $table->index(['business_id', 'phone']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });
        Schema::dropIfExists('customers');
    }
};
