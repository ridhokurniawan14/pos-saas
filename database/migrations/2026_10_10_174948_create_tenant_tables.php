<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('owner_id')->constrained('users');
            $table->string('timezone')->default('Asia/Jakarta');
            $table->string('currency', 3)->default('IDR');
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('status')->default('trial'); // trial, active, expired, suspended
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('outlets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('receipt_footer')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('business_id');
        });

        Schema::create('business_user', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('owner'); // owner, admin, kasir, gudang
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['business_id', 'user_id']);
        });

        Schema::create('outlet_user', function (Blueprint $table) {
            $table->foreignUuid('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['outlet_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_user');
        Schema::dropIfExists('business_user');
        Schema::dropIfExists('outlets');
        Schema::dropIfExists('businesses');
    }
};
