<?php

use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HotspotController;
use Illuminate\Support\Facades\Route;

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
Route::get('/admin/login', [AuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AuthController::class, 'processLogin'])->name('admin.login.submit');
Route::post('/admin/logout', [AuthController::class, 'logout'])->name('admin.logout');

// Admin Dashboard & Features
Route::middleware(['admin'])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('admin.dashboard');
    Route::get('/earnings', [AdminController::class, 'earnings'])->name('admin.earnings');
    Route::get('/analytics', [AdminController::class, 'analytics'])->name('admin.analytics');

    // Active Sessions & Router status
    Route::get('/active-sessions', [AdminController::class, 'activeSessions'])->name('admin.active_sessions');
    Route::post('/active-sessions/{id}/kick', [AdminController::class, 'kickActiveSession'])->name('admin.active_sessions.kick');
    Route::get('/router', [AdminController::class, 'routerPanel'])->name('admin.router');
    Route::get('/queues', [AdminController::class, 'routerQueues'])->name('admin.queues');
    Route::get('/logs', [AdminController::class, 'routerLogs'])->name('admin.logs');
    Route::get('/router-status', [AdminController::class, 'routerStatus'])->name('admin.router_status');
    Route::get('/router-snapshot', [AdminController::class, 'routerSnapshot'])->name('admin.router_snapshot');
    Route::post('/router/reboot', [AdminController::class, 'rebootRouter'])->name('admin.router_reboot');
    Route::get('/live-metrics', [AdminController::class, 'liveMetrics'])->name('admin.live_metrics');

    // User management
    Route::post('/transactions/{id}/extend', [AdminController::class, 'extend'])->name('admin.extend');
    Route::post('/transactions/{id}/kick', [AdminController::class, 'kick'])->name('admin.kick');
    Route::post('/transactions/{id}/reconnect', [AdminController::class, 'reconnectDevice'])->name('admin.reconnect');
    Route::delete('/transactions/{id}', [AdminController::class, 'destroyTxn'])->name('admin.txn.destroy');

    // Packages
    Route::get('/packages', [PackageController::class, 'index'])->name('admin.packages');
    Route::post('/packages', [PackageController::class, 'store'])->name('admin.packages.store');
    Route::put('/packages/{package}', [PackageController::class, 'update'])->name('admin.packages.update');
    Route::delete('/packages/{package}', [PackageController::class, 'destroy'])->name('admin.packages.destroy');
});
