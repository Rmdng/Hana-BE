<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDriverTripRequest extends FormRequest
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
            'shipment_order_id' => ['required', 'exists:shipment_orders,id'],
            'driver_id' => ['required', 'exists:drivers,id'],
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
            'surat_angkut_id' => ['nullable', 'exists:surat_angkuts,id'],
            'start_time' => ['nullable', 'date'],
            'finish_time' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in($this->statuses())],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return list<string>
     */
    private function statuses(): array
    {
        return [
            'menunggu_muat',
            'sampai_tempat_muat',
            'bukti_muat_diupload',
            'menunggu',
            'menuju_lokasi_muat',
            'proses_muat',
            'dalam_perjalanan',
            'sampai_tempat_bongkar',
            'bukti_bongkar_diupload',
            'proses_bongkar',
            'selesai',
        ];
    }
}
