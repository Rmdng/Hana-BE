<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSuratAngkutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'shipment_order_id' => ['required', 'exists:shipment_orders,id'],
            'driver_id' => ['required', 'exists:drivers,id'],
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
            'surat_number' => ['nullable', 'string', 'max:255', 'unique:surat_angkuts,surat_number'],
            'issue_date' => ['required', 'date'],
            'pickup_address' => ['nullable', 'string'],
            'destination_address' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:255'],
        ];
    }
}
