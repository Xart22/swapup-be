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
        Schema::create('items_', function (Blueprint $table) {
            $table->id();
            $table->string('item_id')->unique();
            $table->string('shopify_product_id')->nullable();
            $table->string('account');
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('tag_price');
            $table->string('expected_credit')->nullable();
            $table->string('status_item');
            $table->date('expires');
            $table->date('schedule_start');
            $table->date('sold_item_date')->nullable();
            $table->string('cogs')->nullable();
            $table->boolean('status_item_expired')->default(false);
            $table->string('location_name');
            $table->timestamp('created');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items_', function (Blueprint $table) {
            $table->dropColumn('items_');
        });
    }
};
