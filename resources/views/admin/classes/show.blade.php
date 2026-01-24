@extends('layouts.app')

@section('title', 'Class Details')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Class Details: {{ $class->name }} {{ $class->section ? '(' . $class->section . ')' : '' }}</h4>
                    <div>
                        <a href="{{ route('classes.edit', $class) }}" class="btn btn-warning btn-sm">Edit</a>
                        <a href="{{ route('classes.index') }}" class="btn btn-secondary btn-sm">Back to List</a>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Basic Information</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>ID:</strong></td>
                                    <td>{{ $class->id }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Name:</strong></td>
                                    <td>{{ $class->name }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Section:</strong></td>
                                    <td>{{ $class->section ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Stream:</strong></td>
                                    <td>{{ $class->stream ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Capacity:</strong></td>
                                    <td>{{ $class->capacity }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <span class="badge {{ $class->is_active ? 'badge-success' : 'badge-danger' }}">
                                            {{ $class->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Description:</strong></td>
                                    <td>{{ $class->description ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Created At:</strong></td>
                                    <td>{{ $class->created_at->format('d-m-Y h:i A') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Updated At:</strong></td>
                                    <td>{{ $class->updated_at->format('d-m-Y h:i A') }}</td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="col-md-6">
                            <h5>Assigned Teachers</h5>
                            @if($teachers->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Teacher Name</th>
                                                <th>Role</th>
                                                <th>Primary?</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($teachers as $teacher)
                                                <tr>
                                                    <td>{{ $teacher->name }}</td>
                                                    <td>{{ ucfirst(str_replace('_', ' ', $teacher->pivot->role)) }}</td>
                                                    <td>{{ $teacher->pivot->is_primary ? 'Yes' : 'No' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p>No teachers assigned to this class.</p>
                            @endif
                            
                            <h5 class="mt-4">Student Count</h5>
                            <p>Total Students: <strong>{{ $class->students->count() }}</strong></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection