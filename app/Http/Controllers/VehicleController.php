<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\Driver;
use App\Support\ListQueryFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vehicles = Vehicle::query();

        if ($request->boolean('available_for_driver')) {
            $currentDriverId = (int) $request->query('driver_id', 0);
            $usedVehicleIds = Driver::query()
                ->when($currentDriverId > 0, function ($query) use ($currentDriverId): void {
                    $query->where('id', '!=', $currentDriverId);
                })
                ->whereNotNull('vehicle_id')
                ->pluck('vehicle_id');

            $vehicles->whereNotIn('id', $usedVehicleIds);
        }

        $search = ListQueryFilters::searchTerm($request);
        $vehicles
            ->when($search, function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('plate_number', 'like', "%{$search}%")
                        ->orWhere('vehicle_type', 'like', "%{$search}%")
                        ->orWhere('brand_model', 'like', "%{$search}%")
                        ->orWhere('capacity', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), function (Builder $query) use ($request): void {
                $query->where('status', $request->query('status'));
            });

        ListQueryFilters::applyDateFilters($vehicles, $request, 'created_at');

        $vehicles = $vehicles->orderBy('plate_number')->get();

        return $this->success('Data kendaraan berhasil ditampilkan.', $vehicles);
    }

    public function store(Request $request): JsonResponse
    {
        $vehicle = Vehicle::create($this->validated($request));

        return $this->success('Data kendaraan berhasil dibuat.', $vehicle, 201);
    }

    public function show(Vehicle $vehicle): JsonResponse
    {
        return $this->success('Detail kendaraan berhasil ditampilkan.', $vehicle);
    }

    public function update(Request $request, Vehicle $vehicle): JsonResponse
    {
        $vehicle->update($this->validated($request, $vehicle));

        return $this->success('Data kendaraan berhasil diperbarui.', $vehicle);
    }

    public function destroy(Vehicle $vehicle): JsonResponse
    {
        $vehicle->delete();

        return $this->success('Data kendaraan berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Vehicle $vehicle = null): array
    {
        return $request->validate([
            'plate_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('vehicles', 'plate_number')->ignore($vehicle?->id),
            ],
            'vehicle_type' => ['required', 'string', 'max:255'],
            'brand_model' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'available', 'maintenance'])],
        ]);
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
