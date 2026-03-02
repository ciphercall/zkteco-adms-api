<?php

use App\Http\Controllers\ZKTeco\AdmsController;
use App\Http\Controllers\ZKTeco\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return redirect('/dashboard');
});

// ─── Dashboard & API ─────────────────────────────────────────────────────────
Route::get('/dashboard', [DashboardController::class, 'index']);

Route::prefix('api/zkteco')->group(function () {
    Route::get('stats', [DashboardController::class, 'stats']);
    Route::get('access-events', [DashboardController::class, 'accessEvents']);
    Route::get('device-status', [DashboardController::class, 'deviceStatus']);
    Route::get('users', [DashboardController::class, 'users']);
    Route::get('raw-logs', [DashboardController::class, 'rawLogs']);
    Route::get('timeline', [DashboardController::class, 'timeline']);
});

Route::prefix('iclock')->group(function () {
    // ── Section 7.1: Initialization + Section 10: Data Upload ──
    Route::match(['GET', 'POST'], 'cdata', [AdmsController::class, 'cdata']);

    // ── Section 7.4: Registration ──
    Route::match(['GET', 'POST'], 'registry', [AdmsController::class, 'registry']);

    // ── Section 7.5: Download Configuration Parameters ──
    Route::match(['GET', 'POST'], 'push', [AdmsController::class, 'push']);

    // ── Section 9: Heartbeat ──
    Route::match(['GET', 'POST'], 'ping', [AdmsController::class, 'ping']);

    // ── Section 7.2/7.3: Key Exchange (encryption) ──
    Route::match(['GET', 'POST'], 'exchange', [AdmsController::class, 'exchange']);

    // ── Section 11.1: Download Cache Command ──
    Route::match(['GET', 'POST'], 'getrequest', [AdmsController::class, 'getRequest']);
    Route::match(['GET', 'POST'], 'getreq', [AdmsController::class, 'getRequest']);

    // ── Section 10.4: Command results ──
    Route::match(['GET', 'POST'], 'devicecmd', [AdmsController::class, 'deviceCmd']);

    // ── Security PUSH: heartbeat + commands ──
    Route::match(['GET', 'POST'], 'service/control', [AdmsController::class, 'serviceControl']);

    // ── Security PUSH: query data results ──
    Route::match(['GET', 'POST'], 'querydata', [AdmsController::class, 'queryData']);

    // ── File/photo data ──
    Route::match(['GET', 'POST'], 'fdata', [AdmsController::class, 'fdata']);

    // ── Fallback: double-path bugs + unrecognized endpoints ──
    Route::match(['GET', 'POST'], '{any}', [AdmsController::class, 'fallback'])->where('any', '.*');
});
