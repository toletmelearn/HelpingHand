@extends('layouts.admin')

@section('title', 'Absence Overview - Admin Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-user-slash"></i> Absent Teachers Today
                    </h4>
                </div>
                <div class="card-body">
                    @if($absentTeachers->count() > 0)
                        <div class="list-group">
                            @foreach($absentTeachers as $teacher)
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h5 class="mb-1">{{ $teacher->user->name ?? 'N/A' }}</h5>
                                        <small>Teacher ID: {{ $teacher->teacher_id }}</small>
                                    </div>
                                    <p class="mb-1">
                                        <i class="fas fa-chalkboard-teacher"></i> 
                                        @foreach($teacher->absentSubstitutions as $sub)
                                            {{ $sub->class->name }}-{{ $sub->section->name }}: {{ $sub->subject->name }} (P{{ $sub->period_number }})
                                            @if(!$loop->last), @endif
                                        @endforeach
                                    </p>
                                    <small class="text-muted">
                                        <i class="fas fa-comment"></i> {{ $teacher->absentSubstitutions->first()->reason ?: 'No reason specified' }}
                                    </small>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-3">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <p class="text-muted">No teachers are absent today.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-user-clock"></i> Substitute Teachers Today
                    </h4>
                </div>
                <div class="card-body">
                    @if($substitutedTeachers->count() > 0)
                        <div class="list-group">
                            @foreach($substitutedTeachers as $teacher)
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h5 class="mb-1">{{ $teacher->user->name ?? 'N/A' }}</h5>
                                        <small>Teacher ID: {{ $teacher->teacher_id }}</small>
                                    </div>
                                    <p class="mb-1">
                                        <i class="fas fa-chalkboard-teacher"></i> 
                                        @foreach($teacher->substituteSubstitutions as $sub)
                                            {{ $sub->class->name }}-{{ $sub->section->name }}: {{ $sub->subject->name }} (P{{ $sub->period_number }})
                                            @if(!$loop->last), @endif
                                        @endforeach
                                    </p>
                                    <small class="text-muted">
                                        Status: <span class="badge badge-success">{{ $teacher->substituteSubstitutions->first()->getReadableStatus() }}</span>
                                    </small>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-3">
                            <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                            <p class="text-muted">No substitute teachers assigned today.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0">
                        <i class="fas fa-exclamation-triangle"></i> Pending Substitutions
                    </h4>
                </div>
                <div class="card-body">
                    <p class="text-muted">These substitutions need immediate attention and assignment.</p>
                    
                    <!-- Would show pending substitutions here if implemented -->
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Note:</strong> This section would show pending substitutions that need to be assigned to substitute teachers.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
