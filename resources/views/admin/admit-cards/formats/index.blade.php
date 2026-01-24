@extends('layouts.app')

@section('title', 'Admit Card Format Management')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Admit Card Format Management</h4>
                    <a href="{{ route('admin.admit-card-formats.create') }}" class="btn btn-primary">Add New Format</a>
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
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($formats as $format)
                                <tr>
                                    <td>{{ $format->id }}</td>
                                    <td>{{ $format->name }}</td>
                                    <td>
                                        <span class="badge bg-{{ $format->is_active ? 'success' : 'secondary' }}">
                                            {{ $format->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>{{ $format->creator->name ?? 'N/A' }}</td>
                                    <td>{{ $format->created_at->format('d M Y h:i A') }}</td>
                                    <td>
                                        <a href="{{ route('admin.admit-card-formats.show', $format) }}" class="btn btn-sm btn-info">View</a>
                                        <a href="{{ route('admin.admit-card-formats.edit', $format) }}" class="btn btn-sm btn-warning">Edit</a>
                                        <form action="{{ route('admin.admit-card-formats.destroy', $format) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this format?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center">No admit card formats found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $formats->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection