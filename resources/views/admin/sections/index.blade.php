@extends('layouts.app')

@section('title', 'Sections Management')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Sections Management</h1>
        <a href="{{ route('admin.sections.create') }}" class="btn btn-primary">Create New Section</a>
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
                            <th>Capacity</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sections as $section)
                        <tr>
                            <td>{{ $section->id }}</td>
                            <td>{{ $section->name }}</td>
                            <td>{{ $section->capacity ?? 'Unlimited' }}</td>
                            <td>{{ Str::limit($section->description, 50) }}</td>
                            <td>
                                @if($section->trashed())
                                    <span class="badge bg-danger">Deleted</span>
                                @else
                                    <span class="badge bg-success">Active</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="{{ route('admin.sections.show', $section) }}" class="btn btn-info">View</a>
                                    <a href="{{ route('admin.sections.edit', $section) }}" class="btn btn-warning">Edit</a>
                                    
                                    @if(!$section->trashed())
                                        <form action="{{ route('admin.sections.destroy', $section) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                                        </form>
                                    @else
                                        <a href="{{ route('admin.sections.restore', $section->id) }}" class="btn btn-success">Restore</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center">No sections found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $sections->links() }}
        </div>
    </div>
</div>
@endsection