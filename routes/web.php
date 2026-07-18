<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HotspotController;
use App\Http\Controllers\AdminController;

// The URL customers are dropped onto from the MikroTik network
Route::get('/checkout', [HotspotController::class, 'showCheckout'])->name('hotspot.checkout');
Route::post('/reconnect-user', [HotspotController::class, 'reconnectUser'])->name('hotspot.reconnect_user');
Route::post('/recover-package', [HotspotController::class, 'recoverPackage'])->name('hotspot.recover_package');

// Handles processing form data
Route::post('/process-payment', [HotspotController::class, 'processPayment'])->name('hotspot.pay');

// Waiting page
Route::get('/waiting/{txn}', [HotspotController::class, 'showWaiting'])->name('hotspot.waiting');

// Webhook for Selcom Payment confirmation
Route::post('/webhook/selcom', [HotspotController::class, 'handleWebhook'])->name('webhook.selcom');

// Admin Auth
Route::get('/admin/login', [\App\Http\Controllers\AuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [\App\Http\Controllers\AuthController::class, 'processLogin'])->name('admin.login.submit');
Route::post('/admin/logout', [\App\Http\Controllers\AuthController::class, 'logout'])->name('admin.logout');

// Admin Dashboard & Features
Route::middleware(['admin'])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('admin.dashboard');
    Route::get('/earnings', [AdminController::class, 'earnings'])->name('admin.earnings');
    Route::get('/analytics', [AdminController::class, 'analytics'])->name('admin.analytics');
    
    // Active Sessions & Router status
    Route::get('/active-sessions', [AdminController::class, 'activeSessions'])->name('admin.active_sessions');
    Route::post('/active-sessions/{id}/kick', [AdminController::class, 'kickActiveSession'])->name('admin.active_sessions.kick');
    Route::get('/router-status', [AdminController::class, 'routerStatus'])->name('admin.router_status');
    
    // User management
    Route::post('/transactions/{id}/extend', [AdminController::class, 'extend'])->name('admin.extend');
    Route::post('/transactions/{id}/kick', [AdminController::class, 'kick'])->name('admin.kick');
    Route::post('/transactions/{id}/reconnect', [AdminController::class, 'reconnectDevice'])->name('admin.reconnect');
    Route::delete('/transactions/{id}', [AdminController::class, 'destroyTxn'])->name('admin.txn.destroy');

    // Packages
    Route::get('/packages', [\App\Http\Controllers\Admin\PackageController::class, 'index'])->name('admin.packages');
    Route::post('/packages', [\App\Http\Controllers\Admin\PackageController::class, 'store'])->name('admin.packages.store');
    Route::put('/packages/{package}', [\App\Http\Controllers\Admin\PackageController::class, 'update'])->name('admin.packages.update');
    Route::delete('/packages/{package}', [\App\Http\Controllers\Admin\PackageController::class, 'destroy'])->name('admin.packages.destroy');
});