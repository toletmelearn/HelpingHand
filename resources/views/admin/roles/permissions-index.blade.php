@extends('layouts.app')

@section('title', 'Role & Permission Management')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Role & Permission Management</h4>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Role</th>
                                    <th>Display Name</th>
                                    <th>Description</th>
                                    <th>Permissions Count</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($roles as $role)
                                <tr>
                                    <td>{{ $role->name }}</td>
                                    <td>{{ $role->display_name ?? 'N/A' }}</td>
                                    <td>{{ $role->description ?? 'N/A' }}</td>
                                    <td>{{ $role->permissions->count() }}</td>
                                    <td>
                                        <a href="{{ route('admin.role-permissions.edit', $role->id) }}" class="btn btn-sm btn-warning">Manage Permissions</a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center">No roles found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection