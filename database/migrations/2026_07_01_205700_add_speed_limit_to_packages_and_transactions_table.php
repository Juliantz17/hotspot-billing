<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->string('speed_limit')->nullable()->after('price');
        });

        Schema::table('hotspot_transactions', function (Blueprint $table) {
            $table->string('speed_limit')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('speed_limit');
        });

        Schema::table('hotspot_transactions', function (Blueprint $table) {
            $table->dropColumn('speed_limit');
        });
    }
};
