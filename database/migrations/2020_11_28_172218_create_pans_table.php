<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pans', function (Blueprint $table) {
            $table->id();
            $table->string('user', 10);
            $table->string('cardname', 55);
            $table->string('mask', 12);
            $table->string('pan',100)->unique();
            $table->string('exp');
            $table->string('fingerprint');
            $table->string('pciprint');
            $table->boolean('isdefault')->default(true);
            $table->string('bank')->nullable();
            $table->string('bankcode')->nullable();
            $table->string('bankcountry')->nullable();
            $table->string('bankstate')->nullable();
            $table->string('cardtype');
            $table->string('icon');
            $table->timestamps();
            $table->unique(['user', 'isdefault']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pans');
    }
}
