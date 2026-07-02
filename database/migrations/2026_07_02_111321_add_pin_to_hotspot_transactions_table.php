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
        Schema::table('hotspot_transactions', function (Blueprint $table) {
            $table->string('pin', 6)->nullable()->after('speed_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotspot_transactions', function (Blueprint $table) {
            $table->dropColumn('pin');
        });
    }
};
