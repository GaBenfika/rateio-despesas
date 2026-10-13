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
        Schema::create('expense_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained();
            $table->unsignedSmallInteger('percentage_bp');               // 10000 = 100%
            $table->unsignedBigInteger('amount_brl_cents')->nullable();  // preenchido no rateio
            $table->timestamps();
            $table->unique(['expense_id', 'unit_id']); // a mesma unidade não aparece duas vezes
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_allocations');
    }
};
