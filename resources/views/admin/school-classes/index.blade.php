@extends('layouts.admin')

@section('title', 'School Classes Management')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1>School Classes Management</h1>
        <div>
            <a href="{{ route('admin.school-classes.create') }}" class="btn btn-primary">Create New Class</a>
        </div>
    </div>

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

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Order</th>
                            <th>Section</th>
                            <th>Academic Session</th>
                            <th>Class Teacher</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($schoolClasses as $schoolClass)
                        <tr>
                            <td>{{ $schoolClass->id }}</td>
                            <td>{{ $schoolClass->name }}</td>
                            <td>{{ $schoolClass->class_order }}</td>
                            <td>{{ $schoolClass->section->name ?? '—' }}</td>
                            <td>{{ $schoolClass->academicSession->name ?? '—' }}</td>
                            <td>{{ $schoolClass->teacher->name ?? '—' }}</td>
                            <td>
                                @if($schoolClass->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif

                                @if($schoolClass->trashed())
                                    <span class="badge bg-danger">Deleted</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="{{ route('admin.school-classes.show', $schoolClass) }}" class="btn btn-info">View</a>

                                    @if(!$schoolClass->trashed())
                                        <a href="{{ route('admin.school-classes.edit', $schoolClass) }}" class="btn btn-warning">Edit</a>
                                        <form action="{{ route('admin.school-classes.destroy', $schoolClass) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                                        </form>
                                    @else
                                        <form action="{{ route('admin.school-classes.restore', $schoolClass->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-success">Restore</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center">No classes found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $schoolClasses->links() }}
        </div>
    </div>
</div>
@endsection
