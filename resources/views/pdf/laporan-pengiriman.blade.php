<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Pengiriman</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 16mm;
        }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #111827;
            font-size: 9px;
            line-height: 1.35;
        }
        .header {
            border-bottom: 3px solid #0f172a;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .company {
            color: #0f172a;
            font-size: 18px;
            font-weight: bold;
            letter-spacing: .4px;
        }
        .title {
            color:rgb(0, 0, 0);
            font-size: 15px;
            font-weight: bold;
            margin-top: 2px;
        }
        .meta {
            margin-top: 8px;
            color: #374151;
        }
        .summary {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            margin: 10px -6px 12px;
        }
        .summary td {
            border: 1px solid #cbd5e1;
            padding: 8px;
            background: #f8fafc;
            width: 16.66%;
        }
        .summary .value {
            color: #0f172a;
            font-size: 15px;
            font-weight: bold;
        }
        .summary .label {
            color: #64748b;
            font-size: 8px;
            margin-top: 2px;
        }
        .filters {
            border: 1px solid #d1d5db;
            padding: 7px 8px;
            margin-bottom: 12px;
            background: #f9fafb;
        }
        table.report {
            width: 100%;
            border-collapse: collapse;
        }
        table.report th {
            background: #0f172a;
            color: #fff;
            padding: 5px 4px;
            border: 1px solid #0f172a;
            text-align: left;
            font-size: 8px;
        }
        table.report td {
            padding: 4px;
            border: 1px solid #d1d5db;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.report tr:nth-child(even) td {
            background: #f8fafc;
        }
        .status {
            font-weight: bold;
            color: #0f172a;
        }
        .footer {
            position: fixed;
            bottom: -8mm;
            left: 0;
            right: 0;
            color: #6b7280;
            font-size: 8px;
            border-top: 1px solid #d1d5db;
            padding-top: 4px;
        }
        .footer .page:after {
            content: counter(page);
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">PT HANA JAYA PUTRA TRANSPORT</div>
        <div class="title">LAPORAN PENGIRIMAN</div>
        <div class="meta">
            Tanggal cetak: {{ $printedAt->format('d-m-Y H:i') }} |
            Dicetak oleh: {{ $printedBy->name ?? '-' }} |
            Role: {{ ucwords(str_replace('_', ' ', $printedBy->role ?? '-')) }}
        </div>
    </div>

    <div class="filters">
        Periode: {{ $filters['period'] ?? 'Semua periode' }} |
        Status: {{ $filters['status'] ?? 'Semua status' }} |
        Pencarian: {{ $filters['search'] ?? '-' }} |
        Total data: {{ $summary['total'] }}
    </div>

    <table class="summary">
        <tr>
            <td><div class="value">{{ $summary['total'] }}</div><div class="label">Total Pengiriman</div></td>
            <td><div class="value">{{ $summary['selesai'] }}</div><div class="label">Selesai</div></td>
            <td><div class="value">{{ $summary['dalam_perjalanan'] }}</div><div class="label">Dalam Perjalanan</div></td>
            <td><div class="value">{{ $summary['menunggu_muat'] }}</div><div class="label">Menunggu Muat</div></td>
            <td><div class="value">{{ $summary['total_customer'] }}</div><div class="label">Customer Terkait</div></td>
            <td><div class="value">{{ $summary['total_supir'] }}</div><div class="label">Supir Terkait</div></td>
        </tr>
    </table>

    <table class="report">
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 11%;">No Order</th>
                <th style="width: 9%;">Tanggal</th>
                <th style="width: 12%;">Pengirim</th>
                <th style="width: 12%;">Penerima</th>
                <th style="width: 10%;">Supir</th>
                <th style="width: 10%;">Unit</th>
                <th style="width: 13%;">Muat</th>
                <th style="width: 13%;">Bongkar</th>
                <th style="width: 6%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($shipments as $index => $shipment)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $shipment['order_number'] ?? '-' }}</td>
                    <td>{{ optional($shipment['order_date'])->format('d-m-Y') ?? '-' }}</td>
                    <td>{{ $shipment['customer_name'] ?? '-' }}</td>
                    <td>{{ $shipment['receiver_customer_name'] ?? '-' }}</td>
                    <td>{{ $shipment['driver_name'] ?? '-' }}</td>
                    <td>{{ $shipment['plate_number'] ?? '-' }}<br>{{ $shipment['vehicle_type'] ?? '-' }}</td>
                    <td>{{ $shipment['pickup_address'] ?? '-' }}</td>
                    <td>{{ $shipment['destination_address'] ?? '-' }}</td>
                    <td class="status">{{ ucwords(str_replace('_', ' ', $shipment['order_status'] ?? '-')) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Laporan ini dibuat secara otomatis melalui Sistem Administrasi Pengelolaan Data Pengiriman PT Hana Jaya Putra Transport.
        <span style="float: right;">Halaman <span class="page"></span></span>
    </div>
</body>
</html>
