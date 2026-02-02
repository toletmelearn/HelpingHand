@extends('layouts.admin')

@section('title', 'Manage Academic Sessions')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Academic Session Management</h4>
                    <a href="{{ route('academic-sessions.create') }}" class="btn btn-primary">Add New Session</a>
                </div>

                <div class="card-body">
                    <!-- Search and Filter Form -->
                    <form method="GET" action="{{ route('academic-sessions.index') }}" class="mb-4">
                        <div class="row">
                            <div class="col-md-3">
                                <input type="text" name="search" class="form-control" placeholder="Search sessions..." 
                                       value="{{ request('search') }}">
                            </div>
                            <div class="col-md-2">
                                <select name="status" class="form-control">
                                    <option value="">All Statuses</option>
                                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="current" class="form-control">
                                    <option value="">All Sessions</option>
                                    <option value="yes" {{ request('current') == 'yes' ? 'selected' : '' }}>Current</option>
                                    <option value="no" {{ request('current') == 'no' ? 'selected' : '' }}>Not Current</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Search</button>
                            </div>
                            <div class="col-md-2">
                                <a href="{{ route('academic-sessions.index') }}" class="btn btn-secondary w-100">Clear</a>
                            </div>
                        </div>
                    </form>

                    <!-- Success/Error Messages -->
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

                    <!-- Academic Sessions Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="thead-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Code</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Current</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($sessions as $session)
                                    <tr>
                                        <td>{{ $session->id }}</td>
                                        <td>{{ $session->name }}</td>
                                        <td>{{ $session->code }}</td>
                                        <td>{{ $session->start_date->format('d-m-Y') }}</td>
                                        <td>{{ $session->end_date->format('d-m-Y') }}</td>
                                        <td>
                                            @if($session->is_current)
                                                <span class="badge badge-success">Current</span>
                                            @else
                                                <span class="badge badge-secondary">Not Current</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge {{ $session->is_active ? 'badge-success' : 'badge-danger' }}">
                                                {{ $session->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td>{{ $session->created_at->format('d-m-Y') }}</td>
                                        <td>
                                            <a href="{{ route('academic-sessions.show', $session) }}" class="btn btn-info btn-sm">View</a>
                                            <a href="{{ route('academic-sessions.edit', $session) }}" class="btn btn-warning btn-sm">Edit</a>
                                            
                                            @if(!$session->is_current)
                                                <a href="{{ route('academic-sessions.set-current', $session) }}" 
                                                   class="btn btn-primary btn-sm"
                                                   onclick="return confirm('Are you sure you want to set this session as current?')">Set Current</a>
                                            @endif
                                            
                                            <form action="{{ route('academic-sessions.destroy', $session) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm" 
                                                        onclick="return confirm('Are you sure you want to delete this academic session?')">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center">No academic sessions found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-center">
                        {{ $sessions->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
