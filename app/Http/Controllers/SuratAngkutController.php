<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSuratAngkutRequest;
use App\Http\Requests\UpdateSuratAngkutRequest;
use App\Models\Driver;
use App\Models\DriverTrip;
use App\Models\ShipmentOrder;
use App\Models\SuratAngkut;
use App\Support\DriverAvailability;
use App\Support\ListQueryFilters;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuratAngkutController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $this->visibleSuratAngkuts($request)
            ->with(['shipmentOrder.customer', 'shipmentOrder.receiverCustomer', 'driver', 'vehicle']);

        $this->applyListFilters($query, $request);

        $suratAngkuts = $query
            ->latest()
            ->get();

        return $this->success('Data surat angkut berhasil ditampilkan.', $suratAngkuts);
    }

    public function store(StoreSuratAngkutRequest $request): JsonResponse
    {
        $data = $request->validated();
        $shipmentOrder = ShipmentOrder::findOrFail($data['shipment_order_id']);
        $driver = Driver::with(['user', 'vehicle', 'driverTrips'])->findOrFail($data['driver_id']);

        if ($message = DriverAvailability::unavailableReason($driver, $shipmentOrder->id)) {
            return $this->unprocessable($message);
        }

        $data['vehicle_id'] = $data['vehicle_id'] ?? $driver->vehicle->id;
        $data['surat_number'] = $data['surat_number'] ?? $this->nextNumber();
        $data['pickup_address'] = $data['pickup_address'] ?? $shipmentOrder->pickup_address;
        $data['destination_address'] = $data['destination_address'] ?? $shipmentOrder->destination_address;
        $data['status'] = $data['status'] ?? 'diterbitkan';

        $suratAngkut = SuratAngkut::create($data);

        DriverTrip::where('shipment_order_id', $suratAngkut->shipment_order_id)
            ->where('driver_id', $suratAngkut->driver_id)
            ->whereNull('surat_angkut_id')
            ->latest()
            ->first()
            ?->update(['surat_angkut_id' => $suratAngkut->id]);

        return $this->success(
            'Data surat angkut berhasil dibuat.',
            $suratAngkut->load(['shipmentOrder.customer', 'shipmentOrder.receiverCustomer', 'driver', 'vehicle']),
            201
        );
    }

    public function show(Request $request, SuratAngkut $suratAngkut): JsonResponse
    {
        if (! $this->canViewSuratAngkut($request, $suratAngkut)) {
            return $this->forbidden();
        }

        return $this->success(
            'Detail surat angkut berhasil ditampilkan.',
            $suratAngkut->load(['shipmentOrder.customer', 'shipmentOrder.receiverCustomer', 'driver', 'vehicle'])
        );
    }

    public function pdf(Request $request, SuratAngkut $suratAngkut): Response|JsonResponse
    {
        if (! in_array($request->user()->role, ['admin', 'operasional', 'kepala_operasional'], true)) {
            return $this->forbidden();
        }

        $suratAngkut->load([
            'shipmentOrder.customer',
            'shipmentOrder.receiverCustomer',
            'driver',
            'vehicle',
        ]);

        if (! $suratAngkut->shipmentOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Data shipment order untuk surat angkut ini tidak ditemukan.',
                'data' => null,
            ], 404);
        }

        $filename = 'surat-angkut-'.str_replace(['/', '\\', ' '], '-', $suratAngkut->surat_number).'.pdf';

        $pdf = Pdf::loadView('pdf.surat-angkut', [
            'suratAngkut' => $suratAngkut,
            'shipmentOrder' => $suratAngkut->shipmentOrder,
            'sender' => $suratAngkut->shipmentOrder->customer,
            'receiver' => $suratAngkut->shipmentOrder->receiverCustomer,
            'driver' => $suratAngkut->driver,
            'vehicle' => $suratAngkut->vehicle,
        ])->setPaper([0, 0, 684, 792], 'portrait');

        return $pdf->download($filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function update(UpdateSuratAngkutRequest $request, SuratAngkut $suratAngkut): JsonResponse
    {
        $data = $request->validated();
        $data['status'] = $data['status'] ?? 'diterbitkan';
        $driver = Driver::with(['user', 'vehicle', 'driverTrips'])->findOrFail($data['driver_id']);

        if ($message = DriverAvailability::unavailableReason(
            $driver,
            (int) $data['shipment_order_id'],
            $suratAngkut->driverTrips()->latest()->value('id'),
        )) {
            return $this->unprocessable($message);
        }

        $suratAngkut->update($data);

        return $this->success(
            'Data surat angkut berhasil diperbarui.',
            $suratAngkut->load(['shipmentOrder.customer', 'shipmentOrder.receiverCustomer', 'driver', 'vehicle'])
        );
    }

    public function destroy(SuratAngkut $suratAngkut): JsonResponse
    {
        $suratAngkut->delete();

        return $this->success('Data surat angkut berhasil dihapus.');
    }

    private function visibleSuratAngkuts(Request $request): Builder
    {
        $query = SuratAngkut::query();

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
                        ->where('surat_number', 'like', "%{$search}%")
                        ->orWhere('pickup_address', 'like', "%{$search}%")
                        ->orWhere('destination_address', 'like', "%{$search}%")
                        ->orWhereHas('shipmentOrder', function (Builder $query) use ($search): void {
                            $query->where('order_number', 'like', "%{$search}%");
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
                            $query->where('driver_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('vehicle', function (Builder $query) use ($search): void {
                            $query->where('plate_number', 'like', "%{$search}%")
                                ->orWhere('vehicle_type', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('status'), function (Builder $query) use ($request): void {
                $query->where('status', $request->query('status'));
            });

        ListQueryFilters::applyDateFilters($query, $request, 'issue_date');
    }

    private function canViewSuratAngkut(Request $request, SuratAngkut $suratAngkut): bool
    {
        if ($request->user()->role === 'supir') {
            return $suratAngkut->driver()
                ->where('user_id', $request->user()->id)
                ->exists();
        }

        if ($request->user()->role === 'customer') {
            return $suratAngkut->shipmentOrder()
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

    private function nextNumber(): string
    {
        return 'SA-'.now()->format('YmdHis').'-'.random_int(100, 999);
    }
}
