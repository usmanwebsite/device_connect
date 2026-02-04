<?php

use App\Http\Controllers\AngularRedirectController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceLocationAssignController;
use App\Http\Controllers\EncryptionController;
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

Route::post('/encrypt', [EncryptionController::class, 'encrypt']);
Route::post('/decrypt', [EncryptionController::class, 'decrypt']);

// web.php
// Route::get('/assign-device', [DeviceLocationAssignController::class, 'create'])->name('assign.device.form');
// Route::post('/assign-device', [DeviceLocationAssignController::class, 'store'])->name('assign.device.store');

Route::prefix('/vms/device-assignments')->group(function () {
    Route::get('/', [DeviceLocationAssignController::class, 'index'])->name('device-assignments.index');
    Route::get('/create', [DeviceLocationAssignController::class, 'create'])->name('device-assignments.create'); // ✅ FIXED
    Route::post('/store', [DeviceLocationAssignController::class, 'store'])->name('device-assignments.store');
    Route::get('/edit/{id}', [DeviceLocationAssignController::class, 'edit'])->name('device-assignments.edit');
    Route::put('/update/{id}', [DeviceLocationAssignController::class, 'update'])->name('device-assignments.update');
    Route::delete('/delete/{id}', [DeviceLocationAssignController::class, 'destroy'])->name('device-assignments.destroy');

    Route::post('/update-ip-range', [DeviceLocationAssignController::class, 'updateIpRange'])->name('device-assignments.update-ip-range');
    Route::get('/get-status', [DeviceLocationAssignController::class, 'getDeviceStatus'])->name('device-assignments.get-status');
});


Route::get('/vms', [DashboardController::class, 'index'])->name('dashboard');
Route::post('/login', [LoginController::class, 'login'])->name('login');

Route::post('/dashboard/get-visitor-details', [DashboardController::class, 'getVisitorDetails']);
Route::get('/dashboard/refresh-on-site', [DashboardController::class, 'refreshOnSiteData']);
Route::get('/dashboard/refresh-denied-access-count', [DashboardController::class, 'refreshDeniedAccessCount']);


Route::get('/dashboard/visitors-on-site-paginated', [DashboardController::class, 'getVisitorsOnSitePaginated']);
Route::get('/dashboard/checkouts-today-paginated', [DashboardController::class, 'getCheckoutsTodayPaginated']);
Route::get('/dashboard/today-appointments-paginated', [DashboardController::class, 'getTodayAppointmentsPaginated']);


// 1) CALLBACK — NO JAVA AUTH MIDDLEWARE HERE!
Route::get('/java-auth/callback',
    [JavaAuthController::class, 'handleCallback']
)->name('java-auth.callback');

Route::get('vms/dashboard', [DashboardController::class, 'index'])->name('dashboard');



Route::post('/vms/dashboard/graph-data', [DashboardController::class, 'getGraphData'])
    ->name('dashboard.graph.data');
Route::get('/vms/dashboard/checkouts-today-modal-data', [DashboardController::class, 'getCheckoutsTodayModalDataAjax'])->name('dashboard.checkouts.today.modal.data');
Route::post('/vms/dashboard/acknowledge-alert', [DashboardController::class, 'acknowledgeAlert']);
Route::post('/vms/dashboard/hide-critical-alert', [DashboardController::class, 'hideCriticalAlert']);
Route::post('/vms/dashboard/get-critical-alert-details', [DashboardController::class, 'getCriticalAlertDetails']);
Route::get('/vms/dashboard/refresh-counts', [DashboardController::class, 'refreshDashboardCounts']);
Route::get('/dashboard/security-alerts-datatable', [DashboardController::class, 'getSecurityAlertsData'])->name('dashboard.security-alerts.datatable');

Route::get('/dashboard/upcoming-appointments-ajax', [DashboardController::class, 'getUpcomingAppointmentsAjax'])->name('dashboard.upcoming.ajax');
Route::get('/dashboard/active-security-alerts-ajax', [DashboardController::class, 'getActiveSecurityAlertsAjax'])->name('dashboard.active.security.alerts.ajax');

// Route::get('/redirect-to-angular', [AngularRedirectController::class, 'redirect'])->name('angular.redirect');


Route::prefix('/vms/visitor-types')->group(function () {
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



    Route::prefix('/vms/reports')->group(function () {
        Route::get('/access-logs', [ReportController::class, 'accessLogs'])->name('reports.access-logs');
        Route::post('/access-logs/data', [ReportController::class, 'getAccessLogsData'])->name('reports.access-logs.data');
        Route::get('/staff-movement/{staffNo}', [ReportController::class, 'getStaffMovement'])->name('reports.staff-movement');

        Route::get('/visitor-details', [ReportController::class, 'getVisitorDetails'])
     ->name('visitor.details');
    });

    Route::get('/vms/paths', [PathController::class, 'index'])->name('paths.index');
    Route::post('/vms/paths', [PathController::class, 'store'])->name('paths.store');
    Route::get('/vms/paths/{id}/edit', [PathController::class, 'edit'])->name('paths.edit');
    Route::put('/vms/paths/{id}', [PathController::class, 'update'])->name('paths.update');
    Route::post('/vms/vendor-locations/refresh-hierarchy', [PathController::class, 'refreshLocationHierarchy'])
    ->name('vendor.locations.refresh.hierarchy');


    Route::prefix('/vms/visitor-report')->group(function () {
        Route::get('/', [VisitorReportController::class, 'index'])->name('visitor.report');
        Route::get('/export', [VisitorReportController::class, 'export'])->name('visitor.report.export');
    });


    Route::prefix('/vms/security-alerts')->group(function () {
        Route::get('/', [SecurityAlertController::class, 'index'])->name('security.alerts.index');
        Route::get('/details/{id}', [SecurityAlertController::class, 'getDetails'])->name('security.alerts.details');

        Route::get('/unauthorized-access', [SecurityAlertController::class, 'unauthorizedAccessDetails'])->name('security.unauthorized.access');
    });


    Route::prefix('/vms/visitor-details')->group(function () {
        Route::get('/', [VisitorDetailsController::class, 'index'])->name('visitor-details.index');
        Route::post('/search', [VisitorDetailsController::class, 'search'])->name('visitor-details.search');
        Route::post('/chronology', [VisitorDetailsController::class, 'getVisitorChronology'])->name('visitor-details.chronology');
    });

    Route::prefix('/vms/visitor-info-door')->name('visitor-info-door.')->group(function () {
        Route::get('/', [VisitorInfoByDoorController::class, 'index'])->name('index');
        Route::post('/get-visitors', [VisitorInfoByDoorController::class, 'getVisitorsByLocation'])->name('get-visitors');
        Route::get('/get-visitor-details', [VisitorInfoByDoorController::class, 'getVisitorDetails'])->name('get-visitor-details');
        Route::get('/export', [VisitorInfoByDoorController::class, 'exportVisitors'])->name('export');
    });


    Route::prefix('/vms/security-alert-priority')->name('security-alert-priority.')->group(function () {
        Route::get('/', [SecurityAlertPriorityController::class, 'index'])->name('index');
        Route::get('/{securityAlertPriority}/edit', [SecurityAlertPriorityController::class, 'edit'])->name('edit');
        Route::put('/{securityAlertPriority}', [SecurityAlertPriorityController::class, 'update'])->name('update');
    });


    Route::post('/vms/logout', [JavaAuthController::class, 'logout'])->name('vms.logout');

    Route::get('/redirect-to-angular/{route}', [AngularRedirectController::class, 'redirect'])
    ->where('route', '.*')
    ->name('angular.redirect');
