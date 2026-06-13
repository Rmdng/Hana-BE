<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShipmentOrderRequest;
use App\Http\Requests\UpdateShipmentOrderRequest;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\DriverTrip;
use App\Models\ShipmentOrder;
use App\Models\SuratAngkut;
use App\Support\DriverAvailability;
use App\Support\ListQueryFilters;
use App\Support\PublicFileUrl;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ShipmentOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $this->visibleOrders($request)
            ->with(['customer', 'receiverCustomer', 'updatedBy']);

        $this->applyListFilters($query, $request);

        $orders = $query
            ->latest()
            ->get();

        return $this->success('Data shipment order berhasil ditampilkan.', $orders);
    }

    public function store(StoreShipmentOrderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $driverId = (int) $data['driver_id'];
        unset($data['driver_id']);

        $senderCustomer = Customer::findOrFail($data['customer_id']);
        $pickupAddress = $this->customerAddress($senderCustomer);
        if ($pickupAddress === null) {
            return $this->customerAddressRequired('pemesan');
        }

        $receiverCustomer = Customer::findOrFail(
            $data['receiver_customer_id'] ?? $data['customer_id']
        );
        $destinationAddress = $this->customerAddress($receiverCustomer);
        if ($destinationAddress === null) {
            return $this->customerAddressRequired('penerima');
        }

        $data['pickup_address'] = $pickupAddress;
        $data['destination_address'] = $destinationAddress;
        $data['receiver_customer_id'] = $receiverCustomer->id;
        $driver = Driver::with(['user', 'vehicle', 'driverTrips'])->findOrFail($driverId);
        $data['vehicle_type'] = $data['vehicle_type']
            ?? $driver->vehicle?->vehicle_type
            ?? 'Belum ditentukan';
        $data['order_date'] = $data['order_date'] ?? now()->toDateString();
        $data['status'] = 'menunggu_muat';
        $orderDate = Carbon::parse($data['order_date']);

        if ($message = DriverAvailability::unavailableReason($driver)) {
            return $this->unprocessable($message);
        }

        $shipmentOrder = DB::transaction(function () use ($data, $driver, $orderDate): ShipmentOrder {
            $data['order_number'] = $this->nextOrderNumber($orderDate);
            $shipmentOrder = ShipmentOrder::create($data);

            $suratAngkut = SuratAngkut::create([
                'shipment_order_id' => $shipmentOrder->id,
                'driver_id' => $driver->id,
                'vehicle_id' => $driver->vehicle->id,
                'surat_number' => $this->nextSuratAngkutNumber($orderDate),
                'issue_date' => $shipmentOrder->order_date,
                'pickup_address' => $shipmentOrder->pickup_address,
                'destination_address' => $shipmentOrder->destination_address,
                'status' => 'dibuat',
            ]);

            DriverTrip::create([
                'shipment_order_id' => $shipmentOrder->id,
                'driver_id' => $driver->id,
                'vehicle_id' => $driver->vehicle->id,
                'surat_angkut_id' => $suratAngkut->id,
                'start_time' => null,
                'finish_time' => null,
                'status' => 'menunggu_muat',
                'notes' => 'Trip dibuat otomatis dari shipment order '.$shipmentOrder->order_number,
            ]);

            return $shipmentOrder;
        });

        return $this->success(
            'Order berhasil dibuat. Surat angkut dan trip berhasil dibuat otomatis.',
            $this->withProofPhotos($shipmentOrder->load([
                'customer',
                'receiverCustomer',
                'updatedBy',
                'suratAngkuts.driver',
                'suratAngkuts.vehicle',
                'driverTrips.driver',
                'driverTrips.vehicle',
                'driverTrips.tripPhotos',
            ])),
            201
        );
    }

    public function show(Request $request, ShipmentOrder $shipmentOrder): JsonResponse
    {
        if (! $this->canViewOrder($request, $shipmentOrder)) {
            return $this->forbidden();
        }

        return $this->success(
            'Detail shipment order berhasil ditampilkan.',
            $this->withProofPhotos($shipmentOrder->load([
                'customer',
                'receiverCustomer',
                'updatedBy',
                'suratAngkuts.driver',
                'suratAngkuts.vehicle',
                'driverTrips.driver',
                'driverTrips.vehicle',
                'driverTrips.tripPhotos',
            ]))
        );
    }

    public function print(Request $request, ShipmentOrder $shipmentOrder): Response|JsonResponse
    {
        if (! in_array($request->user()->role, ['admin', 'operasional', 'kepala_operasional'], true)) {
            return $this->forbidden();
        }

        $shipmentOrder->load([
            'customer',
            'receiverCustomer',
            'suratAngkuts.driver',
            'suratAngkuts.vehicle',
            'driverTrips.driver',
            'driverTrips.vehicle',
        ]);

        $driverTrip = $shipmentOrder->driverTrips->sortByDesc('id')->first();
        $suratAngkut = $shipmentOrder->suratAngkuts->sortByDesc('id')->first();
        $driver = $driverTrip?->driver ?? $suratAngkut?->driver;
        $vehicle = $driverTrip?->vehicle ?? $suratAngkut?->vehicle;
        $filename = 'surat-order-'.str_replace(['/', '\\', ' '], '-', $shipmentOrder->order_number).'.pdf';

        $pdf = Pdf::loadView('pdf.surat-order', [
            'shipmentOrder' => $shipmentOrder,
            'sender' => $shipmentOrder->customer,
            'receiver' => $shipmentOrder->receiverCustomer,
            'suratAngkut' => $suratAngkut,
            'driver' => $driver,
            'vehicle' => $vehicle,
        ])->setPaper([0, 0, 684, 792], 'portrait');

        return $pdf->download($filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function update(UpdateShipmentOrderRequest $request, ShipmentOrder $shipmentOrder): JsonResponse
    {
        $data = $request->validated();
        $driver = isset($data['driver_id']) ? Driver::with(['user', 'vehicle', 'driverTrips'])->find($data['driver_id']) : null;
        $vehicleId = $data['vehicle_id'] ?? $driver?->vehicle_id;
        $senderCustomer = Customer::findOrFail($data['customer_id']);
        $pickupAddress = $this->customerAddress($senderCustomer);
        if ($pickupAddress === null) {
            return $this->customerAddressRequired('pemesan');
        }

        $receiverCustomer = Customer::findOrFail(
            $data['receiver_customer_id'] ?? $data['customer_id']
        );
        $destinationAddress = $this->customerAddress($receiverCustomer);

        if ($destinationAddress === null) {
            return $this->customerAddressRequired('penerima');
        }

        $data['pickup_address'] = $pickupAddress;
        $data['destination_address'] = $destinationAddress;
        $data['receiver_customer_id'] = $receiverCustomer->id;
        $data['updated_by'] = $request->user()->id;

        unset($data['driver_id'], $data['vehicle_id']);

        if ($driver?->vehicle && empty($data['vehicle_type'])) {
            $data['vehicle_type'] = $driver->vehicle->vehicle_type;
        }

        if ($driver) {
            $message = DriverAvailability::unavailableReason($driver, $shipmentOrder->id);
            if ($message) {
                return $this->unprocessable($message);
            }
        }

        DB::transaction(function () use ($shipmentOrder, $data, $driver, $vehicleId): void {
            $status = $shipmentOrder->status;
            $shipmentOrder->update($data + ['status' => $status]);
            $shipmentOrder->refresh();

            $suratAngkutData = [
                'pickup_address' => $shipmentOrder->pickup_address,
                'destination_address' => $shipmentOrder->destination_address,
                'issue_date' => $shipmentOrder->order_date,
                'status' => $status,
            ];
            if ($driver) {
                $suratAngkutData['driver_id'] = $driver->id;
            }
            if ($vehicleId) {
                $suratAngkutData['vehicle_id'] = $vehicleId;
            }
            $shipmentOrder->suratAngkuts()->latest()->first()?->update($suratAngkutData);

            $driverTripData = [
                'status' => $status,
            ];
            if ($driver) {
                $driverTripData['driver_id'] = $driver->id;
            }
            if ($vehicleId) {
                $driverTripData['vehicle_id'] = $vehicleId;
            }
            $shipmentOrder->driverTrips()->latest()->first()?->update($driverTripData);

        });

        return $this->success(
            'Data shipment order berhasil diperbarui.',
            $this->withProofPhotos($shipmentOrder->load([
                'customer',
                'receiverCustomer',
                'updatedBy',
                'suratAngkuts.driver',
                'suratAngkuts.vehicle',
                'driverTrips.driver',
                'driverTrips.vehicle',
                'driverTrips.tripPhotos',
            ]))
        );
    }

    public function destroy(ShipmentOrder $shipmentOrder): JsonResponse
    {
        $shipmentOrder->delete();

        return $this->success('Data shipment order berhasil dihapus.');
    }

    private function visibleOrders(Request $request): Builder
    {
        $query = ShipmentOrder::query();

        if ($request->user()->role === 'customer') {
            $query->where(function (Builder $query) use ($request): void {
                $query
                    ->whereHas('customer', function (Builder $query) use ($request): void {
                        $query->where('user_id', $request->user()->id);
                    })
                    ->orWhereHas('receiverCustomer', function (Builder $query) use ($request): void {
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
                        ->where('order_number', 'like', "%{$search}%")
                        ->orWhere('item_name', 'like', "%{$search}%")
                        ->orWhere('pickup_address', 'like', "%{$search}%")
                        ->orWhere('destination_address', 'like', "%{$search}%")
                        ->orWhere('vehicle_type', 'like', "%{$search}%")
                        ->orWhereHas('customer', function (Builder $query) use ($search): void {
                            $query->where('company_name', 'like', "%{$search}%")
                                ->orWhere('contact_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        })
                        ->orWhereHas('receiverCustomer', function (Builder $query) use ($search): void {
                            $query->where('company_name', 'like', "%{$search}%")
                                ->orWhere('contact_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        })
                        ->orWhereHas('driverTrips.driver', function (Builder $query) use ($search): void {
                            $query->where('driver_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('driverTrips.vehicle', function (Builder $query) use ($search): void {
                            $query->where('plate_number', 'like', "%{$search}%")
                                ->orWhere('vehicle_type', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('status'), function (Builder $query) use ($request): void {
                $query->where('status', $request->query('status'));
            })
            ->when($request->filled('customer_id'), function (Builder $query) use ($request): void {
                $query->where(function (Builder $query) use ($request): void {
                    $query->where('customer_id', $request->query('customer_id'))
                        ->orWhere('receiver_customer_id', $request->query('customer_id'));
                });
            })
            ->when($request->filled('driver_id'), function (Builder $query) use ($request): void {
                $query->whereHas('driverTrips', function (Builder $query) use ($request): void {
                    $query->where('driver_id', $request->query('driver_id'));
                });
            })
            ->when($request->filled('vehicle_id'), function (Builder $query) use ($request): void {
                $query->whereHas('driverTrips', function (Builder $query) use ($request): void {
                    $query->where('vehicle_id', $request->query('vehicle_id'));
                });
            });

        ListQueryFilters::applyDateFilters($query, $request, 'order_date');
    }

    private function canViewOrder(Request $request, ShipmentOrder $shipmentOrder): bool
    {
        if ($request->user()->role !== 'customer') {
            return true;
        }

        return $shipmentOrder->customer()
            ->where('user_id', $request->user()->id)
            ->exists()
            || $shipmentOrder->receiverCustomer()
                ->where('user_id', $request->user()->id)
                ->exists();
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

    private function customerAddress(Customer $customer): ?string
    {
        $address = trim((string) $customer->address);

        return $address === '' ? null : $address;
    }

    private function customerAddressRequired(string $type): JsonResponse
    {
        $label = $type === 'pemesan' ? 'pemesan' : 'penerima';

        return response()->json([
            'success' => false,
            'message' => "Alamat customer {$label} belum tersedia. Lengkapi data customer melalui admin.",
            'data' => null,
        ], 422);
    }

    private function withProofPhotos(ShipmentOrder $shipmentOrder): ShipmentOrder
    {
        $driverTrip = $shipmentOrder->driverTrips->sortByDesc('id')->first();
        $loadingPhoto = $driverTrip?->tripPhotos
            ?->filter(fn ($photo): bool => in_array($photo->photo_type, ['muat', 'bukti_muat', 'loading'], true))
            ->sortByDesc('uploaded_at')
            ->first();
        $unloadingPhoto = $driverTrip?->tripPhotos
            ?->filter(fn ($photo): bool => in_array($photo->photo_type, ['bongkar', 'bukti_bongkar', 'unloading'], true))
            ->sortByDesc('uploaded_at')
            ->first();

        $shipmentOrder->setAttribute('photo_muat', $loadingPhoto);
        $shipmentOrder->setAttribute('photo_bongkar', $unloadingPhoto);
        $shipmentOrder->setAttribute('bukti_muat_url', PublicFileUrl::tripPhoto($loadingPhoto?->photo_path));
        $shipmentOrder->setAttribute('bukti_bongkar_url', PublicFileUrl::tripPhoto($unloadingPhoto?->photo_path));
        $shipmentOrder->setAttribute('loading_photo_url', PublicFileUrl::tripPhoto($loadingPhoto?->photo_path));
        $shipmentOrder->setAttribute('unloading_photo_url', PublicFileUrl::tripPhoto($unloadingPhoto?->photo_path));
        $shipmentOrder->setAttribute('loading_photo_uploaded_at', $loadingPhoto?->uploaded_at);
        $shipmentOrder->setAttribute('unloading_photo_uploaded_at', $unloadingPhoto?->uploaded_at);

        return $shipmentOrder;
    }

    private function nextOrderNumber(Carbon $date): string
    {
        $sequence = ShipmentOrder::whereDate('order_date', $date->toDateString())->count() + 1;

        return 'ORD-'.$date->format('Ymd').'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }


    private function nextSuratAngkutNumber(Carbon $date): string
    {
        $sequence = SuratAngkut::whereDate('issue_date', $date->toDateString())->count() + 1;

        return 'SA-'.$date->format('Ymd').'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
