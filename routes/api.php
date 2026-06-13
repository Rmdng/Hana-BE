<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerTrackingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\DriverTripController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ShipmentOrderController;
use App\Http\Controllers\SuratAngkutController;
use App\Http\Controllers\TripLocationController;
use App\Http\Controllers\TripPhotoController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:api')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/drivers', [DriverController::class, 'index'])
        ->middleware('role:admin,operasional,kepala_operasional');

    Route::apiResource('vehicles', VehicleController::class)
        ->only(['index', 'show'])
        ->middleware('role:admin,operasional,kepala_operasional');

    Route::apiResource('vehicles', VehicleController::class)
        ->except(['index', 'show'])
        ->middleware('role:admin');

    Route::apiResource('users', UserManagementController::class)
        ->middleware('role:admin');

    Route::apiResource('customers', CustomerController::class)
        ->only(['index', 'show'])
        ->middleware('role:admin,operasional,kepala_operasional');

    Route::apiResource('customers', CustomerController::class)
        ->except(['index', 'show'])
        ->middleware('role:admin');

    Route::apiResource('shipment-orders', ShipmentOrderController::class)
        ->only(['index', 'show'])
        ->middleware('role:admin,operasional,kepala_operasional,customer');

    Route::get('/shipment-orders/{shipment_order}/print', [ShipmentOrderController::class, 'print'])
        ->middleware('role:admin,operasional,kepala_operasional');

    Route::apiResource('shipment-orders', ShipmentOrderController::class)
        ->except(['index', 'show'])
        ->middleware('role:admin,operasional');

    Route::apiResource('surat-angkuts', SuratAngkutController::class)
        ->only(['index', 'show'])
        ->middleware('role:admin,operasional,kepala_operasional,supir,customer');

    Route::get('/surat-angkuts/{surat_angkut}/pdf', [SuratAngkutController::class, 'pdf'])
        ->middleware('role:admin,operasional,kepala_operasional');

    Route::apiResource('surat-angkuts', SuratAngkutController::class)
        ->except(['index', 'show'])
        ->middleware('role:admin,operasional');

    Route::apiResource('driver-trips', DriverTripController::class)
        ->only(['index', 'show'])
        ->middleware('role:admin,operasional,kepala_operasional,supir,customer');

    Route::apiResource('driver-trips', DriverTripController::class)
        ->only(['store', 'destroy'])
        ->middleware('role:admin,operasional');

    Route::apiResource('driver-trips', DriverTripController::class)
        ->only(['update'])
        ->middleware('role:admin,operasional,supir');

    Route::post('/driver-trips/{driver_trip}/locations', [TripLocationController::class, 'store'])
        ->middleware('role:admin,operasional,supir');

    Route::post('/driver-trips/{driver_trip}/photos', [TripPhotoController::class, 'store'])
        ->middleware('role:admin,operasional,supir');

    Route::prefix('customer')
        ->middleware('role:customer')
        ->group(function (): void {
            Route::get('/shipment-orders', [CustomerTrackingController::class, 'shipmentOrders']);
            Route::get('/shipment-orders/{id}/tracking', [CustomerTrackingController::class, 'tracking'])
                ->whereNumber('id');
        });

    Route::get('/reports/shipments', [ReportController::class, 'shipments'])
        ->middleware('role:admin,operasional,kepala_operasional');

    Route::get('/reports/pdf', [ReportController::class, 'pdf'])
        ->middleware('role:admin,operasional,kepala_operasional');

    Route::get('/reports/excel', [ReportController::class, 'excel'])
        ->middleware('role:admin,operasional,kepala_operasional');

    Route::get('/dashboard/summary', [DashboardController::class, 'summary'])
        ->middleware('role:admin,operasional,kepala_operasional,supir,customer');
});
