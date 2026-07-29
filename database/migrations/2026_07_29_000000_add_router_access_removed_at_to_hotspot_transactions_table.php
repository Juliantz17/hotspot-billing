<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotspot_transactions', function (Blueprint $table) {
            $table->timestamp('router_access_removed_at')
                ->nullable()
                ->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('hotspot_transactions', function (Blueprint $table) {
            $table->dropColumn('router_access_removed_at');
        });
    }
};
