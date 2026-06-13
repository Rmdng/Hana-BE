<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShipmentOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'receiver_customer_id' => ['nullable', 'exists:customers,id'],
            'driver_id' => ['nullable', 'exists:drivers,id'],
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
            'pickup_address' => ['nullable', 'string'],
            'destination_address' => ['nullable', 'string'],
            'item_name' => ['required', 'string', 'max:255'],
            'item_description' => ['nullable', 'string'],
            'vehicle_type' => ['nullable', 'string', 'max:255'],
            'order_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
