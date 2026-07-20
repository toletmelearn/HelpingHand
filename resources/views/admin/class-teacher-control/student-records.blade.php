@extends('layouts.admin')

@section('title', 'Student Records - Class Teacher Control')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-user-graduate"></i> Student Records
                    </h4>
                    @can('updateClassStudent', new \App\Models\Student())
                    <a href="{{ route('admin.students.create') }}" class="btn btn-light">
                        <i class="fas fa-plus"></i> Add Student
                    </a>
                    @endcan
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <form method="GET" action="{{ route('admin.class-teacher-control.student-records') }}" class="mb-4">
                        <div class="row">
                            <div class="col-md-3">
                                <select name="class_id" class="form-control">
                                    <option value="">All Classes</option>
                                    @foreach($classes as $class)
                                        <option value="{{ $class->id }}" {{ request('class_id') == $class->id ? 'selected' : '' }}>
                                            {{ $class->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="section_id" class="form-control">
                                    <option value="">All Sections</option>
                                    @foreach($sections as $section)
                                        <option value="{{ $section->id }}" {{ request('section_id') == $section->id ? 'selected' : '' }}>
                                            {{ $section->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <a href="{{ route('admin.class-teacher-control.student-records') }}" class="btn btn-secondary">Reset</a>
                            </div>
                        </div>
                    </form>

                    <!-- Students Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Admission No.</th>
                                    <th>Name</th>
                                    <th>Class</th>
                                    <th>Section</th>
                                    <th>Phone</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($students as $student)
                                <tr>
                                    <td>{{ $student->admission_no }}</td>
                                    <td>{{ $student->name }}</td>
                                    <td>{{ $student->class->name ?? 'N/A' }}</td>
                                    <td>{{ $student->section->name ?? 'N/A' }}</td>
                                    <td>{{ $student->phone ?? 'N/A' }}</td>
                                    <td class="text-nowrap">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="studentActions{{ $student->id }}" data-bs-toggle="dropdown" aria-expanded="false">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="studentActions{{ $student->id }}">
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('admin.students.show', $student) }}">
                                                        <i class="fas fa-eye me-2"></i> View
                                                    </a>
                                                </li>
                                                @can('updateClassStudent', $student)
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('admin.class-teacher-control.edit-student', $student->id) }}">
                                                        <i class="fas fa-edit me-2"></i> Edit
                                                    </a>
                                                </li>
                                                @endcan
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('admin.audit-logs.student-history', $student->id) }}">
                                                        <i class="fas fa-history me-2"></i> View History
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center">No students found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-center">
                        {{ $students->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
