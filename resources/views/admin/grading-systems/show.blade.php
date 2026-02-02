@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">Grading System Details</h1>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Grading System: {{ $gradingSystem->name }}</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Basic Information</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Name:</strong></td>
                                    <td>{{ $gradingSystem->name }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Grade:</strong></td>
                                    <td>{{ $gradingSystem->grade }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Minimum Percentage:</strong></td>
                                    <td>{{ $gradingSystem->min_percentage }}%</td>
                                </tr>
                                <tr>
                                    <td><strong>Maximum Percentage:</strong></td>
                                    <td>{{ $gradingSystem->max_percentage ? $gradingSystem->max_percentage.'%' : 'âˆž' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Grade Points:</strong></td>
                                    <td>{{ $gradingSystem->grade_points ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Description:</strong></td>
                                    <td>{{ $gradingSystem->description ?: '-' }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Status Information</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <span class="badge bg-{{ $gradingSystem->is_active ? 'success' : 'secondary' }}">
                                            {{ $gradingSystem->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Display Order:</strong></td>
                                    <td>{{ $gradingSystem->order }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Created:</strong></td>
                                    <td>{{ $gradingSystem->created_at->format('M d, Y H:i') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Updated:</strong></td>
                                    <td>{{ $gradingSystem->updated_at->format('M d, Y H:i') }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="{{ route('admin.grading-systems.index') }}" class="btn btn-secondary">Back to List</a>
                        <div>
                            <a href="{{ route('admin.grading-systems.edit', $gradingSystem->id) }}" class="btn btn-warning">Edit</a>
                            <form action="{{ route('admin.grading-systems.destroy', $gradingSystem->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this grading system?')">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
