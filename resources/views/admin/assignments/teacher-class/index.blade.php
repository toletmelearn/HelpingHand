@extends('layouts.admin')

@section('title', 'Teacher-Class Assignments')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Teacher-Class Assignments</h4>
                    <a href="{{ route('admin.teacher-class-assignments.create') }}" class="btn btn-primary float-right">
                        <i class="fas fa-plus"></i> Assign Teacher to Class
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
                                    <th>Teacher</th>
                                    <th>Class</th>
                                    <th>Role</th>
                                    <th>Assigned At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($assignments as $assignment)
                                    <tr>
                                        <td>{{ $assignment->id }}</td>
                                        <td>{{ $assignment->teacher->name ?? 'N/A' }}</td>
                                        <td>{{ $assignment->class->name ?? 'N/A' }}</td>
                                        <td>
                                            <span class="badge badge-{{ $assignment->role === 'class_teacher' ? 'primary' : ($assignment->role === 'subject_teacher' ? 'success' : 'warning') }}">
                                                {{ $assignment->getRoleLabel() }}
                                            </span>
                                            @if($assignment->is_primary)
                                                <span class="badge badge-info ml-1">Primary</span>
                                            @endif
                                        </td>
                                        <td>{{ $assignment->assigned_at ? $assignment->assigned_at->format('d M Y') : 'N/A' }}</td>
                                        <td>
                                            <a href="{{ route('admin.teacher-class-assignments.edit', $assignment->id) }}" class="btn btn-sm btn-primary mr-1">Edit</a>
                                            <form action="{{ route('admin.teacher-class-assignments.destroy', $assignment->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to remove this assignment?')">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No assignments found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-center">
                        {{ $assignments->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
