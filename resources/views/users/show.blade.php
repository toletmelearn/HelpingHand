@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h2>User Details - {{ $user->name }}</h2>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>User Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Name:</strong> {{ $user->name }}</p>
                            <p><strong>Email:</strong> {{ $user->email }}</p>
                            <p><strong>Phone:</strong> {{ $user->phone ?? 'N/A' }}</p>
                            <p><strong>Address:</strong> {{ $user->address ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Role:</strong> 
                                @if($user->roles->count() > 0)
                                    <span class="badge bg-primary">{{ ucfirst($user->roles->first()->name) }}</span>
                                @else
                                    <span class="badge bg-secondary">No Role Assigned</span>
                                @endif
                            </p>
                            <p><strong>Created:</strong> {{ $user->created_at->format('M d, Y g:i A') }}</p>
                            <p><strong>Last Login:</strong> {{ $user->last_login_at ? $user->last_login_at->format('M d, Y g:i A') : 'Never' }}</p>
                            <p><strong>Status:</strong> 
                                <span class="badge bg-{{ $user->status === 'active' ? 'success' : 'warning' }}">
                                    {{ ucfirst($user->status ?? 'active') }}
                                </span>
                            </p>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <a href="{{ route('users.edit', $user) }}" class="btn btn-warning">Edit User</a>
                        <a href="{{ route('users.index') }}" class="btn btn-secondary">Back to Users</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection