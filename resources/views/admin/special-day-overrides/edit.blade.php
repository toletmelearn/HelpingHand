@extends('layouts.app')

@section('title', 'Edit Special Day Override')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Edit Special Day Override</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.special-day-overrides.update', $specialDayOverride) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="override_date" class="form-label">Override Date</label>
                                    <input type="date" class="form-control @error('override_date') is-invalid @enderror" id="override_date" name="override_date" value="{{ old('override_date', $specialDayOverride->override_date->format('Y-m-d')) }}" required>
                                    @error('override_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="type" class="form-label">Override Type</label>
                                    <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                                        <option value="">Select Type</option>
                                        <option value="exam_day" {{ old('type', $specialDayOverride->type) == 'exam_day' ? 'selected' : '' }}>Exam Day</option>
                                        <option value="half_day" {{ old('type', $specialDayOverride->type) == 'half_day' ? 'selected' : '' }}>Half Day</option>
                                        <option value="event_day" {{ old('type', $specialDayOverride->type) == 'event_day' ? 'selected' : '' }}>Event Day</option>
                                        <option value="emergency_closure" {{ old('type', $specialDayOverride->type) == 'emergency_closure' ? 'selected' : '' }}>Emergency Closure</option>
                                    </select>
                                    @error('type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="bell_schedule_id" class="form-label">Associated Bell Schedule (Optional)</label>
                                    <select class="form-select @error('bell_schedule_id') is-invalid @enderror" id="bell_schedule_id" name="bell_schedule_id">
                                        <option value="">Select Schedule (Optional)</option>
                                        @foreach($schedules as $schedule)
                                        <option value="{{ $schedule->id }}" {{ old('bell_schedule_id', $specialDayOverride->bell_schedule_id) == $schedule->id ? 'selected' : '' }}>
                                            {{ $schedule->name }} ({{ $schedule->season_type }})
                                        </option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">Select an existing bell schedule to use for this special day, or create custom periods below</div>
                                    @error('bell_schedule_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="remarks" class="form-label">Remarks</label>
                                    <textarea class="form-control @error('remarks') is-invalid @enderror" id="remarks" name="remarks" rows="3">{{ old('remarks', $specialDayOverride->remarks) }}</textarea>
                                    @error('remarks')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Custom Periods (Alternative to selecting a schedule)</label>
                            <div id="custom-periods-container">
                                @if($specialDayOverride->custom_periods)
                                    @foreach($specialDayOverride->custom_periods as $index => $period)
                                    <div class="custom-period-item mb-3 p-3 border rounded">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6>Custom Period {{ $period['period_number'] ?? ($index + 1) }}</h6>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeCustomPeriod(this)">Remove</button>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-2">
                                                <input type="number" class="form-control" name="custom_periods[{{ $index }}][period_number]" value="{{ $period['period_number'] ?? ($index + 1) }}" readonly>
                                            </div>
                                            <div class="col-md-3">
                                                <input type="text" class="form-control" name="custom_periods[{{ $index }}][period_name]" value="{{ $period['period_name'] ?? '' }}" placeholder="Period Name" required>
                                            </div>
                                            <div class="col-md-2">
                                                <input type="time" class="form-control" name="custom_periods[{{ $index }}][start_time]" value="{{ $period['start_time'] ?? '' }}" required>
                                            </div>
                                            <div class="col-md-2">
                                                <input type="time" class="form-control" name="custom_periods[{{ $index }}][end_time]" value="{{ $period['end_time'] ?? '' }}" required>
                                            </div>
                                            <div class="col-md-3">
                                                <select class="form-select" name="custom_periods[{{ $index }}][type]" required>
                                                    <option value="">Type</option>
                                                    <option value="teaching_period" {{ (isset($period['type']) && $period['type'] == 'teaching_period') ? 'selected' : '' }}>Teaching Period</option>
                                                    <option value="short_break" {{ (isset($period['type']) && $period['type'] == 'short_break') ? 'selected' : '' }}>Short Break</option>
                                                    <option value="lunch_break" {{ (isset($period['type']) && $period['type'] == 'lunch_break') ? 'selected' : '' }}>Lunch Break</option>
                                                    <option value="assembly" {{ (isset($period['type']) && $period['type'] == 'assembly') ? 'selected' : '' }}>Assembly</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                @endif
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addCustomPeriod()">
                                <i class="bi bi-plus-circle"></i> Add Custom Period
                            </button>
                            <div class="form-text mt-1">Use this to define custom periods for this special day instead of using an existing schedule</div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.special-day-overrides.index') }}" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Override</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var customPeriodCounter = <?php echo count($specialDayOverride->custom_periods ?? []); ?>;

function addCustomPeriod() {
    customPeriodCounter++;
    const container = document.getElementById('custom-periods-container');
    
    const periodDiv = document.createElement('div');
    periodDiv.className = 'custom-period-item mb-3 p-3 border rounded';
    periodDiv.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6>Custom Period ${customPeriodCounter}</h6>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeCustomPeriod(this)">Remove</button>
        </div>
        <div class="row">
            <div class="col-md-2">
                <input type="number" class="form-control" name="custom_periods[${customPeriodCounter}][period_number]" value="${customPeriodCounter}" readonly>
            </div>
            <div class="col-md-3">
                <input type="text" class="form-control" name="custom_periods[${customPeriodCounter}][period_name]" placeholder="Period Name" required>
            </div>
            <div class="col-md-2">
                <input type="time" class="form-control" name="custom_periods[${customPeriodCounter}][start_time]" placeholder="Start Time" required>
            </div>
            <div class="col-md-2">
                <input type="time" class="form-control" name="custom_periods[${customPeriodCounter}][end_time]" placeholder="End Time" required>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="custom_periods[${customPeriodCounter}][type]" required>
                    <option value="">Type</option>
                    <option value="teaching_period">Teaching Period</option>
                    <option value="short_break">Short Break</option>
                    <option value="lunch_break">Lunch Break</option>
                    <option value="assembly">Assembly</option>
                </select>
            </div>
        </div>
    `;
    
    container.appendChild(periodDiv);
}

function removeCustomPeriod(button) {
    button.closest('.custom-period-item').remove();
}
</script>
@endsection