<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Surat Order {{ $shipmentOrder->order_number }}</title>
    <style>
        @page {
            size: 9.5in 11in;
            margin: 0.35in;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: "Courier New", monospace;
            color: #000;
            font-size: 11px;
            line-height: 1.28;
            margin: 0;
        }
        .header {
            text-align: center;
            border-bottom: 1px solid #000;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }
        .company {
            font-size: 15px;
            font-weight: bold;
        }
        .title {
            font-size: 14px;
            font-weight: bold;
            margin-top: 3px;
        }
        .section {
            margin-top: 7px;
        }
        .section-title {
            font-weight: bold;
            border-bottom: 1px solid #000;
            margin-bottom: 4px;
            padding-bottom: 2px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        td, th {
            vertical-align: top;
            padding: 2px 3px;
        }
        .box td, .box th {
            border: 1px solid #000;
        }
        .label {
            width: 130px;
            white-space: nowrap;
        }
        .two-col {
            width: 100%;
        }
        .two-col > tbody > tr > td {
            width: 50%;
            padding: 0 4px 0 0;
        }
        .two-col > tbody > tr > td + td {
            padding: 0 0 0 4px;
        }
        .note {
            border: 1px solid #000;
            padding: 5px 6px;
            min-height: 30px;
        }
        .signatures {
            margin-top: 18px;
            text-align: center;
        }
        .signatures td {
            width: 33.33%;
            height: 62px;
            vertical-align: bottom;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">PT HANA JAYA PUTRA TRANSPORT</div>
        <div class="title">SURAT ORDER PENGIRIMAN</div>
    </div>

    <div class="section">
        <div class="section-title">1. DATA ORDER</div>
        <table class="box">
            <tr>
                <td class="label">Nomor Order</td>
                <td>{{ $shipmentOrder->order_number ?? '-' }}</td>
                <td class="label">Tanggal Order</td>
                <td>{{ optional($shipmentOrder->order_date)->format('d-m-Y') ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Status Order</td>
                <td>{{ ucwords(str_replace('_', ' ', $shipmentOrder->status ?? '-')) }}</td>
                <td class="label">No. Surat Angkut</td>
                <td>{{ $suratAngkut->surat_number ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <table class="two-col section">
        <tr>
            <td>
                <div class="section-title">2. DATA PENGIRIM</div>
                <table class="box">
                    <tr><td class="label">Nama Customer</td><td>{{ $sender->contact_name ?? '-' }}</td></tr>
                    <tr><td class="label">Perusahaan</td><td>{{ $sender->company_name ?? '-' }}</td></tr>
                    <tr><td class="label">Alamat Muat</td><td>{{ $shipmentOrder->pickup_address ?? '-' }}</td></tr>
                </table>
            </td>
            <td>
                <div class="section-title">3. DATA PENERIMA</div>
                <table class="box">
                    <tr><td class="label">Nama Customer</td><td>{{ $receiver->contact_name ?? '-' }}</td></tr>
                    <tr><td class="label">Perusahaan</td><td>{{ $receiver->company_name ?? '-' }}</td></tr>
                    <tr><td class="label">Alamat Bongkar</td><td>{{ $shipmentOrder->destination_address ?? '-' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="two-col section">
        <tr>
            <td>
                <div class="section-title">4. DATA SUPIR</div>
                <table class="box">
                    <tr><td class="label">Nama Supir</td><td>{{ $driver->driver_name ?? '-' }}</td></tr>
                    <tr><td class="label">Telepon</td><td>{{ $driver->phone ?? '-' }}</td></tr>
                </table>
            </td>
            <td>
                <div class="section-title">5. DATA KENDARAAN</div>
                <table class="box">
                    <tr><td class="label">Nomor Polisi</td><td>{{ $vehicle->plate_number ?? '-' }}</td></tr>
                    <tr><td class="label">Jenis Unit</td><td>{{ $vehicle->vehicle_type ?? '-' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="section">
        <div class="section-title">6. DATA BARANG</div>
        <table class="box">
            <tr><td class="label">Nama Barang</td><td>{{ $shipmentOrder->item_name ?? '-' }}</td></tr>
            <tr><td class="label">Catatan</td><td>{{ $shipmentOrder->notes ?? '-' }}</td></tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">7. KETERANGAN</div>
        <div class="note">
            Surat order ini dibuat oleh sistem administrasi pengelolaan data pengiriman PT Hana Jaya Putra Transport.
        </div>
    </div>

    <table class="signatures">
        <tr>
            <td>Operasional<br><br><br>(...........)</td>
            <td>Supir<br><br><br>(...........)</td>
            <td>Penerima<br><br><br>(...........)</td>
        </tr>
    </table>
</body>
</html>
