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
        Schema::create('pos_drafts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('label')->nullable();
            $table->json('items');
            $table->string('discount_type')->nullable();
            $table->unsignedBigInteger('discount_value')->default(0);
            $table->timestamps();
            $table->index(['business_id', 'outlet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_drafts');
    }
};
