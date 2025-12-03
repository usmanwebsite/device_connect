<?php

use App\Http\Controllers\AngularRedirectController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceLocationAssignController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PathController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\VisitorReportController;
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
Route::post('/dashboard/graph-data', [DashboardController::class, 'getGraphData'])
    ->name('dashboard.graph.data');
// Route::get('/redirect-to-angular', [AngularRedirectController::class, 'redirect'])->name('angular.redirect');


Route::prefix('reports')->group(function () {

    Route::get('/access-logs', [ReportController::class, 'accessLogs'])->name('reports.access-logs');
    Route::post('/access-logs/data', [ReportController::class, 'getAccessLogsData'])->name('reports.access-logs.data');
    Route::get('/staff-movement/{staffNo}', [ReportController::class, 'getStaffMovement'])->name('reports.staff-movement');

});

    Route::get('/paths', [PathController::class, 'index'])->name('paths.index');
    Route::post('/paths', [PathController::class, 'store'])->name('paths.store');
    Route::get('/paths/{id}/edit', [PathController::class, 'edit'])->name('paths.edit');
    Route::put('/paths/{id}', [PathController::class, 'update'])->name('paths.update');


    Route::prefix('visitor-report')->group(function () {
        Route::get('/', [VisitorReportController::class, 'index'])->name('visitor.report');
        Route::get('/export', [VisitorReportController::class, 'export'])->name('visitor.report.export');
    });

    Route::get('/redirect-to-angular/{route}', [AngularRedirectController::class, 'redirect'])
    ->where('route', '.*')
    ->name('angular.redirect');
