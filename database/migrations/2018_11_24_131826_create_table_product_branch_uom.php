<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableProductBranchUom extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_branch_uom', function (Blueprint $table) {
            $table->increments('id');
            // $table->integer('product_brach_id')->unsigned();
            // $table->foreign('product_brach_id')->references('id')->on('product_brach')->onDelete('cascade')->onUpdate('cascade');
            $table->integer('product_branch_id');
            $table->string('price_list');
            $table->double('price');
            $table->string('uom');
            $table->integer('um_convert');
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
        Schema::dropIfExists('product_branch_uom');
    }
}
