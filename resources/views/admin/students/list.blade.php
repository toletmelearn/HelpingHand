@extends('layouts.admin')

@section('title', 'All Students')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title">All Students</h4>
                    <div>
                        <a href="{{ route('admin.students.index') }}" class="btn btn-info me-2">
                            <i class="fas fa-th-list"></i> Class-Section View
                        </a>
                        <a href="{{ route('admin.students.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add New Student
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Admission No</th>
                                    <th>Class</th>
                                    <th>Section</th>
                                    <th>Roll No</th>
                                    <th>Phone</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($students as $student)
                                <tr>
                                    <td>{{ $student->id }}</td>
                                    <td>{{ $student->name }}</td>
                                    <td>{{ $student->admission_no ?: 'N/A' }}</td>
                                    <td>{{ $student->schoolClass->name ?? $student->class ?? 'N/A' }}</td>
                                    <td>{{ $student->section ?: 'N/A' }}</td>
                                    <td>{{ $student->roll_number ?: 'N/A' }}</td>
                                    <td>{{ $student->phone ?: 'N/A' }}</td>
                                    <td>
                                        <a href="{{ route('admin.students.show', $student->id) }}" 
                                           class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="{{ route('admin.students.edit', $student->id) }}" 
                                           class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <form action="{{ route('admin.students.destroy', $student->id) }}" 
                                              method="POST" style="display: inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" 
                                                    class="btn btn-sm btn-danger"
                                                    onclick="return confirm('Are you sure you want to delete this student?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center">No students found</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    @if(isset($students) && $students->count() > 0)
                    <div class="alert alert-info">
                        <strong>Total Students:</strong> {{ $students->count() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection