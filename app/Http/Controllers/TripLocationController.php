<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTripLocationRequest;
use App\Models\DriverTrip;
use App\Services\ShipmentStatusSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TripLocationController extends Controller
{
    public function store(StoreTripLocationRequest $request, DriverTrip $driverTrip): JsonResponse
    {
        if (! $this->canUpdateTrip($request, $driverTrip)) {
            return $this->forbidden();
        }

        $data = $request->validated();
        unset($data['driver_trip_id']);
        $data['recorded_at'] = $data['recorded_at'] ?? now();

        if (
            $data['status'] === 'dalam_perjalanan'
            && in_array($driverTrip->status, ['sampai_tempat_bongkar', 'bukti_bongkar_diupload', 'selesai'], true)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Tracking perjalanan sudah berhenti.',
                'data' => null,
            ], 409);
        }

        $location = DB::transaction(function () use ($driverTrip, $data) {
            $location = $driverTrip->tripLocations()->create($data);
            $driverTrip->update(['status' => $data['status']]);
            $driverTrip->load(['shipmentOrder', 'suratAngkut']);

            app(ShipmentStatusSyncService::class)->syncFromTrip($driverTrip);

            return $location;
        });

        return response()->json([
            'success' => true,
            'message' => 'Lokasi perjalanan berhasil disimpan.',
            'data' => $location,
        ], 201);
    }

    private function canUpdateTrip(Request $request, DriverTrip $driverTrip): bool
    {
        if (in_array($request->user()->role, ['admin', 'operasional'], true)) {
            return true;
        }

        if ($request->user()->role === 'supir') {
            return $driverTrip->driver()
                ->where('user_id', $request->user()->id)
                ->exists();
        }

        return false;
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak untuk data ini.',
            'data' => null,
        ], 403);
    }
}
