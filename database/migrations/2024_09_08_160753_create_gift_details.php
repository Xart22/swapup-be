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
        Schema::create('gift_details', function (Blueprint $table) {
            $table->string('id_gift_card')->unique();
            $table->date('request_date');
            $table->string("user_id");
            $table->double('amount_used');
            $table->double("percentage_amount_used");
            $table->double("gift_total_amount");
            $table->double("before_balance");
            $table->double("after_balance");
            $table->string("gen_code", '25');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gift_details');
    }
};
