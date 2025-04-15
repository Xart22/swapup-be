<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('variant_id')->constrained()->onDelete('cascade');
            $table->string('payment_intent_id');
            $table->string('client_secret');
            $table->string('stripe_status');
            $table->string('status')->default('pending');
            $table->boolean('label_generated')->default(false);

            $table->timestamps();
        });

        DB::statement("ALTER TABLE orders AUTO_INCREMENT = 012000;");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
