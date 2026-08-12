@extends('layouts.admin')

@section('title', 'Class Teacher Assignment (Whole Class) - Admin Dashboard')

@section('content')
<div class="container-fluid">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <h4 class="mb-sm-0 font-size-18">Class Teacher Assignment (Whole Class)</h4>
        <div class="page-title-right">
            <ol class="breadcrumb m-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item active">Class Teacher Assignment (Whole Class)</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header text-end">
                    <a href="{{ route('admin.teacher-class-assignments.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Assign Teacher to Class
                    </a>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        This screen assigns a class teacher for a whole class only -- it has no section field and is <strong>not</strong> read by the Timetable module.
                        To assign a class teacher for a specific section (e.g. Class 3, Section B), use
                        @if(\Illuminate\Support\Facades\Route::has('admin.teacher-subject-assignments.index'))
                            <a href="{{ route('admin.teacher-subject-assignments.index') }}">Teacher-Subject Assignment</a>
                        @else
                            Teacher-Subject Assignment
                        @endif
                        instead, and check "Make Class Teacher" there.
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
