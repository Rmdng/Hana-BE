<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDriverTripRequest;
use App\Http\Requests\UpdateDriverTripRequest;
use App\Models\Driver;
use App\Models\DriverTrip;
use App\Services\ShipmentStatusSyncService;
use App\Support\DriverAvailability;
use App\Support\ListQueryFilters;
use App\Support\PublicFileUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverTripController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $this->visibleDriverTrips($request)
            ->with($this->relations());

        $this->applyListFilters($query, $request);

        $driverTrips = $query
            ->latest()
            ->get();

        return $this->success(
            'Data perjalanan supir berhasil ditampilkan.',
            $this->formatDataForRole($request, $this->withProofPhotos($driverTrips))
        );
    }

    public function store(StoreDriverTripRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['status'] = $data['status'] ?? 'menunggu_muat';
        $driver = Driver::with(['user', 'vehicle', 'driverTrips'])->findOrFail($data['driver_id']);

        if ($message = DriverAvailability::unavailableReason($driver, (int) $data['shipment_order_id'])) {
            return $this->unprocessable($message);
        }

        $driverTrip = DriverTrip::create($data);

        return $this->success(
            'Data perjalanan supir berhasil dibuat.',
            $this->withProofPhotos($driverTrip->load($this->relations())),
            201
        );
    }

    public function show(Request $request, DriverTrip $driverTrip): JsonResponse
    {
        if (! $this->canViewDriverTrip($request, $driverTrip)) {
            return $this->forbidden();
        }

        $driverTrip->load($this->relations());

        return $this->success(
            'Detail perjalanan supir berhasil ditampilkan.',
            $this->formatDataForRole($request, $this->withProofPhotos($driverTrip))
        );
    }

    public function update(UpdateDriverTripRequest $request, DriverTrip $driverTrip): JsonResponse
    {
        if (! $this->canUpdateDriverTrip($request, $driverTrip)) {
            return $this->forbidden();
        }

        DB::transaction(function () use ($request, $driverTrip): void {
            $data = $request->validated();
            $this->validatePhotoRequirements($driverTrip, $data['status'] ?? null);
            if (isset($data['driver_id'])) {
                $driver = Driver::with(['user', 'vehicle', 'driverTrips'])->findOrFail($data['driver_id']);
                if ($message = DriverAvailability::unavailableReason(
                    $driver,
                    (int) ($data['shipment_order_id'] ?? $driverTrip->shipment_order_id),
                    $driverTrip->id,
                )) {
                    abort(422, $message);
                }
            }
            $driverTrip->update($data);
            $driverTrip->load(['shipmentOrder', 'suratAngkut']);

            app(ShipmentStatusSyncService::class)->syncFromTrip($driverTrip);
        });

        return $this->success(
            'Data perjalanan supir berhasil diperbarui.',
            $this->withProofPhotos($driverTrip->load($this->relations()))
        );
    }

    public function destroy(DriverTrip $driverTrip): JsonResponse
    {
        $driverTrip->delete();

        return $this->success('Data perjalanan supir berhasil dihapus.');
    }

    private function visibleDriverTrips(Request $request): Builder
    {
        $query = DriverTrip::query();

        if ($request->user()->role === 'supir') {
            $query->whereHas('driver', function (Builder $query) use ($request): void {
                $query->where('user_id', $request->user()->id);
            });
        }

        if ($request->user()->role === 'customer') {
            $query->where(function (Builder $query) use ($request): void {
                $query
                    ->whereHas('shipmentOrder.customer', function (Builder $query) use ($request): void {
                        $query->where('user_id', $request->user()->id);
                    })
                    ->orWhereHas('shipmentOrder.receiverCustomer', function (Builder $query) use ($request): void {
                        $query->where('user_id', $request->user()->id);
                    });
            });
        }

        return $query;
    }

    private function applyListFilters(Builder $query, Request $request): void
    {
        $search = ListQueryFilters::searchTerm($request);

        $query
            ->when($search, function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('notes', 'like', "%{$search}%")
                        ->orWhereHas('shipmentOrder', function (Builder $query) use ($search): void {
                            $query->where('order_number', 'like', "%{$search}%")
                                ->orWhere('item_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('shipmentOrder.customer', function (Builder $query) use ($search): void {
                            $query->where('company_name', 'like', "%{$search}%")
                                ->orWhere('contact_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('shipmentOrder.receiverCustomer', function (Builder $query) use ($search): void {
                            $query->where('company_name', 'like', "%{$search}%")
                                ->orWhere('contact_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('driver', function (Builder $query) use ($search): void {
                            $query->where('driver_name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        })
                        ->orWhereHas('vehicle', function (Builder $query) use ($search): void {
                            $query->where('plate_number', 'like', "%{$search}%")
                                ->orWhere('vehicle_type', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('status'), function (Builder $query) use ($request): void {
                $query->where('status', $request->query('status'));
            })
            ->when($request->filled('driver_id'), function (Builder $query) use ($request): void {
                $query->where('driver_id', $request->query('driver_id'));
            })
            ->when($request->filled('vehicle_id'), function (Builder $query) use ($request): void {
                $query->where('vehicle_id', $request->query('vehicle_id'));
            });

        ListQueryFilters::applyDateFilters($query, $request, 'created_at');
    }

    private function canViewDriverTrip(Request $request, DriverTrip $driverTrip): bool
    {
        if ($request->user()->role === 'supir') {
            return $driverTrip->driver()
                ->where('user_id', $request->user()->id)
                ->exists();
        }

        if ($request->user()->role === 'customer') {
            return $driverTrip->shipmentOrder()
                ->where(function (Builder $query) use ($request): void {
                    $query
                        ->whereHas('customer', function (Builder $query) use ($request): void {
                            $query->where('user_id', $request->user()->id);
                        })
                        ->orWhereHas('receiverCustomer', function (Builder $query) use ($request): void {
                            $query->where('user_id', $request->user()->id);
                        });
                })
                ->exists();
        }

        return true;
    }

    private function canUpdateDriverTrip(Request $request, DriverTrip $driverTrip): bool
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

    private function validatePhotoRequirements(DriverTrip $driverTrip, ?string $nextStatus): void
    {
        if ($nextStatus === 'bukti_muat_diupload') {
            $count = $driverTrip->tripPhotos()->where('photo_type', 'muat')->count();
            abort_if($count < 2, 422, 'Minimal unggah 2 foto bukti muat.');
        }

        if (in_array($nextStatus, ['bukti_bongkar_diupload', 'selesai'], true)) {
            $count = $driverTrip->tripPhotos()->where('photo_type', 'bongkar')->count();
            abort_if($count < 2, 422, 'Minimal unggah 2 foto bukti bongkar.');
        }
    }

    /**
     * @return list<string>
     */
    private function relations(): array
    {
        return [
            'shipmentOrder.customer',
            'shipmentOrder.receiverCustomer',
            'driver.user',
            'vehicle',
            'suratAngkut',
            'latestLocation',
            'tripPhotos',
            'photos',
        ];
    }

    private function withProofPhotos(mixed $data): mixed
    {
        if ($data instanceof DriverTrip) {
            $this->setProofPhotoAttributes($data);

            return $data;
        }

        return $data->each(fn (DriverTrip $driverTrip) => $this->setProofPhotoAttributes($driverTrip));
    }

    private function setProofPhotoAttributes(DriverTrip $driverTrip): void
    {
        if (! $driverTrip->relationLoaded('tripPhotos')) {
            $driverTrip->load('tripPhotos');
        }

        $loadingPhoto = $driverTrip->tripPhotos
            ->filter(fn ($photo): bool => in_array($photo->photo_type, ['muat', 'bukti_muat', 'loading'], true))
            ->sortByDesc(fn ($photo) => $photo->uploaded_at ?? $photo->created_at)
            ->first();

        $unloadingPhoto = $driverTrip->tripPhotos
            ->filter(fn ($photo): bool => in_array($photo->photo_type, ['bongkar', 'bukti_bongkar', 'unloading'], true))
            ->sortByDesc(fn ($photo) => $photo->uploaded_at ?? $photo->created_at)
            ->first();

        $driverTrip->setAttribute('photo_muat', $loadingPhoto);
        $driverTrip->setAttribute('photo_bongkar', $unloadingPhoto);
        $driverTrip->setAttribute('bukti_muat_url', PublicFileUrl::tripPhoto($loadingPhoto?->photo_path));
        $driverTrip->setAttribute('bukti_bongkar_url', PublicFileUrl::tripPhoto($unloadingPhoto?->photo_path));
        $driverTrip->setAttribute('loading_photo_url', PublicFileUrl::tripPhoto($loadingPhoto?->photo_path));
        $driverTrip->setAttribute('unloading_photo_url', PublicFileUrl::tripPhoto($unloadingPhoto?->photo_path));
        $driverTrip->setAttribute('bukti_muat_uploaded_at', $loadingPhoto?->uploaded_at ?? $loadingPhoto?->created_at);
        $driverTrip->setAttribute('bukti_bongkar_uploaded_at', $unloadingPhoto?->uploaded_at ?? $unloadingPhoto?->created_at);
        $driverTrip->setAttribute('loading_photo_uploaded_at', $loadingPhoto?->uploaded_at ?? $loadingPhoto?->created_at);
        $driverTrip->setAttribute('unloading_photo_uploaded_at', $unloadingPhoto?->uploaded_at ?? $unloadingPhoto?->created_at);
    }

    private function formatDataForRole(Request $request, mixed $data): mixed
    {
        if ($request->user()->role !== 'customer') {
            return $data;
        }

        if ($data instanceof DriverTrip) {
            return $this->formatCustomerData($data);
        }

        return $data->map(fn (DriverTrip $driverTrip): array => $this->formatCustomerData($driverTrip));
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCustomerData(DriverTrip $driverTrip): array
    {
        return [
            'id' => $driverTrip->id,
            'shipment_order_id' => $driverTrip->shipment_order_id,
            'status' => $driverTrip->status,
            'latest_location' => $driverTrip->latestLocation,
        ];
    }

    private function success(string $message, mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak untuk data ini.',
            'data' => null,
        ], 403);
    }

    private function unprocessable(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
        ], 422);
    }
}
