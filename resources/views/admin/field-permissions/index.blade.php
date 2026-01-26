@extends('layouts.app')

@section('title', 'Field Permissions')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-shield-alt"></i> Field Permissions</h4>
                    <a href="{{ route('admin.field-permissions.create') }}" class="btn btn-light">
                        <i class="fas fa-plus"></i> Add New Permission
                    </a>
                </div>
                
                <div class="card-body">
                    <!-- Filters -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <select name="model_type" class="form-control" onchange="location = this.value;">
                                <option value="{{ route('admin.field-permissions.index') }}">All Models</option>
                                <option value="{{ route('admin.field-permissions.index', ['model_type' => 'student']) }}" 
                                    {{ request('model_type') == 'student' ? 'selected' : '' }}>
                                    Student
                                </option>
                                <option value="{{ route('admin.field-permissions.index', ['model_type' => 'teacher']) }}" 
                                    {{ request('model_type') == 'teacher' ? 'selected' : '' }}>
                                    Teacher
                                </option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <select name="role" class="form-control" onchange="location = this.value;">
                                <option value="{{ route('admin.field-permissions.index') }}">All Roles</option>
                                <option value="{{ route('admin.field-permissions.index', ['role' => 'admin']) }}" 
                                    {{ request('role') == 'admin' ? 'selected' : '' }}>
                                    Admin
                                </option>
                                <option value="{{ route('admin.field-permissions.index', ['role' => 'class_teacher']) }}" 
                                    {{ request('role') == 'class_teacher' ? 'selected' : '' }}>
                                    Class Teacher
                                </option>
                                <option value="{{ route('admin.field-permissions.index', ['role' => 'teacher']) }}" 
                                    {{ request('role') == 'teacher' ? 'selected' : '' }}>
                                    Teacher
                                </option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <select name="status" class="form-control" onchange="location = this.value;">
                                <option value="{{ route('admin.field-permissions.index') }}">All Status</option>
                                <option value="{{ route('admin.field-permissions.index', ['status' => 'active']) }}" 
                                    {{ request('status') == 'active' ? 'selected' : '' }}>
                                    Active
                                </option>
                                <option value="{{ route('admin.field-permissions.index', ['status' => 'inactive']) }}" 
                                    {{ request('status') == 'inactive' ? 'selected' : '' }}>
                                    Inactive
                                </option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <a href="{{ route('admin.field-permissions.index') }}" class="btn btn-outline-secondary w-100">
                                Clear Filters
                            </a>
                        </div>
                    </div>
                    
                    <!-- Permissions Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Model Type</th>
                                    <th>Field Name</th>
                                    <th>Role</th>
                                    <th>Permission Level</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($permissions as $permission)
                                    <tr>
                                        <td>{{ $permission->id }}</td>
                                        <td>{{ ucfirst($permission->model_type) }}</td>
                                        <td>{{ $permission->field_name }}</td>
                                        <td>{{ ucfirst(str_replace('_', ' ', $permission->role)) }}</td>
                                        <td>
                                            <span class="badge 
                                                @if($permission->permission_level === 'editable') bg-primary
                                                @elseif($permission->permission_level === 'read_only') bg-warning
                                                @else bg-danger @endif">
                                                {{ $permission->getReadablePermissionLevel() }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge {{ $permission->is_active ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $permission->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('admin.field-permissions.show', $permission) }}" 
                                                   class="btn btn-sm btn-info" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="{{ route('admin.field-permissions.edit', $permission) }}" 
                                                   class="btn btn-sm btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form action="{{ route('admin.field-permissions.destroy', $permission) }}" 
                                                      method="POST" class="d-inline" 
                                                      onsubmit="return confirm('Are you sure you want to delete this permission?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">No field permissions found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="d-flex justify-content-center">
                        {{ $permissions->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection