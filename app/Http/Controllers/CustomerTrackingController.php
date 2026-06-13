<?php

namespace App\Http\Controllers;

use App\Models\ShipmentOrder;
use App\Models\TripLocation;
use App\Support\ListQueryFilters;
use App\Support\PublicFileUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerTrackingController extends Controller
{
    public function shipmentOrders(Request $request): JsonResponse
    {
        $query = ShipmentOrder::where(function ($query) use ($request): void {
            $query
                ->whereHas('customer', function ($query) use ($request): void {
                    $query->where('user_id', $request->user()->id);
                })
                ->orWhereHas('receiverCustomer', function ($query) use ($request): void {
                    $query->where('user_id', $request->user()->id);
                });
        })
            ->with(['customer', 'receiverCustomer']);

        $this->applyOrderFilters($query, $request);

        $orders = $query
            ->latest()
            ->get();

        return $this->success('Data shipment order customer berhasil ditampilkan.', $orders);
    }

    private function applyOrderFilters(Builder $query, Request $request): void
    {
        $search = ListQueryFilters::searchTerm($request);

        $query
            ->when($search, function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('order_number', 'like', "%{$search}%")
                        ->orWhere('item_name', 'like', "%{$search}%")
                        ->orWhereHas('customer', function (Builder $query) use ($search): void {
                            $query->where('company_name', 'like', "%{$search}%")
                                ->orWhere('contact_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('receiverCustomer', function (Builder $query) use ($search): void {
                            $query->where('company_name', 'like', "%{$search}%")
                                ->orWhere('contact_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('status'), function (Builder $query) use ($request): void {
                $query->where('status', $request->query('status'));
            });

        ListQueryFilters::applyDateFilters($query, $request, 'order_date');
    }

    public function tracking(Request $request, int $id): JsonResponse
    {
        $shipmentOrder = $this->customerShipmentOrder($request, $id);

        if (! $shipmentOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Shipment order tidak ditemukan.',
                'data' => null,
            ], 404);
        }

        $suratAngkut = $shipmentOrder->suratAngkuts()
            ->with(['driver', 'vehicle'])
            ->latest()
            ->first();

        $driverTrip = $shipmentOrder->driverTrips()
            ->with(['driver', 'suratAngkut', 'tripPhotos'])
            ->latest()
            ->first();

        $latestLocation = $driverTrip
            ? TripLocation::where('driver_trip_id', $driverTrip->id)
                ->orderByDesc('recorded_at')
                ->orderByDesc('created_at')
                ->first()
            : null;

        $loadingPhoto = $driverTrip?->tripPhotos
            ?->filter(fn ($photo): bool => in_array($photo->photo_type, ['muat', 'bukti_muat', 'loading'], true))
            ->sortByDesc('uploaded_at')
            ->first();
        $unloadingPhoto = $driverTrip?->tripPhotos
            ?->filter(fn ($photo): bool => in_array($photo->photo_type, ['bongkar', 'bukti_bongkar', 'unloading'], true))
            ->sortByDesc('uploaded_at')
            ->first();

        $data = [
            'no_order' => $shipmentOrder->order_number,
            'shipment_order' => $shipmentOrder,
            'status_order' => $shipmentOrder->status,
            'surat_angkut' => $suratAngkut,
            'driver_trip' => $driverTrip,
            'latest_location' => $latestLocation,
            'trip_photos' => $driverTrip?->tripPhotos,
            'photo_muat' => $loadingPhoto,
            'photo_bongkar' => $unloadingPhoto,
            'bukti_muat_url' => PublicFileUrl::tripPhoto($loadingPhoto?->photo_path),
            'bukti_bongkar_url' => PublicFileUrl::tripPhoto($unloadingPhoto?->photo_path),
            'loading_photo_url' => PublicFileUrl::tripPhoto($loadingPhoto?->photo_path),
            'unloading_photo_url' => PublicFileUrl::tripPhoto($unloadingPhoto?->photo_path),
            'loading_photo_uploaded_at' => $loadingPhoto?->uploaded_at,
            'unloading_photo_uploaded_at' => $unloadingPhoto?->uploaded_at,
            'latitude' => $latestLocation?->latitude,
            'longitude' => $latestLocation?->longitude,
            'address' => $latestLocation?->address,
            'status' => $latestLocation?->status ?? $driverTrip?->status,
            'status_perjalanan' => $latestLocation?->status ?? $driverTrip?->status,
            'recorded_at' => $latestLocation?->recorded_at,
            'last_location_updated_at' => $latestLocation?->recorded_at,
            'google_maps_url' => $this->googleMapsUrl($latestLocation?->latitude, $latestLocation?->longitude),
        ];

        if (! $latestLocation) {
            return response()->json([
                'success' => false,
                'message' => 'Lokasi supir belum tersedia.',
                'data' => $data,
            ]);
        }

        return $this->success('Data tracking shipment order berhasil ditampilkan.', $data);
    }

    private function customerShipmentOrder(Request $request, int $id): ?ShipmentOrder
    {
        return ShipmentOrder::with(['customer', 'receiverCustomer'])
            ->where('id', $id)
            ->where(function ($query) use ($request): void {
                $query
                    ->whereHas('customer', function ($query) use ($request): void {
                        $query->where('user_id', $request->user()->id);
                    })
                    ->orWhereHas('receiverCustomer', function ($query) use ($request): void {
                        $query->where('user_id', $request->user()->id);
                    });
            })
            ->first();
    }

    private function googleMapsUrl(mixed $latitude, mixed $longitude): ?string
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        return "https://www.google.com/maps/search/?api=1&query={$latitude},{$longitude}";
    }

    private function success(string $message, mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}
