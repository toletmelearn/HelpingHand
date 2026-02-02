@extends('layouts.admin')

@section('title', 'Classes Management')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Classes Management</h4>
                    <a href="{{ route('admin.classes.create') }}" class="btn btn-primary float-right">
                        <i class="fas fa-plus"></i> Add New Class
                    </a>
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

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Capacity</th>
                                    <th>Description</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($classes as $class)
                                    <tr>
                                        <td>{{ $class->id }}</td>
                                        <td>{{ $class->name }}</td>
                                        <td>{{ $class->capacity ?: 'Unlimited' }}</td>
                                        <td>{{ Str::limit($class->description, 50) }}</td>
                                        <td>{{ $class->created_at->format('d M Y') }}</td>
                                        <td>
                                            <a href="{{ route('admin.classes.show', $class->id) }}" class="btn btn-sm btn-info">View</a>
                                            <a href="{{ route('admin.classes.edit', $class->id) }}" class="btn btn-sm btn-primary">Edit</a>
                                            <form action="{{ route('admin.classes.destroy', $class->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this class?')">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No classes found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-center">
                        {{ $classes->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
