<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('user', 20);
            $table->string('internal_ref', 32);
            $table->string('sup_amount', 20);
            $table->string('inf_amount', 20);
            $table->string('bill_charges', 20);
            $table->string('sup_currency', 3)->default('USD');
            $table->string('inf_currency', 3)->default('KES');
            $table->string('market_rate', 20);
            $table->string('applied_rate', 20);
            $table->string('forex_offset', 20);
            $table->string('sup_forex_charges', 20)->nullable();
            $table->string('inf_forex_charges', 20);
            $table->string('bank_tran_ref');
            $table->string('mpesa_tran_ref');
            $table->boolean('status')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
}
