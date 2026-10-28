<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('opening_cash')->default(0);
            $table->unsignedBigInteger('closing_cash')->nullable();  // uang fisik hasil hitung
            $table->bigInteger('expected_cash')->nullable();         // uang seharusnya menurut sistem
            $table->bigInteger('difference')->nullable();            // fisik - sistem
            $table->string('note')->nullable();
            $table->string('status')->default('open');               // open, closed
            $table->timestamps();
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'opened_at']);
        });

        DB::statement("CREATE UNIQUE INDEX shifts_one_open_per_user ON shifts (user_id) WHERE status = 'open'");

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('shift_id')->constrained()->cascadeOnDelete();
            $table->string('type');                      // in, out
            $table->unsignedBigInteger('amount');
            $table->string('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index('shift_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignUuid('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
        });
        Schema::dropIfExists('cash_movements');
        DB::statement('DROP INDEX IF EXISTS shifts_one_open_per_user');
        Schema::dropIfExists('shifts');
    }
};