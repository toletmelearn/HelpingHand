@extends('layouts.admin')

@section('title', 'Add Biometric Record')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="bi bi-plus-circle"></i> Add Biometric Record
            </h1>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Manual Record Entry</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.teacher-biometrics.store') }}" method="POST">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="teacher_id" class="form-label">Teacher *</label>
                                <select class="form-select" id="teacher_id" name="teacher_id" required>
                                    <option value="">Select Teacher</option>
                                    @foreach($teachers as $teacher)
                                        <option value="{{ $teacher->id }}" {{ old('teacher_id') == $teacher->id ? 'selected' : '' }}>
                                            {{ $teacher->name }} ({{ $teacher->employee_id }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('teacher_id')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="date" class="form-label">Date *</label>
                                <input type="date" class="form-control" id="date" name="date" value="{{ old('date', now()->toDateString()) }}" required>
                                @error('date')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_in_time" class="form-label">First In Time</label>
                                <input type="time" class="form-control" id="first_in_time" name="first_in_time" value="{{ old('first_in_time') }}">
                                @error('first_in_time')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="last_out_time" class="form-label">Last Out Time</label>
                                <input type="time" class="form-control" id="last_out_time" name="last_out_time" value="{{ old('last_out_time') }}">
                                @error('last_out_time')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="3">{{ old('remarks') }}</textarea>
                            @error('remarks')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="alert alert-info">
                            <h6 class="alert-heading">Current Settings</h6>
                            <p class="mb-0">
                                School Hours: {{ $settings->school_start_time_formatted ?? '08:00 AM' }} - {{ $settings->school_end_time_formatted ?? '04:00 PM' }}<br>
                                Grace Period: {{ $settings->grace_period_minutes ?? 15 }} minutes<br>
                                Half Day Minimum: {{ $settings->half_day_minimum_hours ?? 4 }} hours
                            </p>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.teacher-biometrics.index') }}" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Dashboard
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Record
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
