<?php

namespace App\Support;

use App\Models\Driver;

class DriverAvailability
{
    public static function unavailableReason(
        Driver $driver,
        ?int $exceptShipmentOrderId = null,
        ?int $exceptDriverTripId = null,
    ): ?string {
        $driver->loadMissing(['user', 'vehicle']);

        if (! $driver->user || $driver->user->role !== 'supir') {
            return 'Supir tidak bisa digunakan karena akun supir sudah tidak aktif atau sudah dihapus.';
        }

        if (! $driver->vehicle) {
            return 'Supir belum memiliki kendaraan aktif.';
        }

        if (self::hasActiveTrip($driver, $exceptShipmentOrderId, $exceptDriverTripId)) {
            return 'Supir sedang mendapatkan order aktif sehingga tidak bisa digunakan untuk order lain.';
        }

        return null;
    }

    public static function hasActiveTrip(
        Driver $driver,
        ?int $exceptShipmentOrderId = null,
        ?int $exceptDriverTripId = null,
    ): bool {
        return $driver->driverTrips()
            ->where('status', '!=', 'selesai')
            ->when($exceptShipmentOrderId, function ($query) use ($exceptShipmentOrderId): void {
                $query->where('shipment_order_id', '!=', $exceptShipmentOrderId);
            })
            ->when($exceptDriverTripId, function ($query) use ($exceptDriverTripId): void {
                $query->where('id', '!=', $exceptDriverTripId);
            })
            ->exists();
    }
}
