@extends('layouts.admin')

@section('title', 'Permission Details')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Permission Details: {{ $permission->name }}</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>ID:</label>
                                <p class="form-control-static">{{ $permission->id }}</p>
                            </div>
                            
                            <div class="form-group">
                                <label>Name:</label>
                                <p class="form-control-static">{{ $permission->name }}</p>
                            </div>
                            
                            <div class="form-group">
                                <label>Guard Name:</label>
                                <p class="form-control-static">{{ $permission->guard_name }}</p>
                            </div>
                            
                            <div class="form-group">
                                <label>Created At:</label>
                                <p class="form-control-static">{{ $permission->created_at->format('Y-m-d H:i:s') }}</p>
                            </div>
                            
                            <div class="form-group">
                                <label>Updated At:</label>
                                <p class="form-control-static">{{ $permission->updated_at->format('Y-m-d H:i:s') }}</p>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h5>Assigned Roles</h5>
                            @if($permission->roles->count() > 0)
                                <ul class="list-group">
                                    @foreach($permission->roles as $role)
                                        <li class="list-group-item">{{ $role->name }}</li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-muted">This permission is not assigned to any roles yet.</p>
                            @endif
                        </div>
                    </div>
                    
                    <div class="form-group mt-3">
                        <a href="{{ route('admin.permissions.index') }}" class="btn btn-secondary">Back to Permissions</a>
                        <a href="{{ route('admin.permissions.edit', $permission) }}" class="btn btn-warning">Edit Permission</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection