@extends('layouts.admin')

@section('title', 'Field Permission Details')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-shield-alt"></i> Field Permission Details</h4>
                    <div>
                        <a href="{{ route('admin.field-permissions.index') }}" class="btn btn-light me-2">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                        <a href="{{ route('admin.field-permissions.edit', $permission) }}" class="btn btn-warning">
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
                                    <td>{{ $permission->id }}</td>
                                </tr>
                                <tr>
                                    <th>Model Type:</th>
                                    <td>{{ ucfirst($permission->model_type) }}</td>
                                </tr>
                                <tr>
                                    <th>Field Name:</th>
                                    <td>{{ $permission->field_name }}</td>
                                </tr>
                                <tr>
                                    <th>Role:</th>
                                    <td>{{ ucfirst(str_replace('_', ' ', $permission->role)) }}</td>
                                </tr>
                                <tr>
                                    <th>Permission Level:</th>
                                    <td>
                                        <span class="badge 
                                            @if($permission->permission_level === 'editable') bg-primary
                                            @elseif($permission->permission_level === 'read_only') bg-warning
                                            @else bg-danger @endif">
                                            {{ $permission->getReadablePermissionLevel() }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <span class="badge {{ $permission->is_active ? 'bg-success' : 'bg-secondary' }}">
                                            {{ $permission->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Created At:</th>
                                    <td>{{ $permission->created_at->format('Y-m-d H:i:s') }}</td>
                                </tr>
                                <tr>
                                    <th>Updated At:</th>
                                    <td>{{ $permission->updated_at->format('Y-m-d H:i:s') }}</td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="alert alert-info">
                                <h5><i class="fas fa-info-circle"></i> Permission Information</h5>
                                <p>This permission defines that for the <strong>{{ ucfirst($permission->model_type) }}</strong> model, the field <strong>{{ $permission->field_name }}</strong> has the following access level for <strong>{{ ucfirst(str_replace('_', ' ', $permission->role)) }}</strong> role:</p>
                                
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6>Permission Level: <span class="badge 
                                            @if($permission->permission_level === 'editable') bg-primary
                                            @elseif($permission->permission_level === 'read_only') bg-warning
                                            @else bg-danger @endif">
                                            {{ $permission->getReadablePermissionLevel() }}
                                        </span></h6>
                                        
                                        @if($permission->permission_level === 'editable')
                                            <p class="mb-0">Users with this role can view and edit this field.</p>
                                        @elseif($permission->permission_level === 'read_only')
                                            <p class="mb-0">Users with this role can view this field but cannot edit it.</p>
                                        @else
                                            <p class="mb-0">Users with this role cannot see this field at all.</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <form action="{{ route('admin.field-permissions.destroy', $permission) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this permission?')">
                                <i class="fas fa-trash"></i> Delete Permission
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
