<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateManualAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && strtoupper((string) ($user->role ?? '')) === 'TEACHER';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:users,id'],
            'status' => ['required', 'string', 'in:SAKIT,IZIN,BELUM_ABSEN'],
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
            'student_id.required' => 'Identitas siswa wajib diisi.',
            'student_id.integer' => 'Identitas siswa harus berupa angka valid.',
            'student_id.exists' => 'Siswa tidak ditemukan dalam sistem.',
            'status.required' => 'Status kehadiran wajib dipilih.',
            'status.in' => 'Status kehadiran harus salah satu dari: SAKIT, IZIN, atau BELUM_ABSEN.',
        ];
    }
}
