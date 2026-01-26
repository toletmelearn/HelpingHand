@extends('layouts.app')

@section('title', 'Student Change History - Audit Logs')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-history"></i> Change History - {{ $student->name }}
                    </h4>
                    <span class="badge badge-light">Admission No: {{ $student->admission_no }}</span>
                </div>
                <div class="card-body">
                    <!-- Student Info Summary -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6><i class="fas fa-user"></i> Student Information</h6>
                                    <p class="mb-1"><strong>Name:</strong> {{ $student->name }}</p>
                                    <p class="mb-1"><strong>Admission No:</strong> {{ $student->admission_no }}</p>
                                    <p class="mb-1"><strong>Class:</strong> {{ $student->class->name ?? 'N/A' }}</p>
                                    <p class="mb-1"><strong>Section:</strong> {{ $student->section->name ?? 'N/A' }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6><i class="fas fa-file-alt"></i> Record Statistics</h6>
                                    <p class="mb-1"><strong>Total Changes:</strong> {{ $logs->count() }}</p>
                                    <p class="mb-1"><strong>First Change:</strong> 
                                        @if($logs->count() > 0)
                                            {{ $logs->sortByDesc('logged_at')->last()->logged_at->format('d M Y h:i A') }}
                                        @else
                                            N/A
                                        @endif
                                    </p>
                                    <p class="mb-1"><strong>Last Change:</strong> 
                                        @if($logs->count() > 0)
                                            {{ $logs->first()->logged_at->format('d M Y h:i A') }}
                                        @else
                                            N/A
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Change History Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Field Changed</th>
                                    <th>Old Value</th>
                                    <th>New Value</th>
                                    <th>Changed By</th>
                                    <th>Role</th>
                                    <th>Action</th>
                                    <th>Date & Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $log)
                                <tr>
                                    <td>{{ $log->field_name }}</td>
                                    <td class="text-truncate" style="max-width: 200px;" title="{{ $log->old_value }}">
                                        {{ Str::limit($log->old_value, 50) }}
                                    </td>
                                    <td class="text-truncate" style="max-width: 200px;" title="{{ $log->new_value }}">
                                        {{ Str::limit($log->new_value, 50) }}
                                    </td>
                                    <td>{{ $log->user_name }}</td>
                                    <td>
                                        <span class="badge 
                                            @if($log->user_type === 'admin') bg-primary
                                            @elseif($log->user_type === 'class-teacher') bg-success
                                            @elseif($log->user_type === 'teacher') bg-info
                                            @else bg-secondary
                                            @endif
                                        ">
                                            {{ ucfirst(str_replace('-', ' ', $log->user_type)) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge 
                                            @if($log->action === 'create') bg-success
                                            @elseif($log->action === 'update') bg-warning
                                            @elseif($log->action === 'delete') bg-danger
                                            @else bg-secondary
                                            @endif
                                        ">
                                            {{ $log->getReadableAction() }}
                                        </span>
                                    </td>
                                    <td>{{ $log->logged_at->format('d M Y h:i A') }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center">No changes recorded for this student.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Navigation -->
                    <div class="d-flex justify-content-between mt-4">
                        <a href="{{ route('admin.class-teacher-control.student-records') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Student Records
                        </a>
                        <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-outline-primary">
                            <i class="fas fa-history"></i> View All Audit Logs
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection