<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\DriverTrip;
use App\Models\ShipmentOrder;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $data = match ($request->user()->role) {
            'supir' => $this->driverSummary($request),
            'customer' => $this->customerSummary($request),
            default => $this->managementSummary(),
        };

        return response()->json([
            'success' => true,
            'message' => 'Ringkasan dashboard berhasil ditampilkan.',
            'data' => $data,
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function managementSummary(): array
    {
        return [
            'total_customer' => Customer::count(),
            'total_order' => ShipmentOrder::count(),
            'total_order_diajukan' => ShipmentOrder::where('status', 'diajukan')->count(),
            'total_order_diproses' => ShipmentOrder::where('status', 'diproses')->count(),
            'total_order_berjalan' => $this->runningOrdersQuery()->count(),
            'total_order_selesai' => ShipmentOrder::where('status', 'selesai')->count(),
            'total_supir' => Driver::count(),
            'total_kendaraan' => Vehicle::count(),
            'total_perjalanan_aktif' => $this->activeTripsQuery()->count(),
            'total_perjalanan_selesai' => DriverTrip::where('status', 'selesai')->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function driverSummary(Request $request): array
    {
        $baseQuery = DriverTrip::whereHas('driver', function (Builder $query) use ($request): void {
            $query->where('user_id', $request->user()->id);
        });

        return [
            'total_pengiriman' => (clone $baseQuery)->count(),
            'total_pengiriman_aktif' => (clone $baseQuery)->where('status', '!=', 'selesai')->count(),
            'total_pengiriman_selesai' => (clone $baseQuery)->where('status', 'selesai')->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function customerSummary(Request $request): array
    {
        $baseQuery = ShipmentOrder::where(function (Builder $query) use ($request): void {
            $query
                ->whereHas('customer', function (Builder $query) use ($request): void {
                    $query->where('user_id', $request->user()->id);
                })
                ->orWhereHas('receiverCustomer', function (Builder $query) use ($request): void {
                    $query->where('user_id', $request->user()->id);
                });
        });

        return [
            'total_pengiriman' => (clone $baseQuery)->count(),
            'total_pengiriman_berjalan' => (clone $baseQuery)
                ->where(function (Builder $query): void {
                    $query->where('status', 'berjalan')
                        ->orWhereHas('driverTrips', function (Builder $query): void {
                            $query->whereNotIn('status', ['menunggu', 'selesai']);
                        });
                })
                ->count(),
            'total_pengiriman_selesai' => (clone $baseQuery)->where('status', 'selesai')->count(),
        ];
    }

    private function runningOrdersQuery(): Builder
    {
        return ShipmentOrder::where(function (Builder $query): void {
            $query->where('status', 'berjalan')
                ->orWhereHas('driverTrips', function (Builder $query): void {
                    $query->whereNotIn('status', ['menunggu', 'selesai']);
                });
        });
    }

    private function activeTripsQuery(): Builder
    {
        return DriverTrip::where('status', '!=', 'selesai');
    }
}
