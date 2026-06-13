<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTripPhotoRequest;
use App\Models\DriverTrip;
use App\Support\PublicFileUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TripPhotoController extends Controller
{
    public function store(StoreTripPhotoRequest $request, DriverTrip $driverTrip): JsonResponse
    {
        if (! $this->canUpdateTrip($request, $driverTrip)) {
            return $this->forbidden();
        }

        $data = $request->validated();
        if (in_array($data['photo_type'], ['muat', 'bongkar'], true)) {
            $count = $driverTrip->tripPhotos()
                ->where('photo_type', $data['photo_type'])
                ->count();

            if ($count >= 3) {
                return response()->json([
                    'success' => false,
                    'message' => $data['photo_type'] === 'bongkar'
                        ? 'Maksimal unggah 3 foto bukti bongkar.'
                        : 'Maksimal unggah 3 foto bukti muat.',
                    'data' => null,
                ], 422);
            }
        }

        $photo = DB::transaction(function () use ($request, $driverTrip, $data) {
            $path = $request->file('photo')->store('trip_photos', 'public');

            $photo = $driverTrip->tripPhotos()->create([
                'photo_type' => $data['photo_type'],
                'photo_path' => $path,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'notes' => $data['notes'] ?? null,
                'uploaded_at' => $data['uploaded_at'] ?? now(),
            ]);

            $driverTrip->load(['shipmentOrder', 'suratAngkut']);

            return $photo;
        });

        return response()->json([
            'success' => true,
            'message' => 'Foto perjalanan berhasil diunggah.',
            'data' => $this->withPublicUrl($photo),
        ], 201);
    }

    private function withPublicUrl(mixed $photo): mixed
    {
        $photo->setAttribute('photo_url', PublicFileUrl::tripPhoto($photo->photo_path));

        return $photo;
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
