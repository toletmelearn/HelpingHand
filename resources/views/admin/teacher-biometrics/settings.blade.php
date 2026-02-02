@extends('layouts.admin')

@section('title', 'Biometric Settings')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="bi bi-gear"></i> Biometric Settings Configuration
            </h1>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Working Hours Configuration</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.teacher-biometrics.settings.update') }}" method="POST">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="school_start_time" class="form-label">School Start Time *</label>
                                <input type="time" class="form-control" id="school_start_time" name="school_start_time" 
                                       value="{{ old('school_start_time', $settings->school_start_time ?? '08:00') }}" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="school_end_time" class="form-label">School End Time *</label>
                                <input type="time" class="form-control" id="school_end_time" name="school_end_time" 
                                       value="{{ old('school_end_time', $settings->school_end_time ?? '16:00') }}" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="grace_period_minutes" class="form-label">Grace Period (minutes) *</label>
                                <input type="number" class="form-control" id="grace_period_minutes" name="grace_period_minutes" 
                                       value="{{ old('grace_period_minutes', $settings->grace_period_minutes ?? 15) }}" 
                                       min="0" max="120" required>
                                <div class="form-text">Time allowed after start time before marking as late</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="late_tolerance_limit" class="form-label">Late Tolerance Limit (minutes) *</label>
                                <input type="number" class="form-control" id="late_tolerance_limit" name="late_tolerance_limit" 
                                       value="{{ old('late_tolerance_limit', $settings->late_tolerance_limit ?? 30) }}" 
                                       min="0" max="120" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="lunch_break_duration" class="form-label">Lunch Break Duration (minutes) *</label>
                                <input type="number" class="form-control" id="lunch_break_duration" name="lunch_break_duration" 
                                       value="{{ old('lunch_break_duration', $settings->lunch_break_duration ?? 60) }}" 
                                       min="0" max="240" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="break_time_duration" class="form-label">Other Break Duration (minutes) *</label>
                                <input type="number" class="form-control" id="break_time_duration" name="break_time_duration" 
                                       value="{{ old('break_time_duration', $settings->break_time_duration ?? 30) }}" 
                                       min="0" max="120" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="half_day_minimum_hours" class="form-label">Half Day Minimum Hours *</label>
                                <input type="number" class="form-control" id="half_day_minimum_hours" name="half_day_minimum_hours" 
                                       value="{{ old('half_day_minimum_hours', $settings->half_day_minimum_hours ?? 4.00) }}" 
                                       step="0.5" min="1" max="12" required>
                                <div class="form-text">Minimum hours required to avoid half-day marking</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="early_departure_tolerance" class="form-label">Early Departure Tolerance (minutes) *</label>
                                <input type="number" class="form-control" id="early_departure_tolerance" name="early_departure_tolerance" 
                                       value="{{ old('early_departure_tolerance', $settings->early_departure_tolerance ?? 30) }}" 
                                       min="0" max="120" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Exclude Lunch from Working Hours</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="exclude_lunch_from_working_hours" 
                                           name="exclude_lunch_from_working_hours" value="1"
                                           {{ old('exclude_lunch_from_working_hours', $settings->exclude_lunch_from_working_hours ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="exclude_lunch_from_working_hours">
                                        Exclude lunch break time from working hours calculation
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Exclude Other Breaks</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="exclude_breaks_from_working_hours" 
                                           name="exclude_breaks_from_working_hours" value="1"
                                           {{ old('exclude_breaks_from_working_hours', $settings->exclude_breaks_from_working_hours ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="exclude_breaks_from_working_hours">
                                        Exclude other break time from working hours calculation
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $settings->description ?? '') }}</textarea>
                            <div class="form-text">Optional description for these settings</div>
                        </div>

                        <div class="alert alert-warning">
                            <h6 class="alert-heading">Important Notice</h6>
                            <p class="mb-0">
                                Changing these settings will affect all future biometric calculations. 
                                Existing records will retain their original calculations.
                            </p>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.teacher-biometrics.index') }}" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Dashboard
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Working Hours Preview</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Total Working Hours:</strong><br>
                        <span id="totalHoursPreview">8.0 hours</span>
                    </div>
                    <div class="mb-3">
                        <strong>Late Arrival Threshold:</strong><br>
                        <span>{{ $settings->school_start_time ?? '08:00' }} + {{ $settings->grace_period_minutes ?? 15 }} minutes = 
                              <span id="lateThreshold">{{ date('H:i', strtotime(($settings->school_start_time ?? '08:00') . ' +' . ($settings->grace_period_minutes ?? 15) . ' minutes')) }}</span>
                        </span>
                    </div>
                    <div class="mb-3">
                        <strong>Early Departure Threshold:</strong><br>
                        <span>{{ $settings->school_end_time ?? '16:00' }} - {{ $settings->early_departure_tolerance ?? 30 }} minutes = 
                              <span id="earlyThreshold">{{ date('H:i', strtotime(($settings->school_end_time ?? '16:00') . ' -' . ($settings->early_departure_tolerance ?? 30) . ' minutes')) }}</span>
                        </span>
                    </div>
                    <div class="alert alert-info">
                        <small>All calculations are performed automatically based on these settings.</small>
                    </div>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Configuration Tips</h6>
                </div>
                <div class="card-body">
                    <ul class="small">
                        <li>Grace period allows teachers to arrive slightly late without penalty</li>
                        <li>Break times are automatically subtracted from working hours if enabled</li>
                        <li>Half-day threshold helps identify partial attendance</li>
                        <li>Settings apply to all future records automatically</li>
                        <li>Consider seasonal variations for different time settings</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update preview when settings change
    const startTimeInput = document.getElementById('school_start_time');
    const endTimeInput = document.getElementById('school_end_time');
    const lunchBreakInput = document.getElementById('lunch_break_duration');
    const otherBreakInput = document.getElementById('break_time_duration');
    const gracePeriodInput = document.getElementById('grace_period_minutes');
    const earlyToleranceInput = document.getElementById('early_departure_tolerance');
    const excludeLunchCheckbox = document.getElementById('exclude_lunch_from_working_hours');
    const excludeBreaksCheckbox = document.getElementById('exclude_breaks_from_working_hours');
    
    const totalHoursPreview = document.getElementById('totalHoursPreview');
    const lateThreshold = document.getElementById('lateThreshold');
    const earlyThreshold = document.getElementById('earlyThreshold');
    
    function updatePreview() {
        // Calculate total working hours
        const startTime = startTimeInput.value;
        const endTime = endTimeInput.value;
        const lunchBreak = parseInt(lunchBreakInput.value) || 0;
        const otherBreak = parseInt(otherBreakInput.value) || 0;
        const excludeLunch = excludeLunchCheckbox.checked;
        const excludeBreaks = excludeBreaksCheckbox.checked;
        
        if (startTime && endTime) {
            const start = new Date(`2000-01-01T${startTime}`);
            const end = new Date(`2000-01-01T${endTime}`);
            
            if (end > start) {
                let totalMinutes = (end - start) / (1000 * 60);
                
                if (excludeLunch) {
                    totalMinutes -= lunchBreak;
                }
                if (excludeBreaks) {
                    totalMinutes -= otherBreak;
                }
                
                const hours = Math.floor(totalMinutes / 60);
                const minutes = Math.round(totalMinutes % 60);
                totalHoursPreview.textContent = `${hours}.${Math.round(minutes/6)*10} hours`;
            }
        }
        
        // Update late threshold
        if (startTime && gracePeriodInput.value) {
            const start = new Date(`2000-01-01T${startTime}`);
            const graceMinutes = parseInt(gracePeriodInput.value) || 0;
            start.setMinutes(start.getMinutes() + graceMinutes);
            lateThreshold.textContent = start.toTimeString().substring(0, 5);
        }
        
        // Update early departure threshold
        if (endTime && earlyToleranceInput.value) {
            const end = new Date(`2000-01-01T${endTime}`);
            const toleranceMinutes = parseInt(earlyToleranceInput.value) || 0;
            end.setMinutes(end.getMinutes() - toleranceMinutes);
            earlyThreshold.textContent = end.toTimeString().substring(0, 5);
        }
    }
    
    // Add event listeners
    [startTimeInput, endTimeInput, lunchBreakInput, otherBreakInput, gracePeriodInput, 
     earlyToleranceInput, excludeLunchCheckbox, excludeBreaksCheckbox].forEach(input => {
        if (input) {
            input.addEventListener('input', updatePreview);
        }
    });
    
    // Initial update
    updatePreview();
});
</script>
@endsection
