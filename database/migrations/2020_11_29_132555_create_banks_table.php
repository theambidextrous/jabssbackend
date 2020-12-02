<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBanksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('user', 20);
            $table->string('internal_ref', 32);
            $table->string('amount', 20);
            $table->string('bill_charges', 20);
            $table->string('currency', 3)->default('USD');
            $table->string('market_rate', 20);
            $table->string('applied_rate', 20);
            $table->string('forex_offset', 20);
            $table->string('external_ref')->nullable();
            $table->string('mpesa_ref')->nullable();
            $table->boolean('status')->default(false);
            $table->text('int_payload_string')->nullable();
            $table->text('ext_payload_string')->nullable();
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
        Schema::dropIfExists('banks');
    }
}
