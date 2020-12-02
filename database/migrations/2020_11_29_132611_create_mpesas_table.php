<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMpesasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mpesas', function (Blueprint $table) {
            $table->id();
            $table->string('user', 20);
            $table->string('internal_ref', 32);
            $table->string('amount', 20);
            $table->string('receiver', 20);
            $table->string('receiver_name', 55)->nullable();
            $table->string('account', 55)->nullable();
            $table->string('send_type', 1);
            $table->string('external_ref')->nullable();
            $table->boolean('status')->default(false);
            $table->string('note')->nullable();
            $table->string('bank_ref')->nullable();
            $table->text('int_payload_string');
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
        Schema::dropIfExists('mpesas');
    }
}
