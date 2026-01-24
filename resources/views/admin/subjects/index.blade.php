@extends('layouts.app')

@section('title', 'Subjects Management')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Subjects Management</h1>
        <a href="{{ route('admin.subjects.create') }}" class="btn btn-primary">Create New Subject</a>
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
                            <th>Code</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($subjects as $subject)
                        <tr>
                            <td>{{ $subject->id }}</td>
                            <td>{{ $subject->name }}</td>
                            <td>{{ $subject->code }}</td>
                            <td>{{ Str::limit($subject->description, 50) }}</td>
                            <td>
                                @if($subject->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                                
                                @if($subject->trashed())
                                    <span class="badge bg-danger">Deleted</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="{{ route('admin.subjects.show', $subject) }}" class="btn btn-info">View</a>
                                    <a href="{{ route('admin.subjects.edit', $subject) }}" class="btn btn-warning">Edit</a>
                                    
                                    @if(!$subject->trashed())
                                        <form action="{{ route('admin.subjects.destroy', $subject) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                                        </form>
                                    @else
                                        <a href="{{ route('admin.subjects.restore', $subject->id) }}" class="btn btn-success">Restore</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center">No subjects found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $subjects->links() }}
        </div>
    </div>
</div>
@endsection