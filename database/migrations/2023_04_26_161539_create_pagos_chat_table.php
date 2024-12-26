<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePagosChatTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pagos_chat', function (Blueprint $table) {
            $table->increments('id');
            $table->string('api_key');
            $table->string('id_factura');
            $table->string('estado');
            $table->string('id_bancard');
            $table->string('resultado_bancard');
            $table->string('ci_ruc');
            $table->string('monto');
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
        Schema::dropIfExists('pagos_chat');
    }
}
