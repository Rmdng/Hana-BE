<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSuratAngkutRequest extends FormRequest
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
        $suratAngkutId = $this->route('surat_angkut')?->id;

        return [
            'shipment_order_id' => ['required', 'exists:shipment_orders,id'],
            'driver_id' => ['required', 'exists:drivers,id'],
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'surat_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('surat_angkuts', 'surat_number')->ignore($suratAngkutId),
            ],
            'issue_date' => ['required', 'date'],
            'pickup_address' => ['required', 'string'],
            'destination_address' => ['required', 'string'],
            'status' => ['nullable', 'string', 'max:255'],
        ];
    }
}
