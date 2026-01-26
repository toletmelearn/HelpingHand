@extends('layouts.app')

@section('title', 'View Teacher Substitution - Admin Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-file-alt"></i> Teacher Substitution Details
                    </h4>
                    <span class="badge badge-light">
                        {{ $teacherSubstitution->substitution_date->format('d M Y') }} - 
                        Period {{ $teacherSubstitution->period_number }}
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <strong>Date:</strong>
                                <p class="text-muted">{{ $teacherSubstitution->substitution_date->format('d F Y (l)') }}</p>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Absent Teacher:</strong>
                                <p class="text-muted">
                                    {{ $teacherSubstitution->absentTeacher->user->name ?? 'N/A' }} 
                                    ({{ $teacherSubstitution->absentTeacher->teacher_id }})
                                </p>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Class:</strong>
                                <p class="text-muted">{{ $teacherSubstitution->class->name }}</p>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Section:</strong>
                                <p class="text-muted">{{ $teacherSubstitution->section->name }}</p>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Subject:</strong>
                                <p class="text-muted">{{ $teacherSubstitution->subject->name }}</p>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <strong>Period:</strong>
                                <p class="text-muted">
                                    {{ $teacherSubstitution->period_number }} 
                                    @if($teacherSubstitution->period_name)
                                        ({{ $teacherSubstitution->period_name }})
                                    @endif
                                </p>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Substitute Teacher:</strong>
                                <p class="text-muted">
                                    @if($teacherSubstitution->substituteTeacher)
                                        {{ $teacherSubstitution->substituteTeacher->user->name ?? 'N/A' }} 
                                        ({{ $teacherSubstitution->substituteTeacher->teacher_id }})
                                    @else
                                        <span class="badge badge-warning">Not Assigned</span>
                                    @endif
                                </p>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Status:</strong>
                                <p>
                                    <span class="badge 
                                        @if($teacherSubstitution->status == 'pending') badge-warning
                                        @elseif($teacherSubstitution->status == 'assigned') badge-info
                                        @elseif($teacherSubstitution->status == 'approved') badge-success
                                        @elseif($teacherSubstitution->status == 'cancelled') badge-danger
                                        @endif
                                    ">
                                        {{ $teacherSubstitution->getReadableStatus() }}
                                    </span>
                                </p>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Reason:</strong>
                                <p class="text-muted">{{ $teacherSubstitution->reason ?: 'Not specified' }}</p>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Created By:</strong>
                                <p class="text-muted">
                                    {{ $teacherSubstitution->createdBy->name ?? 'N/A' }}
                                    @if($teacherSubstitution->created_at)
                                        ({{ $teacherSubstitution->created_at->format('d M Y h:i A') }})
                                    @endif
                                </p>
                            </div>
                            
                            @if($teacherSubstitution->updatedBy)
                                <div class="mb-3">
                                    <strong>Last Updated By:</strong>
                                    <p class="text-muted">
                                        {{ $teacherSubstitution->updatedBy->name ?? 'N/A' }}
                                        @if($teacherSubstitution->updated_at)
                                            ({{ $teacherSubstitution->updated_at->format('d M Y h:i A') }})
                                        @endif
                                    </p>
                                </div>
                            @endif
                            
                            @if($teacherSubstitution->assigned_at)
                                <div class="mb-3">
                                    <strong>Assigned At:</strong>
                                    <p class="text-muted">{{ $teacherSubstitution->assigned_at->format('d M Y h:i A') }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between">
                        <a href="{{ route('admin.teacher-substitutions.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                        <div>
                            @if($teacherSubstitution->isPending() && $teacherSubstitution->substituteTeacher)
                                <a href="{{ route('admin.teacher-substitutions.assign', $teacherSubstitution) }}" class="btn btn-success">
                                    <i class="fas fa-user-check"></i> Assign Substitute
                                </a>
                            @endif
                            
                            @if($teacherSubstitution->isAssigned())
                                <a href="{{ route('admin.teacher-substitutions.approve', $teacherSubstitution) }}" class="btn btn-success">
                                    <i class="fas fa-check"></i> Approve Assignment
                                </a>
                            @endif
                            
                            @if(!$teacherSubstitution->isCancelled())
                                <a href="{{ route('admin.teacher-substitutions.cancel', $teacherSubstitution) }}" 
                                   class="btn btn-danger" 
                                   onclick="return confirm('Are you sure you want to cancel this substitution?')">
                                    <i class="fas fa-times"></i> Cancel Assignment
                                </a>
                            @endif
                            
                            <a href="{{ route('admin.teacher-substitutions.edit', $teacherSubstitution) }}" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection