<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HotspotController;

// The URL customers are dropped onto from the MikroTik network
Route::get('/checkout', [HotspotController::class, 'showCheckout'])->name('hotspot.checkout');

// Handles processing form data
Route::post('/process-payment', [HotspotController::class, 'processPayment'])->name('hotspot.pay');