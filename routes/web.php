<?php

use App\Http\Controllers\AngularRedirectController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceLocationAssignController;
use App\Http\Controllers\LoginController;
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
    return view('welcome');
});

// layout

Route::get('/layout', function () {
    return view('layout.main_layout');
});

// web.php
Route::get('/assign-device', [DeviceLocationAssignController::class, 'create'])->name('assign.device.form');
Route::post('/assign-device', [DeviceLocationAssignController::class, 'store'])->name('assign.device.store');

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::post('/login', [LoginController::class, 'login'])->name('login');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
// Route::get('/redirect-to-angular', [AngularRedirectController::class, 'redirect'])->name('angular.redirect');

Route::get('/redirect-to-angular/{route}', [AngularRedirectController::class, 'redirect'])
     ->where('route', '.*')
     ->name('angular.redirect');
