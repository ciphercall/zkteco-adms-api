<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ZKTeco\RawLogsController;
use App\Http\Controllers\ZKTeco\UserRegistrationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/zkteco/raw-logs', [RawLogsController::class, 'index']);
Route::post('/zkteco/register-user', [UserRegistrationController::class, 'store']);
Route::put('/zkteco/device-users/{id}', [UserRegistrationController::class, 'update']);
Route::delete('/zkteco/device-users/{id}', [UserRegistrationController::class, 'destroy']);
Route::post('/zkteco/sync-device-users', [UserRegistrationController::class, 'syncDeviceUsers']);
Route::get('/zkteco/device-users-list', [UserRegistrationController::class, 'deviceUsersList']);
Route::get('/zkteco/command-status/{device_sn}/{pin}', [UserRegistrationController::class, 'commandStatus']);
Route::get('/zkteco/known-devices', [UserRegistrationController::class, 'knownDevices']);
Route::get('/zkteco/registration-stats', [UserRegistrationController::class, 'registrationStats']);
