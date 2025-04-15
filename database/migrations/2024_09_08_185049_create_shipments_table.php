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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained();
            $table->string('aus_order_id');
            $table->string('shipment_id');
            $table->string('article_id');
            $table->string('consignment_id');
            $table->string('shipment_reference');
            $table->string('order_reference');
            $table->string('total_cost');
            $table->string('total_cost_ex_gst');
            $table->string('total_gst');
            $table->string('movement_type');
            $table->string('status');
            $table->string('label_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
