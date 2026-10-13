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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->string('supplier');
            $table->date('expense_date');
            $table->char('currency', 3);                              // BRL ou USD
            $table->unsignedBigInteger('amount_cents');               // valor original em centavos
            $table->decimal('exchange_rate', 12, 6)->nullable();      // cotação usada (1 para BRL)
            $table->date('exchange_rate_date')->nullable();           // data real da cotação
            $table->unsignedBigInteger('amount_brl_cents')->nullable(); // valor em BRL, após conversão
            $table->string('status')->default('pending');             // pending | converted
            $table->timestamps();
            $table->index('expense_date'); // o relatório filtra por período
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
