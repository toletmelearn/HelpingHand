@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">Grading Systems</h1>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Grading Systems</h6>
                    <a href="{{ route('admin.grading-systems.create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Add New Grading System
                    </a>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif
                    
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Grade</th>
                                    <th>Min %</th>
                                    <th>Max %</th>
                                    <th>Points</th>
                                    <th>Status</th>
                                    <th>Order</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($gradingSystems as $gradingSystem)
                                    <tr>
                                        <td>{{ $gradingSystem->name }}</td>
                                        <td>{{ $gradingSystem->grade }}</td>
                                        <td>{{ $gradingSystem->min_percentage }}%</td>
                                        <td>{{ $gradingSystem->max_percentage ? $gradingSystem->max_percentage.'%' : 'âˆž' }}</td>
                                        <td>{{ $gradingSystem->grade_points ?: '-' }}</td>
                                        <td>
                                            <span class="badge bg-{{ $gradingSystem->is_active ? 'success' : 'secondary' }}">
                                                {{ $gradingSystem->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td>{{ $gradingSystem->order }}</td>
                                        <td>
                                            <a href="{{ route('admin.grading-systems.show', $gradingSystem->id) }}" class="btn btn-sm btn-info">View</a>
                                            <a href="{{ route('admin.grading-systems.edit', $gradingSystem->id) }}" class="btn btn-sm btn-warning">Edit</a>
                                            <form action="{{ route('admin.grading-systems.destroy', $gradingSystem->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this grading system?')">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center">No grading systems found</td>
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
