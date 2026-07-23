<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcademicEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_session_id' => 'nullable|exists:academic_sessions,id',
            'title' => 'required|string|max:255',
            'type' => 'required|in:holiday,exam,event,ptm,other',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Please give this calendar entry a title.',
            'type.required' => 'Please choose an event type.',
            'type.in' => 'Event type must be one of: holiday, exam, event, ptm, other.',
            'start_date.required' => 'Start date is required.',
            'end_date.required' => 'End date is required.',
            'end_date.after_or_equal' => 'End date cannot be before the start date.',
        ];
    }
}
