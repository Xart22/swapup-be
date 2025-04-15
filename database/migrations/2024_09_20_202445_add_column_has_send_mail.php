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
        Schema::table('items_', function (Blueprint $table) {
            $table->boolean('has_send_mailer')->default(false)->after('location_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items_', function (Blueprint $table) {
            $table->dropColumn('has_send_mailer');
        });
    }
};
