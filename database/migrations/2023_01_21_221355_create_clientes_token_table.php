<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClientesTokenTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('clientes_token', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('token', 255);
            $table->integer('cliente_id');
            $table->dateTime('fecha_expiracion');
            $table->foreign('cliente_id')->references('clientecodigo')->on('clientes');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('clientes_token');
    }
}
