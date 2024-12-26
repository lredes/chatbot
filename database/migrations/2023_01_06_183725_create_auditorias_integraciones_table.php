<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuditoriasIntegracionesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('auditorias_integraciones', function (Blueprint $table) {
            $table->id();
            $table->string('uniqueid')->nullable();
            $table->string('metodo');
            $table->string('funcion');
            $table->longText('request')->nullable();
            $table->longText('response')->nullable();
            $table->string('ips')->nullable();
            $table->string('user_agent')->nullable();
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
        Schema::dropIfExists('auditorias_integraciones');
    }
}
