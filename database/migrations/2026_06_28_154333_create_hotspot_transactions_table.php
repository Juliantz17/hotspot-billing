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
    Schema::create('hotspot_transactions', function (Blueprint $table) {
        $table->id();
        $table->string('transaction_id')->unique();
        $table->string('mac_address');
        $table->string('phone_number');
        $table->integer('amount'); // TZS amount
        $table->enum('status', ['PENDING', 'SUCCESS', 'FAILED'])->default('PENDING');
        $table->integer('duration_minutes');
        $table->timestamp('expires_at')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotspot_transactions');
    }
};
