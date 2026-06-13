<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShipmentOrder extends Model
{
    protected $fillable = [
        'customer_id',
        'receiver_customer_id',
        'order_number',
        'pickup_address',
        'destination_address',
        'item_name',
        'item_description',
        'vehicle_type',
        'order_date',
        'status',
        'notes',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function receiverCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'receiver_customer_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
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
