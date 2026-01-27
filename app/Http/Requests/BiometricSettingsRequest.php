<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\BiometricSetting;

class BiometricSettingsRequest extends FormRequest
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
        $exceptId = $this->route('setting') ? $this->route('setting')->id : null;
        return BiometricSetting::getValidationRules($exceptId);
    }
    
    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'school_start_time.required' => 'School start time is required.',
            'school_start_time.date_format' => 'School start time must be in HH:MM:SS format.',
            'school_end_time.required' => 'School end time is required.',
            'school_end_time.date_format' => 'School end time must be in HH:MM:SS format.',
            'school_end_time.after' => 'School end time must be after start time.',
            'grace_period_minutes.required' => 'Grace period is required.',
            'grace_period_minutes.integer' => 'Grace period must be a whole number.',
            'grace_period_minutes.min' => 'Grace period must be at least 0 minutes.',
            'grace_period_minutes.max' => 'Grace period cannot exceed 120 minutes.',
            'lunch_break_duration.required' => 'Lunch break duration is required.',
            'lunch_break_duration.integer' => 'Lunch break duration must be a whole number.',
            'lunch_break_duration.min' => 'Lunch break duration must be at least 0 minutes.',
            'lunch_break_duration.max' => 'Lunch break duration cannot exceed 240 minutes.',
            'break_time_duration.required' => 'Break time duration is required.',
            'break_time_duration.integer' => 'Break time duration must be a whole number.',
            'break_time_duration.min' => 'Break time duration must be at least 0 minutes.',
            'break_time_duration.max' => 'Break time duration cannot exceed 120 minutes.',
            'half_day_minimum_hours.required' => 'Half day minimum hours is required.',
            'half_day_minimum_hours.numeric' => 'Half day minimum hours must be a number.',
            'half_day_minimum_hours.min' => 'Half day minimum hours must be at least 1 hour.',
            'half_day_minimum_hours.max' => 'Half day minimum hours cannot exceed 12 hours.',
            'late_tolerance_limit.required' => 'Late tolerance limit is required.',
            'late_tolerance_limit.integer' => 'Late tolerance limit must be a whole number.',
            'late_tolerance_limit.min' => 'Late tolerance limit must be at least 0 minutes.',
            'late_tolerance_limit.max' => 'Late tolerance limit cannot exceed 120 minutes.',
            'early_departure_tolerance.required' => 'Early departure tolerance is required.',
            'early_departure_tolerance.integer' => 'Early departure tolerance must be a whole number.',
            'early_departure_tolerance.min' => 'Early departure tolerance must be at least 0 minutes.',
            'early_departure_tolerance.max' => 'Early departure tolerance cannot exceed 120 minutes.',
            'description.max' => 'Description cannot exceed 500 characters.',
        ];
    }
}
