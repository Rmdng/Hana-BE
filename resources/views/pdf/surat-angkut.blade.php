<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Surat Angkut {{ $suratAngkut->surat_number }}</title>
    <style>
        @page {
            size: 9.5in 11in;
            margin: 0.30in;
        }
        body {
            font-family: "Courier New", Courier, monospace;
            font-size: 10px;
            color: #000;
            line-height: 1.2;
            margin: 0;
        }
        .title {
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            line-height: 1.3;
        }
        .line {
            border-top: 1px solid #000;
            margin: 5px 0;
            clear: both;
        }
        .row {
            width: 100%;
            clear: both;
        }
        .row:after {
            content: "";
            display: table;
            clear: both;
        }
        .col-left {
            width: 49%;
            float: left;
        }
        .col-right {
            width: 49%;
            float: right;
        }
        .heading {
            font-weight: bold;
            margin-bottom: 3px;
        }
        .field {
            margin: 2px 0;
        }
        .label {
            display: inline-block;
            width: 58px;
            font-weight: bold;
            vertical-align: top;
        }
        .value {
            display: inline-block;
            width: 78%;
            vertical-align: top;
        }
        .address {
            max-height: 24px;
            overflow: hidden;
        }
        .small {
            font-size: 9px;
        }
        .note {
            margin-top: 3px;
        }
        .signature-table {
            width: 100%;
            text-align: center;
            margin-top: 16px;
            border-collapse: collapse;
        }
        .signature-table td {
            width: 33.3%;
            padding-top: 4px;
        }
    </style>
</head>
<body>
    <div class="title">
        PT HANA JAYA PUTRA TRANSPORT<br>
        SURAT ANGKUT
    </div>

    <div class="line"></div>

    <div class="row">
        <div class="col-left">
            <div class="field"><span class="label">No Surat</span>: <span class="value">{{ $suratAngkut->surat_number ?? '-' }}</span></div>
            <div class="field"><span class="label">No Order</span>: <span class="value">{{ $shipmentOrder->order_number ?? '-' }}</span></div>
        </div>
        <div class="col-right">
            <div class="field"><span class="label">Tanggal</span>: <span class="value">{{ optional($suratAngkut->issue_date)->format('d-m-Y') ?? '-' }}</span></div>
        </div>
    </div>

    <div class="line"></div>

    <div class="row">
        <div class="col-left">
            <div class="heading">PENGIRIM</div>
            <div class="field"><span class="label">Nama</span>: <span class="value">{{ $sender->company_name ?? $sender->contact_name ?? '-' }}</span></div>
            <div class="field">
                <span class="label">Alamat</span>:
                <span class="value address">{{ $shipmentOrder->pickup_address ?? $suratAngkut->pickup_address ?? '-' }}</span>
            </div>
        </div>
        <div class="col-right">
            <div class="heading">PENERIMA</div>
            <div class="field"><span class="label">Nama</span>: <span class="value">{{ $receiver->company_name ?? $receiver->contact_name ?? '-' }}</span></div>
            <div class="field">
                <span class="label">Alamat</span>:
                <span class="value address">{{ $shipmentOrder->destination_address ?? $suratAngkut->destination_address ?? '-' }}</span>
            </div>
        </div>
    </div>

    <div class="line"></div>

    <div class="row">
        <div class="col-left">
            <div class="heading">SUPIR / UNIT</div>
            <div class="field"><span class="label">Supir</span>: <span class="value">{{ $driver->driver_name ?? '-' }}</span></div>
            <div class="field"><span class="label">No Pol</span>: <span class="value">{{ $vehicle->plate_number ?? '-' }}</span></div>
            <div class="field"><span class="label">Unit</span>: <span class="value">{{ $vehicle->vehicle_type ?? '-' }}</span></div>
        </div>
        <div class="col-right">
            <div class="heading">BARANG</div>
            <div class="field"><span class="label">Barang</span>: <span class="value">{{ $shipmentOrder->item_name ?? '-' }}</span></div>
            <div class="field"><span class="label">Catatan</span>: <span class="value">{{ $shipmentOrder->notes ?? '-' }}</span></div>
        </div>
    </div>

    <div class="line"></div>

    <div class="heading">KETERANGAN</div>
    <div class="note small">
        Surat angkut ini dibuat oleh sistem administrasi pengiriman PT Hana Jaya Putra Transport.
    </div>

    <div class="line"></div>

    <table class="signature-table">
        <tr>
            <td>Operasional</td>
            <td>Supir</td>
            <td>Penerima</td>
        </tr>
        <tr>
            <td style="height: 34px;"></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>(...........)</td>
            <td>(...........)</td>
            <td>(...........)</td>
        </tr>
    </table>

    <div class="line"></div>
</body>
</html>
