@extends('layouts.app')

@section('title', 'Bulk Mark Attendance')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-people"></i> Bulk Mark Attendance</h2>
        <div>
            <a href="{{ route('attendance.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Attendance
            </a>
            <a href="{{ route('attendance.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Single Mark
            </a>
        </div>
    </div>

    <!-- Bulk Mark Form -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Bulk Mark Attendance</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('attendance.store') }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="date" class="form-label">Date *</label>
                            <input type="date" class="form-control @error('date') is-invalid @enderror" 
                                   id="date" name="date" value="{{ old('date', now()->toDateString()) }}" required>
                            @error('date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject *</label>
                            <input type="text" class="form-control @error('subject') is-invalid @enderror" 
                                   id="subject" name="subject" value="{{ old('subject') }}" required>
                            @error('subject')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="period" class="form-label">Period</label>
                            <input type="text" class="form-control @error('period') is-invalid @enderror" 
                                   id="period" name="period" value="{{ old('period') }}">
                            @error('period')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Class Selection -->
                <div class="mb-4">
                    <label class="form-label">Select Classes *</label>
                    <div class="row">
                        @foreach($classes as $class)
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="class_{{ Str::slug($class) }}" 
                                       name="classes[]" value="{{ $class }}">
                                <label class="form-check-label" for="class_{{ Str::slug($class) }}">
                                    {{ $class }}
                                </label>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                <!-- Attendance Options -->
                <div class="mb-4">
                    <label class="form-label">Default Attendance Status *</label>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="radio" class="form-check-input" id="status_present" name="default_status" value="present" checked>
                                <label class="form-check-label" for="status_present">
                                    <span class="badge bg-success">Present</span>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="radio" class="form-check-input" id="status_absent" name="default_status" value="absent">
                                <label class="form-check-label" for="status_absent">
                                    <span class="badge bg-danger">Absent</span>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="radio" class="form-check-input" id="status_late" name="default_status" value="late">
                                <label class="form-check-label" for="status_late">
                                    <span class="badge bg-warning">Late</span>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="radio" class="form-check-input" id="status_half_day" name="default_status" value="half_day">
                                <label class="form-check-label" for="status_half_day">
                                    <span class="badge bg-info">Half Day</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="{{ route('attendance.index') }}" class="btn btn-secondary me-md-2">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Mark Attendance
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Instructions -->
    <div class="card mt-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="bi bi-info-circle"></i> Instructions</h5>
        </div>
        <div class="card-body">
            <ol>
                <li>Select the date for which you want to mark attendance</li>
                <li>Enter the subject name</li>
                <li>Select the period (optional)</li>
                <li>Choose one or more classes from the list</li>
                <li>Select the default attendance status for all students</li>
                <li>Click "Mark Attendance" to record attendance for all students in selected classes</li>
            </ol>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> <strong>Note:</strong> This will mark attendance for ALL students in the selected classes with the default status. Use carefully.
            </div>
        </div>
    </div>
</div>
@endsection