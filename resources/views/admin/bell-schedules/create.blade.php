@extends('layouts.admin')

@section('title', 'Add Bell Schedule')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Add New Bell Schedule</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.bell-schedules.store') }}" method="POST">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Schedule Name</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="season_type" class="form-label">Season Type</label>
                                    <select class="form-select @error('season_type') is-invalid @enderror" id="season_type" name="season_type" required>
                                        <option value="">Select Season</option>
                                        <option value="summer" {{ old('season_type') == 'summer' ? 'selected' : '' }}>Summer</option>
                                        <option value="winter" {{ old('season_type') == 'winter' ? 'selected' : '' }}>Winter</option>
                                    </select>
                                    @error('season_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
                                        <option value="">Select Status</option>
                                        <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                    @error('status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" class="form-control @error('start_date') is-invalid @enderror" id="start_date" name="start_date" value="{{ old('start_date') }}" required>
                                    @error('start_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" class="form-control @error('end_date') is-invalid @enderror" id="end_date" name="end_date" value="{{ old('end_date') }}" required>
                                    @error('end_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="target_group" class="form-label">Target Group</label>
                            <select class="form-select @error('target_group') is-invalid @enderror" id="target_group" name="target_group">
                                <option value="">All Classes</option>
                                <option value="all" {{ old('target_group') == 'all' ? 'selected' : '' }}>All Classes</option>
                                <option value="primary" {{ old('target_group') == 'primary' ? 'selected' : '' }}>Primary (Nursery - 5th)</option>
                                <option value="middle" {{ old('target_group') == 'middle' ? 'selected' : '' }}>Middle (6th - 8th)</option>
                                <option value="senior" {{ old('target_group') == 'senior' ? 'selected' : '' }}>Senior (9th - 12th)</option>
                            </select>
                            @error('target_group')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Periods</label>
                            <div id="periods-container">
                                <!-- Periods will be added dynamically -->
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addPeriod()">
                                <i class="bi bi-plus-circle"></i> Add Period
                            </button>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.bell-schedules.index') }}" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save Bell Schedule</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let periodCounter = 0;

function addPeriod() {
    periodCounter++;
    const container = document.getElementById('periods-container');
    
    const periodDiv = document.createElement('div');
    periodDiv.className = 'period-item mb-3 p-3 border rounded';
    periodDiv.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6>Period ${periodCounter}</h6>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removePeriod(this)">Remove</button>
        </div>
        <div class="row">
            <div class="col-md-2">
                <input type="number" class="form-control" name="periods[${periodCounter}][period_number]" value="${periodCounter}" readonly>
            </div>
            <div class="col-md-3">
                <input type="text" class="form-control" name="periods[${periodCounter}][period_name]" placeholder="Period Name" required>
            </div>
            <div class="col-md-2">
                <input type="time" class="form-control" name="periods[${periodCounter}][start_time]" placeholder="Start Time" required>
            </div>
            <div class="col-md-2">
                <input type="time" class="form-control" name="periods[${periodCounter}][end_time]" placeholder="End Time" required>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="periods[${periodCounter}][type]" required>
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

function removePeriod(button) {
    button.closest('.period-item').remove();
}

// Add at least one period initially
document.addEventListener('DOMContentLoaded', function() {
    addPeriod();
});
</script>
@endsection
