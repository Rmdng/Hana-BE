<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripPhoto extends Model
{
    protected $fillable = [
        'driver_trip_id',
        'photo_type',
        'photo_path',
        'latitude',
        'longitude',
        'notes',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'uploaded_at' => 'datetime',
        ];
    }

    public function driverTrip(): BelongsTo
    {
        return $this->belongsTo(DriverTrip::class);
    }
}
