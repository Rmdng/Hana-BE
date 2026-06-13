<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ShipmentReportExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(
        private readonly Collection $shipments,
        private readonly array $filters,
        private readonly string $role
    ) {}

    public function array(): array
    {
        $rows = [
            ['PT HANA JAYA PUTRA TRANSPORT'],
            ['LAPORAN PENGIRIMAN'],
            ['Periode: '.($this->filters['period'] ?? 'Semua periode').' | Status: '.($this->filters['status'] ?? 'Semua status').' | Pencarian: '.($this->filters['search'] ?? '-')],
            [],
            $this->headings(),
        ];

        foreach ($this->shipments->values() as $index => $shipment) {
            $rows[] = $this->row($index + 1, $shipment);
        }

        return $rows;
    }

    public function title(): string
    {
        return $this->isKepalaOperasional()
            ? 'Laporan Kepala Operasional'
            : 'Laporan Operasional';
    }

    public function styles(Worksheet $sheet): array
    {
        $highestColumn = $sheet->getHighestColumn();
        $highestRow = $sheet->getHighestRow();

        $sheet->mergeCells("A1:{$highestColumn}1");
        $sheet->mergeCells("A2:{$highestColumn}2");
        $sheet->mergeCells("A3:{$highestColumn}3");
        $sheet->freezePane('A6');

        $sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A3')->getFont()->setItalic(true);
        $sheet->getStyle("A5:{$highestColumn}5")->getFont()->setBold(true);
        $sheet->getStyle("A5:{$highestColumn}5")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFE5E7EB');
        $sheet->getStyle("A5:{$highestColumn}{$highestRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        return [];
    }

    private function headings(): array
    {
        if ($this->isKepalaOperasional()) {
            return [
                'No',
                'Nomor Order',
                'Tanggal Order',
                'Customer Pengirim',
                'Customer Penerima',
                'Supir',
                'Unit/Kendaraan',
                'Nomor Polisi',
                'Status Order',
                'Status Perjalanan',
                'Bukti Muat',
                'Bukti Bongkar',
                'Terakhir Diedit Oleh',
                'Waktu Perubahan',
                'Catatan',
            ];
        }

        return [
            'No',
            'Nomor Order',
            'Tanggal Order',
            'Customer Pengirim',
            'Customer Penerima',
            'Alamat Muat',
            'Alamat Bongkar',
            'Nama Supir',
            'Unit/Kendaraan',
            'Nomor Polisi',
            'Nama Barang',
            'Status Order',
            'Status Perjalanan',
            'Waktu Selesai',
            'Catatan',
        ];
    }

    private function row(int $number, array $shipment): array
    {
        if ($this->isKepalaOperasional()) {
            return [
                $number,
                $shipment['order_number'] ?? '-',
                $this->dateValue($shipment['order_date'] ?? null),
                $shipment['customer_name'] ?? '-',
                $shipment['receiver_customer_name'] ?? '-',
                $shipment['driver_name'] ?? '-',
                $shipment['vehicle_type'] ?? '-',
                $shipment['plate_number'] ?? '-',
                $this->statusLabel($shipment['order_status'] ?? null),
                $this->statusLabel($shipment['trip_status'] ?? null),
                empty($shipment['bukti_muat_url']) ? 'Belum Ada' : 'Ada',
                empty($shipment['bukti_bongkar_url']) ? 'Belum Ada' : 'Ada',
                $shipment['updated_by_name'] ?? '-',
                $this->dateTimeValue($shipment['updated_at'] ?? null),
                $shipment['notes'] ?? '-',
            ];
        }

        return [
            $number,
            $shipment['order_number'] ?? '-',
            $this->dateValue($shipment['order_date'] ?? null),
            $shipment['customer_name'] ?? '-',
            $shipment['receiver_customer_name'] ?? '-',
            $shipment['pickup_address'] ?? '-',
            $shipment['destination_address'] ?? '-',
            $shipment['driver_name'] ?? '-',
            $shipment['vehicle_type'] ?? '-',
            $shipment['plate_number'] ?? '-',
            $shipment['item_name'] ?? '-',
            $this->statusLabel($shipment['order_status'] ?? null),
            $this->statusLabel($shipment['trip_status'] ?? null),
            $this->dateTimeValue($shipment['finish_time'] ?? null),
            $shipment['notes'] ?? '-',
        ];
    }

    private function isKepalaOperasional(): bool
    {
        return in_array($this->role, ['admin', 'kepala_operasional'], true);
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'menunggu_muat' => 'Menunggu Muat',
            'sampai_tempat_muat' => 'Sampai di Tempat Muat',
            'bukti_muat_diupload' => 'Bukti Muat Diupload',
            'dalam_perjalanan' => 'Dalam Perjalanan',
            'sampai_tempat_bongkar' => 'Sampai di Tempat Bongkar',
            'bukti_bongkar_diupload' => 'Bukti Bongkar Diupload',
            'selesai' => 'Selesai',
            'dibuat' => 'Dibuat',
            default => $status ? ucwords(str_replace('_', ' ', $status)) : '-',
        };
    }

    private function dateValue(mixed $value): string
    {
        return $value && method_exists($value, 'format') ? $value->format('d-m-Y') : ($value ? (string) $value : '-');
    }

    private function dateTimeValue(mixed $value): string
    {
        return $value && method_exists($value, 'format') ? $value->format('d-m-Y H:i') : ($value ? (string) $value : '-');
    }
}
