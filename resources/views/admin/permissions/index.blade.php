@extends('layouts.admin')

@section('title', 'Permissions')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">Permissions</h3>
                    <a href="{{ route('admin.permissions.create') }}" class="btn btn-primary">Add New Permission</a>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <form method="GET" class="row g-2 mb-3">
                        <div class="col-md-4">
                            <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search name or label...">
                        </div>
                        <div class="col-md-4">
                            <select name="module" class="form-select">
                                <option value="">All modules</option>
                                @foreach($modules as $module)
                                    <option value="{{ $module }}" {{ request('module') == $module ? 'selected' : '' }}>{{ ucwords(str_replace('-', ' ', $module)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
                        </div>
                        <div class="col-md-2">
                            <a href="{{ route('admin.permissions.index') }}" class="btn btn-outline-secondary w-100">Reset</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Label</th>
                                    <th>Module</th>
                                    <th>Guard Name</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($permissions as $permission)
                                    <tr>
                                        <td>{{ $permission->id }}</td>
                                        <td>{{ $permission->name }}</td>
                                        <td>{{ $permission->label ?? '—' }}</td>
                                        <td>
                                            @if($permission->module)
                                                <span class="badge bg-secondary">{{ ucwords(str_replace('-', ' ', $permission->module)) }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ $permission->guard_name }}</td>
                                        <td>
                                            <a href="{{ route('admin.permissions.show', $permission) }}" class="btn btn-sm btn-info">View</a>
                                            <a href="{{ route('admin.permissions.edit', $permission) }}" class="btn btn-sm btn-warning">Edit</a>
                                            <form action="{{ route('admin.permissions.destroy', $permission) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this permission?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No permissions found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-center">
                        {{ $permissions->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection