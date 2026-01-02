<?php

use App\Http\Controllers\AngularRedirectController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceLocationAssignController;
use App\Http\Controllers\JavaAuthController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PathController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SecurityAlertController;
use App\Http\Controllers\SecurityAlertPriorityController;
use App\Http\Controllers\VisitorDetailsController;
use App\Http\Controllers\VisitorInfoByDoorController;
use App\Http\Controllers\VisitorReportController;
use App\Http\Controllers\VisitorTypeController;
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

Route::get('/layout', function () {
    return view('layout.main_layout');
});

// web.php
// Route::get('/assign-device', [DeviceLocationAssignController::class, 'create'])->name('assign.device.form');
// Route::post('/assign-device', [DeviceLocationAssignController::class, 'store'])->name('assign.device.store');

Route::prefix('device-assignments')->group(function () {
    Route::get('/', [DeviceLocationAssignController::class, 'index'])->name('device-assignments.index');
    Route::get('/create', [DeviceLocationAssignController::class, 'create'])->name('device-assignments.create'); // ✅ FIXED
    Route::post('/store', [DeviceLocationAssignController::class, 'store'])->name('device-assignments.store');
    Route::get('/edit/{id}', [DeviceLocationAssignController::class, 'edit'])->name('device-assignments.edit');
    Route::put('/update/{id}', [DeviceLocationAssignController::class, 'update'])->name('device-assignments.update');
    Route::delete('/delete/{id}', [DeviceLocationAssignController::class, 'destroy'])->name('device-assignments.destroy');
});


Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::post('/login', [LoginController::class, 'login'])->name('login');


// 1) CALLBACK — NO JAVA AUTH MIDDLEWARE HERE!
Route::get('/java-auth/callback',
    [JavaAuthController::class, 'handleCallback']
)->name('java-auth.callback');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');



Route::post('/dashboard/graph-data', [DashboardController::class, 'getGraphData'])
    ->name('dashboard.graph.data');
Route::get('/dashboard/checkouts-today-modal-data', [DashboardController::class, 'getCheckoutsTodayModalDataAjax'])->name('dashboard.checkouts.today.modal.data');
Route::post('/dashboard/acknowledge-alert', [DashboardController::class, 'acknowledgeAlert']);
Route::post('/dashboard/hide-critical-alert', [DashboardController::class, 'hideCriticalAlert']);
Route::post('/dashboard/get-critical-alert-details', [DashboardController::class, 'getCriticalAlertDetails']);
Route::get('/dashboard/refresh-counts', [DashboardController::class, 'refreshDashboardCounts']);

// Route::get('/redirect-to-angular', [AngularRedirectController::class, 'redirect'])->name('angular.redirect');


Route::prefix('visitor-types')->group(function () {
    Route::get('/', [VisitorTypeController::class, 'index'])->name('visitor-types.index');
    Route::get('/create', [VisitorTypeController::class, 'create'])->name('visitor-types.create');
    Route::post('/store', [VisitorTypeController::class, 'store'])->name('visitor-types.store');
    Route::get('/edit/{id}', [VisitorTypeController::class, 'edit'])->name('visitor-types.edit');
    Route::put('/update/{id}', [VisitorTypeController::class, 'update'])->name('visitor-types.update');
    Route::delete('/delete/{id}', [VisitorTypeController::class, 'destroy'])->name('visitor-types.destroy');
});

Route::get('/clear-session', function() {
    session()->flush();
    return 'Session cleared!';
});



    Route::prefix('reports')->group(function () {
        Route::get('/access-logs', [ReportController::class, 'accessLogs'])->name('reports.access-logs');
        Route::post('/access-logs/data', [ReportController::class, 'getAccessLogsData'])->name('reports.access-logs.data');
        Route::get('/staff-movement/{staffNo}', [ReportController::class, 'getStaffMovement'])->name('reports.staff-movement');
    });

    Route::get('/paths', [PathController::class, 'index'])->name('paths.index');
    Route::post('/paths', [PathController::class, 'store'])->name('paths.store');
    Route::get('/paths/{id}/edit', [PathController::class, 'edit'])->name('paths.edit');
    Route::put('/paths/{id}', [PathController::class, 'update'])->name('paths.update');
    Route::post('/vendor-locations/refresh-hierarchy', [PathController::class, 'refreshLocationHierarchy'])
    ->name('vendor.locations.refresh.hierarchy');


    Route::prefix('visitor-report')->group(function () {
        Route::get('/', [VisitorReportController::class, 'index'])->name('visitor.report');
        Route::get('/export', [VisitorReportController::class, 'export'])->name('visitor.report.export');
    });


    Route::prefix('security-alerts')->group(function () {
        Route::get('/', [SecurityAlertController::class, 'index'])->name('security.alerts.index');
        Route::get('/details/{id}', [SecurityAlertController::class, 'getDetails'])->name('security.alerts.details');

        Route::get('/unauthorized-access', [SecurityAlertController::class, 'unauthorizedAccessDetails'])->name('security.unauthorized.access');
    });


    Route::prefix('visitor-details')->group(function () {
        Route::get('/', [VisitorDetailsController::class, 'index'])->name('visitor-details.index');
        Route::post('/search', [VisitorDetailsController::class, 'search'])->name('visitor-details.search');
        Route::post('/chronology', [VisitorDetailsController::class, 'getVisitorChronology'])->name('visitor-details.chronology');
    });

    Route::prefix('visitor-info-door')->name('visitor-info-door.')->group(function () {
        Route::get('/', [VisitorInfoByDoorController::class, 'index'])->name('index');
        Route::post('/get-visitors', [VisitorInfoByDoorController::class, 'getVisitorsByLocation'])->name('get-visitors');
        Route::get('/get-visitor-details', [VisitorInfoByDoorController::class, 'getVisitorDetails'])->name('get-visitor-details');
        Route::get('/export', [VisitorInfoByDoorController::class, 'exportVisitors'])->name('export');
    });


    Route::prefix('security-alert-priority')->name('security-alert-priority.')->group(function () {
        Route::get('/', [SecurityAlertPriorityController::class, 'index'])->name('index');
        Route::get('/{securityAlertPriority}/edit', [SecurityAlertPriorityController::class, 'edit'])->name('edit');
        Route::put('/{securityAlertPriority}', [SecurityAlertPriorityController::class, 'update'])->name('update');
    });


    Route::post('/logout', [JavaAuthController::class, 'logout'])
    ->name('logout');

    Route::get('/redirect-to-angular/{route}', [AngularRedirectController::class, 'redirect'])
    ->where('route', '.*')
    ->name('angular.redirect');
