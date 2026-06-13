<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    protected $fillable = [
        'user_id',
        'vehicle_id',
        'driver_name',
        'phone',
        'license_number',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function suratAngkuts(): HasMany
    {
        return $this->hasMany(SuratAngkut::class);
    }

    public function driverTrips(): HasMany
    {
        return $this->hasMany(DriverTrip::class);
    }
}
