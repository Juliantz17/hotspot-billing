<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->boolean('fup_enabled')->default(false)->after('speed_limit');
            $table->unsignedBigInteger('fup_threshold_bytes')->nullable()->after('fup_enabled');
            $table->string('fup_speed_limit')->nullable()->after('fup_threshold_bytes');
        });

        Schema::table('hotspot_transactions', function (Blueprint $table) {
            $table->foreignId('package_id')->nullable()->after('transaction_id')
                ->constrained('packages')->nullOnDelete();
            $table->unsignedBigInteger('usage_bytes')->default(0)->after('speed_limit');
            $table->unsignedBigInteger('router_counter_bytes')->nullable()->after('usage_bytes');
            $table->timestamp('usage_checked_at')->nullable()->after('router_counter_bytes');
            $table->timestamp('fup_applied_at')->nullable()->after('usage_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('hotspot_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_id');
            $table->dropColumn([
                'usage_bytes',
                'router_counter_bytes',
                'usage_checked_at',
                'fup_applied_at',
            ]);
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn([
                'fup_enabled',
                'fup_threshold_bytes',
                'fup_speed_limit',
            ]);
        });
    }
};
