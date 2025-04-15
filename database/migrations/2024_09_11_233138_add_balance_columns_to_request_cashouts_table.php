<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('request_cashouts', function (Blueprint $table) {
            $table->integer('before_balance')->after('cashout_amount');
            $table->integer('after_balance')->after('before_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_cashouts', function (Blueprint $table) {
            $table->dropColumn('before_balance');
            $table->dropColumn('after_balance');
        });
    }
};
