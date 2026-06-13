<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Support\DriverAvailability;
use App\Support\ListQueryFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Driver::with(['user', 'vehicle']);
        $search = ListQueryFilters::searchTerm($request);

        $query
            ->when($request->boolean('available_for_order'), function (Builder $query) use ($request): void {
                $exceptShipmentOrderId = $request->integer('shipment_order_id') ?: null;

                $query
                    ->whereHas('user', function (Builder $query): void {
                        $query->where('role', 'supir');
                    })
                    ->whereNotNull('vehicle_id')
                    ->whereDoesntHave('driverTrips', function (Builder $query) use ($exceptShipmentOrderId): void {
                        $query->where('status', '!=', 'selesai')
                            ->when($exceptShipmentOrderId, function (Builder $query) use ($exceptShipmentOrderId): void {
                                $query->where('shipment_order_id', '!=', $exceptShipmentOrderId);
                            });
                    });
            })
            ->when($search, function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('driver_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('license_number', 'like', "%{$search}%")
                        ->orWhereHas('user', function (Builder $query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('vehicle', function (Builder $query) use ($search): void {
                            $query->where('plate_number', 'like', "%{$search}%")
                                ->orWhere('vehicle_type', 'like', "%{$search}%");
                        });
                });
            });

        ListQueryFilters::applyDateFilters($query, $request, 'created_at');

        $drivers = $query->orderBy('driver_name')->get();
        $exceptShipmentOrderId = $request->integer('shipment_order_id') ?: null;

        $drivers->each(function (Driver $driver) use ($exceptShipmentOrderId): void {
            $driver->setAttribute(
                'is_available_for_order',
                DriverAvailability::unavailableReason($driver, $exceptShipmentOrderId) === null
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Data supir berhasil ditampilkan.',
            'data' => $drivers,
        ]);
    }
}
