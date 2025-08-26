<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Superadmin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($this->route('user'))],
            'password' => 'sometimes|required|string|min:8|confirmed',
            'department' => ['nullable', Rule::in(['Accounting', 'Marketing', 'Purchasing', 'QC & Engineering', 'Maintenance', 'HR & GA', 'Brazing', 'Chassis', 'Nylon', 'PPIC'])],
            'nik' => ['nullable', 'string', 'min:6', Rule::unique('users')->ignore($this->route('user'))],
            'roles' => 'sometimes|required|array|min:1',
            'roles.*' => 'string|exists:roles,name'
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'name.max' => 'Nama tidak boleh lebih dari 255 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'department.in' => 'Departemen yang dipilih tidak valid.',
            'nik.min' => 'NIK minimal 6 karakter.',
            'nik.unique' => 'NIK sudah terdaftar.',
            'roles.required' => 'Role wajib dipilih.',
            'roles.array' => 'Role harus berupa array.',
            'roles.min' => 'Minimal satu role harus dipilih.',
            'roles.*.exists' => 'Role yang dipilih tidak valid.',
        ];
    }
}
