@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h2>User Management</h2>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="{{ route('users.create') }}" class="btn btn-primary">Add New User</a>
                
                <!-- Search and Filter Form -->
                <form method="GET" action="{{ route('users.index') }}" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control" placeholder="Search users..." 
                           value="{{ request('search') }}">
                    <select name="role" class="form-select">
                        <option value="">All Roles</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}" 
                                {{ request('role') == $role->name ? 'selected' : '' }}>
                                {{ ucfirst($role->name) }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-outline-secondary">Filter</button>
                    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Clear</a>
                </form>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Phone</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($users as $user)
                                    <tr>
                                        <td>{{ $user->id }}</td>
                                        <td>{{ $user->name }}</td>
                                        <td>{{ $user->email }}</td>
                                        <td>
                                            @if($user->roles->count() > 0)
                                                <span class="badge bg-primary">
                                                    {{ ucfirst($user->roles->first()->name) }}
                                                </span>
                                            @else
                                                <span class="badge bg-secondary">No Role</span>
                                            @endif
                                        </td>
                                        <td>{{ $user->phone ?? 'N/A' }}</td>
                                        <td>{{ $user->created_at->format('M d, Y') }}</td>
                                        <td>
                                            <a href="{{ route('users.show', $user) }}" class="btn btn-sm btn-info">View</a>
                                            <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-warning">Edit</a>
                                            <form method="POST" action="{{ route('users.destroy', $user) }}" 
                                                  class="d-inline" 
                                                  onsubmit="return confirm('Are you sure you want to delete this user?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">No users found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="d-flex justify-content-center">
                        {{ $users->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
