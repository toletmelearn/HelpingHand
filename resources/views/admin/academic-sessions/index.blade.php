@extends('layouts.admin')

@section('title', 'Academic Sessions Management')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Academic Sessions Management</h1>
        <a href="{{ route('admin.academic-sessions.create') }}" class="btn btn-primary">Create New Session</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Current</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($academicSessions as $session)
                        <tr>
                            <td>{{ $session->id }}</td>
                            <td>{{ $session->name }}</td>
                            <td>{{ $session->start_date->format('d-m-Y') }}</td>
                            <td>{{ $session->end_date->format('d-m-Y') }}</td>
                            <td>
                                @if($session->is_current)
                                    <span class="badge bg-primary">Current</span>
                                @else
                                    <span class="badge bg-secondary">Not Current</span>
                                @endif
                            </td>
                            <td>
                                @if($session->trashed())
                                    <span class="badge bg-danger">Deleted</span>
                                @else
                                    <span class="badge bg-success">Active</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="{{ route('admin.academic-sessions.show', $session) }}" class="btn btn-info">View</a>
                                    <a href="{{ route('admin.academic-sessions.edit', $session) }}" class="btn btn-warning">Edit</a>
                                    
                                    @if(!$session->trashed())
                                        <form action="{{ route('admin.academic-sessions.destroy', $session) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                                        </form>
                                    @else
                                        <a href="{{ route('admin.academic-sessions.restore', $session->id) }}" class="btn btn-success">Restore</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center">No academic sessions found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $academicSessions->links() }}
        </div>
    </div>
</div>
@endsection
