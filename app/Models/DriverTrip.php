<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DriverTrip extends Model
{
    protected $fillable = [
        'shipment_order_id',
        'driver_id',
        'vehicle_id',
        'surat_angkut_id',
        'start_time',
        'finish_time',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'finish_time' => 'datetime',
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

    public function suratAngkut(): BelongsTo
    {
        return $this->belongsTo(SuratAngkut::class);
    }

    public function tripLocations(): HasMany
    {
        return $this->hasMany(TripLocation::class);
    }

    public function tripPhotos(): HasMany
    {
        return $this->hasMany(TripPhoto::class);
    }

    public function latestLocation(): HasOne
    {
        return $this->hasOne(TripLocation::class)->latestOfMany('recorded_at');
    }

    public function photos(): HasMany
    {
        return $this->tripPhotos();
    }
}
