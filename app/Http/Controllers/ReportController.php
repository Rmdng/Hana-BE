<?php

namespace App\Http\Controllers;

use App\Exports\ShipmentReportExport;
use App\Models\ShipmentOrder;
use App\Support\ListQueryFilters;
use App\Support\PublicFileUrl;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function shipments(Request $request): JsonResponse
    {
        $shipments = $this->reportData($request);

        return response()->json([
            'success' => true,
            'message' => 'Laporan pengiriman berhasil ditampilkan.',
            'data' => $shipments,
        ]);
    }

    public function pdf(Request $request): Response|JsonResponse
    {
        $shipments = $this->reportData($request);

        if ($shipments->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data laporan untuk dicetak.',
                'data' => null,
            ], 422);
        }

        $summary = [
            'total' => $shipments->count(),
            'selesai' => $shipments->where('order_status', 'selesai')->count(),
            'dalam_perjalanan' => $shipments->where('order_status', 'dalam_perjalanan')->count(),
            'menunggu_muat' => $shipments->where('order_status', 'menunggu_muat')->count(),
            'total_customer' => $shipments->pluck('customer_name')->merge($shipments->pluck('receiver_customer_name'))->filter()->unique()->count(),
            'total_supir' => $shipments->pluck('driver_name')->filter()->unique()->count(),
        ];

        $pdf = Pdf::loadView('pdf.laporan-pengiriman', [
            'shipments' => $shipments,
            'summary' => $summary,
            'filters' => $this->filterLabels($request),
            'printedAt' => now(),
            'printedBy' => $request->user(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download($this->reportFilename($request), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function excel(Request $request): BinaryFileResponse|JsonResponse
    {
        $shipments = $this->reportData($request);

        if ($shipments->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data laporan untuk diexport.',
                'data' => null,
            ], 422);
        }

        return Excel::download(
            new ShipmentReportExport(
                $shipments,
                $this->filterLabels($request),
                $request->user()->role
            ),
            $this->excelFilename($request)
        );
    }

    private function reportData(Request $request): Collection
    {
        $query = ShipmentOrder::query()
            ->with([
                'customer',
                'receiverCustomer',
                'suratAngkuts.driver',
                'suratAngkuts.vehicle',
                'updatedBy',
                'driverTrips.driver',
                'driverTrips.vehicle',
                'driverTrips.tripPhotos',
            ]);

        $this->applyFilters($query, $request);

        return $query->orderByDesc('order_date')
            ->get()
            ->map(function (ShipmentOrder $shipmentOrder): array {
                $suratAngkut = $shipmentOrder->suratAngkuts->sortByDesc('id')->first();
                $driverTrip = $shipmentOrder->driverTrips->sortByDesc('id')->first();
                $loadingPhoto = $driverTrip?->tripPhotos
                    ?->filter(fn ($photo): bool => in_array($photo->photo_type, ['muat', 'bukti_muat', 'loading'], true))
                    ->sortByDesc('uploaded_at')
                    ->first();
                $unloadingPhoto = $driverTrip?->tripPhotos
                    ?->filter(fn ($photo): bool => in_array($photo->photo_type, ['bongkar', 'bukti_bongkar', 'unloading'], true))
                    ->sortByDesc('uploaded_at')
                    ->first();

                return [
                    'id' => $shipmentOrder->id,
                    'shipment_order_id' => $shipmentOrder->id,
                    'order_number' => $shipmentOrder->order_number,
                    'customer_name' => $shipmentOrder->customer?->company_name,
                    'receiver_customer_name' => $shipmentOrder->receiverCustomer?->company_name,
                    'pickup_address' => $shipmentOrder->pickup_address,
                    'destination_address' => $shipmentOrder->destination_address,
                    'item_name' => $shipmentOrder->item_name,
                    'vehicle_type' => $driverTrip?->vehicle?->vehicle_type ?? $suratAngkut?->vehicle?->vehicle_type ?? $shipmentOrder->vehicle_type,
                    'surat_number' => $suratAngkut?->surat_number,
                    'driver_name' => $driverTrip?->driver?->driver_name ?? $suratAngkut?->driver?->driver_name,
                    'plate_number' => $driverTrip?->vehicle?->plate_number ?? $suratAngkut?->vehicle?->plate_number,
                    'order_status' => $shipmentOrder->status,
                    'trip_status' => $driverTrip?->status,
                    'order_date' => $shipmentOrder->order_date,
                    'start_time' => $driverTrip?->start_time,
                    'finish_time' => $driverTrip?->finish_time,
                    'notes' => $shipmentOrder->notes,
                    'updated_by_name' => $shipmentOrder->updatedBy?->name,
                    'updated_at' => $shipmentOrder->updated_at,
                    'photo_muat' => $loadingPhoto,
                    'photo_bongkar' => $unloadingPhoto,
                    'trip_photos' => $driverTrip?->tripPhotos,
                    'bukti_muat_url' => PublicFileUrl::tripPhoto($loadingPhoto?->photo_path),
                    'bukti_bongkar_url' => PublicFileUrl::tripPhoto($unloadingPhoto?->photo_path),
                    'loading_photo_uploaded_at' => $loadingPhoto?->uploaded_at,
                    'unloading_photo_uploaded_at' => $unloadingPhoto?->uploaded_at,
                ];
            });
    }

    private function applyFilters(Builder $query, Request $request): void
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
                                ->orWhere('contact_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('receiverCustomer', function (Builder $query) use ($search): void {
                            $query->where('company_name', 'like', "%{$search}%")
                                ->orWhere('contact_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('driverTrips.driver', function (Builder $query) use ($search): void {
                            $query->where('driver_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('driverTrips.vehicle', function (Builder $query) use ($search): void {
                            $query->where('plate_number', 'like', "%{$search}%")
                                ->orWhere('vehicle_type', 'like', "%{$search}%");
                        })
                        ->orWhereHas('suratAngkuts.vehicle', function (Builder $query) use ($search): void {
                            $query->where('plate_number', 'like', "%{$search}%")
                                ->orWhere('vehicle_type', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('start_date'), function (Builder $query) use ($request): void {
                $query->whereDate('order_date', '>=', $request->query('start_date'));
            })
            ->when($request->filled('end_date'), function (Builder $query) use ($request): void {
                $query->whereDate('order_date', '<=', $request->query('end_date'));
            })
            ->when($request->filled('customer_id'), function (Builder $query) use ($request): void {
                $query->where('customer_id', $request->query('customer_id'));
            })
            ->when($request->filled('driver_id'), function (Builder $query) use ($request): void {
                $query->where(function (Builder $query) use ($request): void {
                    $query->whereHas('driverTrips', function (Builder $query) use ($request): void {
                        $query->where('driver_id', $request->query('driver_id'));
                    })->orWhereHas('suratAngkuts', function (Builder $query) use ($request): void {
                        $query->where('driver_id', $request->query('driver_id'));
                    });
                });
            })
            ->when($request->filled('vehicle_id'), function (Builder $query) use ($request): void {
                $query->where(function (Builder $query) use ($request): void {
                    $query->whereHas('driverTrips', function (Builder $query) use ($request): void {
                        $query->where('vehicle_id', $request->query('vehicle_id'));
                    })->orWhereHas('suratAngkuts', function (Builder $query) use ($request): void {
                        $query->where('vehicle_id', $request->query('vehicle_id'));
                    });
                });
            })
            ->when($request->filled('status'), function (Builder $query) use ($request): void {
                $query->where(function (Builder $query) use ($request): void {
                    $query->where('status', $request->query('status'))
                        ->orWhereHas('driverTrips', function (Builder $query) use ($request): void {
                            $query->where('status', $request->query('status'));
                        });
                });
            });

        ListQueryFilters::applyDateFilters($query, $request, 'order_date');
    }

    /**
     * @return array<string, string|null>
     */
    private function filterLabels(Request $request): array
    {
        return [
            'search' => $request->query('search'),
            'period' => $this->periodLabel($request),
            'status' => $request->filled('status')
                ? ucwords(str_replace('_', ' ', (string) $request->query('status')))
                : null,
        ];
    }

    private function periodLabel(Request $request): ?string
    {
        if ($request->filled('start_date') && $request->filled('end_date')) {
            return $request->query('start_date').' sampai '.$request->query('end_date');
        }

        if ($request->filled('date')) {
            return (string) $request->query('date');
        }

        if ($request->filled('month')) {
            $months = [
                1 => 'Januari',
                2 => 'Februari',
                3 => 'Maret',
                4 => 'April',
                5 => 'Mei',
                6 => 'Juni',
                7 => 'Juli',
                8 => 'Agustus',
                9 => 'September',
                10 => 'Oktober',
                11 => 'November',
                12 => 'Desember',
            ];
            $month = $months[(int) $request->query('month')] ?? $request->query('month');

            return trim($month.' '.($request->query('year') ?? ''));
        }

        if ($request->filled('year')) {
            return (string) $request->query('year');
        }

        return null;
    }

    private function reportFilename(Request $request): string
    {
        if ($request->filled('start_date') && $request->filled('end_date')) {
            return 'laporan-pengiriman-'.$request->query('start_date').'-sampai-'.$request->query('end_date').'.pdf';
        }

        if ($request->filled('month') && $request->filled('year')) {
            return 'laporan-pengiriman-'.strtolower(str_replace(' ', '-', (string) $this->periodLabel($request))).'.pdf';
        }

        return 'laporan-pengiriman-'.now()->toDateString().'.pdf';
    }

    private function excelFilename(Request $request): string
    {
        $prefix = $request->user()->role === 'kepala_operasional'
            ? 'laporan-kepala-operasional'
            : 'laporan-operasional';

        if ($request->filled('month') && $request->filled('year')) {
            return $prefix.'-'.strtolower(str_replace(' ', '-', (string) $this->periodLabel($request))).'.xlsx';
        }

        return $prefix.'-'.now()->toDateString().'.xlsx';
    }
}
