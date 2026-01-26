@extends('layouts.app')

@section('title', 'Audit Logs')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-history"></i> Audit Logs</h4>
                </div>
                
                <div class="card-body">
                    <!-- Filters -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <select name="user_id" class="form-control" onchange="location = this.value;">
                                <option value="{{ route('admin.audit-logs.index') }}">All Users</option>
                                @foreach($users as $user)
                                    <option value="{{ route('admin.audit-logs.index', ['user_id' => $user->id]) }}" 
                                        {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                        {{ $user->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <select name="model_type" class="form-control" onchange="location = this.value;">
                                <option value="{{ route('admin.audit-logs.index') }}">All Models</option>
                                <option value="{{ route('admin.audit-logs.index', ['model_type' => 'student']) }}" 
                                    {{ request('model_type') == 'student' ? 'selected' : '' }}>
                                    Student
                                </option>
                                <option value="{{ route('admin.audit-logs.index', ['model_type' => 'teacher']) }}" 
                                    {{ request('model_type') == 'teacher' ? 'selected' : '' }}>
                                    Teacher
                                </option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <select name="action" class="form-control" onchange="location = this.value;">
                                <option value="{{ route('admin.audit-logs.index') }}">All Actions</option>
                                <option value="{{ route('admin.audit-logs.index', ['action' => 'create']) }}" 
                                    {{ request('action') == 'create' ? 'selected' : '' }}>
                                    Create
                                </option>
                                <option value="{{ route('admin.audit-logs.index', ['action' => 'update']) }}" 
                                    {{ request('action') == 'update' ? 'selected' : '' }}>
                                    Update
                                </option>
                                <option value="{{ route('admin.audit-logs.index', ['action' => 'delete']) }}" 
                                    {{ request('action') == 'delete' ? 'selected' : '' }}>
                                    Delete
                                </option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <input type="date" name="start_date" class="form-control me-2" 
                                   value="{{ request('start_date') }}" 
                                   onchange="location = this.value ? this.form.action + '?start_date=' + this.value : this.form.action;">
                        </div>
                        
                        <div class="col-md-2">
                            <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-outline-secondary w-100">
                                Clear Filters
                            </a>
                        </div>
                    </div>
                    
                    <!-- Audit Logs Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Model</th>
                                    <th>Model ID</th>
                                    <th>Field</th>
                                    <th>Action</th>
                                    <th>Old Value</th>
                                    <th>New Value</th>
                                    <th>Date & Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $log)
                                    <tr>
                                        <td>{{ $log->id }}</td>
                                        <td>{{ $log->user_type }} #{{ $log->user_id }}</td>
                                        <td>{{ ucfirst($log->model_type) }}</td>
                                        <td>{{ $log->model_id }}</td>
                                        <td>{{ $log->field_name ?: 'N/A' }}</td>
                                        <td>
                                            <span class="badge 
                                                @if($log->action === 'create') bg-success
                                                @elseif($log->action === 'update') bg-warning
                                                @elseif($log->action === 'delete') bg-danger
                                                @else bg-secondary @endif">
                                                {{ $log->getReadableAction() }}
                                            </span>
                                        </td>
                                        <td>{{ Str::limit($log->old_value, 50) }}</td>
                                        <td>{{ Str::limit($log->new_value, 50) }}</td>
                                        <td>{{ $log->performed_at->format('Y-m-d H:i:s') }}</td>
                                        <td>
                                            <a href="{{ route('admin.audit-logs.show', $log) }}" 
                                               class="btn btn-sm btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center">No audit logs found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="d-flex justify-content-center">
                        {{ $logs->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection