@extends('layouts.admin')

@section('title', 'Edit Biometric Record')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="bi bi-pencil"></i> Edit Biometric Record
            </h1>
            
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title">Update Biometric Record</h5>
                        <a href="{{ route('admin.teacher-biometrics.index') }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Records
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.teacher-biometrics.update', $record) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="teacher_id" class="form-label">Teacher *</label>
                                    <select name="teacher_id" id="teacher_id" class="form-select @error('teacher_id') is-invalid @enderror" required>
                                        <option value="">Select Teacher</option>
                                        @foreach($teachers as $teacher)
                                            <option value="{{ $teacher->id }}" {{ old('teacher_id', $record->teacher_id) == $teacher->id ? 'selected' : '' }}>
                                                {{ $teacher->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('teacher_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="date" class="form-label">Date *</label>
                                    <input type="date" name="date" id="date" class="form-control @error('date') is-invalid @enderror" 
                                           value="{{ old('date', $record->date->format('Y-m-d')) }}" required>
                                    @error('date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="first_in_time" class="form-label">First In Time</label>
                                    <input type="time" name="first_in_time" id="first_in_time" class="form-control @error('first_in_time') is-invalid @enderror" 
                                           value="{{ old('first_in_time', $record->first_in_time) }}">
                                    @error('first_in_time')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="last_out_time" class="form-label">Last Out Time</label>
                                    <input type="time" name="last_out_time" id="last_out_time" class="form-control @error('last_out_time') is-invalid @enderror" 
                                           value="{{ old('last_out_time', $record->last_out_time) }}">
                                    @error('last_out_time')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="remarks" class="form-label">Remarks</label>
                                    <textarea name="remarks" id="remarks" class="form-control @error('remarks') is-invalid @enderror" rows="3">{{ old('remarks', $record->remarks) }}</textarea>
                                    @error('remarks')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Calculated Duration</label>
                                    <p class="form-control-plaintext">{{ number_format($record->calculated_duration, 2) }} hours</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Update Record
                            </button>
                            <a href="{{ route('admin.teacher-biometrics.index') }}" class="btn btn-secondary">
                                <i class="bi bi-x"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
