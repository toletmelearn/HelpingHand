@extends('layouts.admin')

@section('title', 'Class Teacher Assignments')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-chalkboard-teacher"></i> Class Teacher Assignments</h4>
                    <a href="{{ route('admin.class-teacher-assignments.create') }}" class="btn btn-light">
                        <i class="fas fa-plus"></i> Add New Assignment
                    </a>
                </div>
                
                <div class="card-body">
                    <!-- Filters -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <select name="teacher_id" class="form-control" onchange="location = this.value;">
                                <option value="{{ route('admin.class-teacher-assignments.index') }}">All Teachers</option>
                                @foreach($teachers as $teacher)
                                    <option value="{{ route('admin.class-teacher-assignments.index', ['teacher_id' => $teacher->id]) }}" 
                                        {{ request('teacher_id') == $teacher->id ? 'selected' : '' }}>
                                        {{ $teacher->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <form method="GET" action="{{ route('admin.class-teacher-assignments.index') }}" class="d-flex">
                                <input type="text" name="assigned_class" placeholder="Filter by Class" class="form-control me-2" 
                                       value="{{ request('assigned_class') }}">
                                <button type="submit" class="btn btn-primary">Filter</button>
                            </form>
                        </div>
                        
                        <div class="col-md-3">
                            <select name="status" class="form-control" onchange="location = this.value;">
                                <option value="{{ route('admin.class-teacher-assignments.index') }}">All Status</option>
                                <option value="{{ route('admin.class-teacher-assignments.index', ['status' => 'active']) }}" 
                                    {{ request('status') == 'active' ? 'selected' : '' }}>
                                    Active
                                </option>
                                <option value="{{ route('admin.class-teacher-assignments.index', ['status' => 'inactive']) }}" 
                                    {{ request('status') == 'inactive' ? 'selected' : '' }}>
                                    Inactive
                                </option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <a href="{{ route('admin.class-teacher-assignments.index') }}" class="btn btn-outline-secondary w-100">
                                Clear Filters
                            </a>
                        </div>
                    </div>
                    
                    <!-- Assignments Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Teacher</th>
                                    <th>Assigned Class</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($assignments as $assignment)
                                    <tr>
                                        <td>{{ $assignment->id }}</td>
                                        <td>{{ $assignment->getClassTeacherName() }}</td>
                                        <td>{{ $assignment->assigned_class }}</td>
                                        <td>{{ $assignment->start_date ? $assignment->start_date->format('Y-m-d') : 'N/A' }}</td>
                                        <td>{{ $assignment->end_date ? $assignment->end_date->format('Y-m-d') : 'N/A' }}</td>
                                        <td>
                                            <span class="badge {{ $assignment->isActive() ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $assignment->isActive() ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('admin.class-teacher-assignments.show', $assignment) }}" 
                                                   class="btn btn-sm btn-info" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="{{ route('admin.class-teacher-assignments.edit', $assignment) }}" 
                                                   class="btn btn-sm btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form action="{{ route('admin.class-teacher-assignments.destroy', $assignment) }}" 
                                                      method="POST" class="d-inline" 
                                                      onsubmit="return confirm('Are you sure you want to delete this assignment?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">No class teacher assignments found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="d-flex justify-content-center">
                        {{ $assignments->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
