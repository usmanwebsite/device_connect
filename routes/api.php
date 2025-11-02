<?php

use App\Http\Controllers\DeviceCommunicationController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\QRCodeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

    Route::prefix('qr')->group(function () {
        Route::post('/validate', [QRCodeController::class, 'validateQR']);
        Route::get('/history', [QRCodeController::class, 'getScanHistory']);
    });

    // Device control endpoints
    Route::prefix('device')->group(function () {
        Route::get('/status', [DeviceController::class, 'getStatus']);
        Route::post('/command', [DeviceController::class, 'sendCommand']);
        Route::post('/open-door', [DeviceController::class, 'openDoor']);
        Route::post('/clear-cards', [DeviceController::class, 'clearCards']);
    });


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

