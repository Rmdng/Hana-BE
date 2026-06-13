<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SuratAngkut extends Model
{
    protected $fillable = [
        'shipment_order_id',
        'driver_id',
        'vehicle_id',
        'surat_number',
        'issue_date',
        'pickup_address',
        'destination_address',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
        ];
    }

    public function shipmentOrder(): BelongsTo
    {
        return $this->belongsTo(ShipmentOrder::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driverTrips(): HasMany
    {
        return $this->hasMany(DriverTrip::class);
    }
}
