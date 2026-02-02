@extends('layouts.admin')

@section('title', 'View Biometric Record')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="bi bi-fingerprint"></i> View Biometric Record
            </h1>
            
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title">Biometric Record Details</h5>
                        <a href="{{ route('admin.teacher-biometrics.index') }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Records
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Teacher</label>
                                <p class="form-control-plaintext">{{ $record->teacher->name ?? 'N/A' }}</p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Date</label>
                                <p class="form-control-plaintext">{{ $record->date->format('d-m-Y') }}</p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">First In Time</label>
                                <p class="form-control-plaintext">{{ $record->first_in_time ?? 'N/A' }}</p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Last Out Time</label>
                                <p class="form-control-plaintext">{{ $record->last_out_time ?? 'N/A' }}</p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Calculated Duration</label>
                                <p class="form-control-plaintext">{{ number_format($record->calculated_duration, 2) }} hours</p>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Arrival Status</label>
                                <p class="form-control-plaintext">
                                    <span class="badge 
                                        @if($record->arrival_status === 'on_time') bg-success 
                                        @elseif($record->arrival_status === 'late') bg-warning 
                                        @else bg-secondary @endif">
                                        {{ ucfirst(str_replace('_', ' ', $record->arrival_status)) }}
                                    </span>
                                </p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Departure Status</label>
                                <p class="form-control-plaintext">
                                    <span class="badge 
                                        @if($record->departure_status === 'on_time') bg-success 
                                        @elseif($record->departure_status === 'early_exit') bg-warning 
                                        @elseif($record->departure_status === 'half_day') bg-info 
                                        @else bg-secondary @endif">
                                        {{ ucfirst(str_replace('_', ' ', $record->departure_status)) }}
                                    </span>
                                </p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Late Minutes</label>
                                <p class="form-control-plaintext">{{ $record->late_minutes ?? 0 }} minutes</p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Early Departure Minutes</label>
                                <p class="form-control-plaintext">{{ $record->early_departure_minutes ?? 0 }} minutes</p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Remarks</label>
                                <p class="form-control-plaintext">{{ $record->remarks ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card-footer">
                    <div class="d-flex gap-2">
                        <a href="{{ route('admin.teacher-biometrics.edit', $record) }}" class="btn btn-primary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <form action="{{ route('admin.teacher-biometrics.destroy', $record) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this record?')">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
