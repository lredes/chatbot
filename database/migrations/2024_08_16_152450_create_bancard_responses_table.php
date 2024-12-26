<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBancardResponsesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bancard_responses', function (Blueprint $table) {
            $table->id();
            $table->string('link_alias')->nullable();
            $table->string('link_url')->nullable();
            $table->string('status')->nullable();
            $table->string('response_code')->nullable();
            $table->string('response_description')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('installment_number')->nullable();
            $table->string('description')->nullable();
            $table->timestamp('date_time')->nullable();
            $table->string('ticket_number')->nullable();
            $table->string('authorization_code')->nullable();
            $table->string('commerce_name')->nullable();
            $table->string('branch_name')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('bin')->nullable();
            $table->string('type')->nullable();
            $table->text('payer')->nullable(); // Cambiado de json a text
            $table->integer('entity_id')->nullable();
            $table->string('entity_name')->nullable();
            $table->integer('brand_id')->nullable();
            $table->string('brand_name')->nullable();
            $table->integer('product_id')->nullable();
            $table->string('product_name')->nullable();
            $table->integer('affinity_id')->nullable();
            $table->string('affinity_name')->nullable();
            $table->string('payment_type_description')->nullable();
            $table->string('card_last_numbers', 4)->nullable();
            $table->string('account_type', 2)->nullable();
            $table->text('additional_info')->nullable(); // Cambiado de json a text
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bancard_responses');
    }
}
