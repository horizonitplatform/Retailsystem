<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRoleToCustomersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('role', 20)->default('user');
            $table->string('phone', 20)->nullable();
            $table->string('social_type', 20)->nullable();
            $table->string('social_id', 100)->nullable();
            $table->string('email')->nullable()->change();
            $table->string('profile_photo', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['sale_price', 'phone', 'social_type', 'social_id', 'email', 'profile_photo']);
        });
    }
}
