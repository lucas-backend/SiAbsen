<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceSessionRequest extends FormRequest
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
            'assignment_id' => ['required', 'integer', 'exists:teacher_assignments,id'],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'session_date' => ['nullable', 'date'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * Custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'assignment_id.required' => 'Penugasan mengajar (assignment_id) wajib diisi.',
            'assignment_id.exists' => 'Penugasan mengajar yang dipilih tidak valid.',
            'class_id.required' => 'Kelas (class_id) wajib diisi.',
            'class_id.exists' => 'Kelas yang dipilih tidak ditemukan.',
            'start_at.required' => 'Waktu mulai sesi wajib diisi.',
            'start_at.date' => 'Format waktu mulai sesi tidak valid.',
            'end_at.required' => 'Waktu berakhir sesi wajib diisi.',
            'end_at.date' => 'Format waktu berakhir sesi tidak valid.',
            'end_at.after' => 'Waktu berakhir sesi harus setelah waktu mulai sesi.',
            'latitude.between' => 'Nilai latitude harus berada dalam rentang -90 hingga 90.',
            'longitude.between' => 'Nilai longitude harus berada dalam rentang -180 hingga 180.',
        ];
    }
}
