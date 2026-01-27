<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeacherBiometricUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Assuming admin authorization is handled elsewhere
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'teacher_id' => 'required|exists:teachers,id',
            'date' => 'required|date',
            'first_in_time' => 'nullable|date_format:H:i',
            'last_out_time' => 'nullable|date_format:H:i',
            'remarks' => 'nullable|string|max:500',
        ];
    }
    
    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'teacher_id.required' => 'Please select a teacher.',
            'teacher_id.exists' => 'Selected teacher does not exist.',
            'date.required' => 'Date is required.',
            'date.date' => 'Please enter a valid date.',
            'first_in_time.date_format' => 'First in time must be in HH:MM format.',
            'last_out_time.date_format' => 'Last out time must be in HH:MM format.',
            'remarks.max' => 'Remarks cannot exceed 500 characters.',
        ];
    }
}
