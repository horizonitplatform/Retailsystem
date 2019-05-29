<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeTypeSystemLinkTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('system_link', function (Blueprint $table) {
            DB::statement("ALTER TABLE system_link CHANGE COLUMN type type ENUM('product', 'category') NOT NULL");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('system_link', function (Blueprint $table) {
            DB::statement("ALTER TABLE system_link CHANGE COLUMN type type ENUM('product') NOT NULL");
        });
    }
}
