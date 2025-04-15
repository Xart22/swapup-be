<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('gift_details', function (Blueprint $table) {
            $table->dateTime('request_date')->change(); // Change request_date to dateTime
        });
    }

    public function down()
    {
        Schema::table('gift_details', function (Blueprint $table) {
            $table->date('request_date')->change(); // Revert back to date if needed
        });
    }
};
