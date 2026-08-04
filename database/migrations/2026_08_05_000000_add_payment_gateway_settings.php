<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value');
            $table->timestamps();
        });

        DB::table('settings')->insert([
            'key' => 'payment_gateway',
            'value' => config('payments.default', 'selcom'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('hotspot_transactions', function (Blueprint $table) {
            $table->string('payment_gateway')->default('selcom')->after('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('hotspot_transactions', fn (Blueprint $table) => $table->dropColumn('payment_gateway'));
        Schema::dropIfExists('settings');
    }
};
