<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTripLocationRequest extends FormRequest
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
            'driver_trip_id' => ['nullable', 'exists:driver_trips,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'address' => ['nullable', 'string'],
            'status' => ['required', Rule::in($this->statuses())],
            'recorded_at' => ['nullable', 'date'],
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
