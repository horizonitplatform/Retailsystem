<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrderTransportTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_transport', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('transport_id')->unsigned();
            $table->integer('order_id')->unsigned();

            $table->foreign('transport_id')->references('id')->on('employees')
                ->onDelete('cascade')->onUpdate('cascade');

            $table->foreign('order_id')->references('id')->on('orders')
                ->onDelete('cascade')->onUpdate('cascade');
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
        Schema::dropIfExists('order_transport');
    }
}
