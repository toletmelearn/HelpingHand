@extends('layouts.admin')

@section('title', 'Class Teacher Assignment Details')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-chalkboard-teacher"></i> Class Teacher Assignment Details</h4>
                    <div>
                        <a href="{{ route('admin.class-teacher-assignments.index') }}" class="btn btn-light me-2">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                        <a href="{{ route('admin.class-teacher-assignments.edit', $assignment) }}" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th>ID:</th>
                                    <td>{{ $assignment->id }}</td>
                                </tr>
                                <tr>
                                    <th>Teacher:</th>
                                    <td>{{ $assignment->getClassTeacherName() }}</td>
                                </tr>
                                <tr>
                                    <th>Assigned Class:</th>
                                    <td>{{ $assignment->assigned_class }}</td>
                                </tr>
                                <tr>
                                    <th>Start Date:</th>
                                    <td>{{ $assignment->start_date ? $assignment->start_date->format('Y-m-d') : 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>End Date:</th>
                                    <td>{{ $assignment->end_date ? $assignment->end_date->format('Y-m-d') : 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <span class="badge {{ $assignment->isActive() ? 'bg-success' : 'bg-secondary' }}">
                                            {{ $assignment->isActive() ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Created At:</th>
                                    <td>{{ $assignment->created_at->format('Y-m-d H:i:s') }}</td>
                                </tr>
                                <tr>
                                    <th>Updated At:</th>
                                    <td>{{ $assignment->updated_at->format('Y-m-d H:i:s') }}</td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="alert alert-info">
                                <h5><i class="fas fa-info-circle"></i> Assignment Information</h5>
                                <p>This assignment indicates that <strong>{{ $assignment->getClassTeacherName() }}</strong> is designated as the class teacher for <strong>{{ $assignment->assigned_class }}</strong>.</p>
                                
                                <p><strong>Current Status:</strong> 
                                    @if($assignment->isCurrentlyAssigned())
                                        <span class="text-success">Currently Assigned</span>
                                        <br><small class="text-muted">This teacher is actively serving as class teacher for this class.</small>
                                    @else
                                        <span class="text-warning">Not Currently Assigned</span>
                                        <br><small class="text-muted">This assignment is not active for the current date.</small>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <form action="{{ route('admin.class-teacher-assignments.destroy', $assignment) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this assignment?')">
                                <i class="fas fa-trash"></i> Delete Assignment
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
