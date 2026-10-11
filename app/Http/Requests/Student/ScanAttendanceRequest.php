<?php

namespace App\Http\Requests\Student;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ScanAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && strtoupper((string) ($user->role ?? '')) === 'STUDENT';
    }

    /**
     * Prepare inputs before validation.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('qr_token') && $this->has('qr_payload')) {
            $this->merge(['qr_token' => $this->input('qr_payload')]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'qr_token' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * Custom validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'qr_token.required' => 'Token QR absensi wajib diisi.',
            'qr_token.string' => 'Token QR harus berupa teks yang valid.',
            'qr_token.max' => 'Panjang token QR melebihi batas yang diizinkan.',
            'latitude.required' => 'Koordinat latitude wajib dikirimkan oleh perangkat GPS.',
            'latitude.numeric' => 'Koordinat latitude harus berupa angka.',
            'latitude.between' => 'Koordinat latitude harus berada di antara -90 dan 90.',
            'longitude.required' => 'Koordinat longitude wajib dikirimkan oleh perangkat GPS.',
            'longitude.numeric' => 'Koordinat longitude harus berupa angka.',
            'longitude.between' => 'Koordinat longitude harus berada di antara -180 dan 180.',
        ];
    }
}
