<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripLocation extends Model
{
    protected $fillable = [
        'driver_trip_id',
        'latitude',
        'longitude',
        'address',
        'status',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'recorded_at' => 'datetime',
        ];
    }

    public function driverTrip(): BelongsTo
    {
        return $this->belongsTo(DriverTrip::class);
    }
}
