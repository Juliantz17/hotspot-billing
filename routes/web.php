<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HotspotController;

// The URL customers are dropped onto from the MikroTik network
Route::get('/checkout', [HotspotController::class, 'showCheckout'])->name('hotspot.checkout');

// Handles processing form data
Route::post('/process-payment', [HotspotController::class, 'processPayment'])->name('hotspot.pay');

// Waiting page
Route::get('/waiting/{txn}', [HotspotController::class, 'showWaiting'])->name('hotspot.waiting');

// Resume Session
Route::post('/resume-session', [HotspotController::class, 'resumeSession'])->name('hotspot.resume');

// Webhook for Selcom Payment confirmation
Route::post('/webhook/selcom', [HotspotController::class, 'handleWebhook'])->name('webhook.selcom');

// Admin Auth
Route::get('/admin/login', [\App\Http\Controllers\AuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [\App\Http\Controllers\AuthController::class, 'processLogin'])->name('admin.login.submit');
Route::post('/admin/logout', [\App\Http\Controllers\AuthController::class, 'logout'])->name('admin.logout');

// Admin Dashboard & Features
Route::middleware(['admin'])->prefix('admin')->group(function () {
    Route::get('/', [\App\Http\Controllers\AdminController::class, 'index'])->name('admin.dashboard');
    Route::get('/earnings', [\App\Http\Controllers\AdminController::class, 'earnings'])->name('admin.earnings');
    
    // User management
    Route::post('/user/{id}/extend', [\App\Http\Controllers\AdminController::class, 'extend'])->name('admin.extend');
    Route::post('/user/{id}/kick', [\App\Http\Controllers\AdminController::class, 'kick'])->name('admin.kick');
    Route::delete('/user/txn/{id}', [\App\Http\Controllers\AdminController::class, 'destroyTxn'])->name('admin.txn.destroy');

    // Packages
    Route::get('/packages', [\App\Http\Controllers\Admin\PackageController::class, 'index'])->name('admin.packages');
    Route::post('/packages', [\App\Http\Controllers\Admin\PackageController::class, 'store'])->name('admin.packages.store');
    Route::put('/packages/{package}', [\App\Http\Controllers\Admin\PackageController::class, 'update'])->name('admin.packages.update');
    Route::delete('/packages/{package}', [\App\Http\Controllers\Admin\PackageController::class, 'destroy'])->name('admin.packages.destroy');
});