<?php

namespace App\Services;

use App\Models\DriverTrip;

class ShipmentStatusSyncService
{
    public function syncFromTrip(DriverTrip $driverTrip): void
    {
        $status = $driverTrip->status;

        $driverTrip->shipmentOrder?->update(['status' => $status]);

        $suratAngkut = $driverTrip->suratAngkut;
        if (! $suratAngkut && $driverTrip->shipmentOrder) {
            $suratAngkut = $driverTrip->shipmentOrder
                ->suratAngkuts()
                ->latest()
                ->first();
        }

        $suratAngkut?->update(['status' => $status]);
    }
}
