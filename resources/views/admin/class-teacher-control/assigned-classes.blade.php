@extends('layouts.app')

@section('title', 'Assigned Classes - Class Teacher Control')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-chalkboard-teacher"></i> Assigned Classes
                    </h4>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <form method="GET" action="{{ route('admin.class-teacher-control.assigned-classes') }}" class="mb-4">
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
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <a href="{{ route('admin.class-teacher-control.assigned-classes') }}" class="btn btn-secondary">Reset</a>
                            </div>
                        </div>
                    </form>

                    <!-- Teachers Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Teacher Name</th>
                                    <th>Employee ID</th>
                                    <th>Email</th>
                                    <th>Assigned Classes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($teachers as $teacher)
                                <tr>
                                    <td>{{ $teacher->user->name ?? 'N/A' }}</td>
                                    <td>{{ $teacher->employee_id ?? 'N/A' }}</td>
                                    <td>{{ $teacher->user->email ?? 'N/A' }}</td>
                                    <td>
                                        @if($teacher->classes->count() > 0)
                                            <ul class="list-unstyled mb-0">
                                                @foreach($teacher->classes as $class)
                                                    <li>
                                                        <span class="badge bg-primary">{{ $class->name }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <span class="badge bg-secondary">No classes assigned</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('teachers.show', $teacher) }}" class="btn btn-outline-primary" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('teachers.edit', $teacher) }}" class="btn btn-outline-secondary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center">No class teachers found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-center">
                        {{ $teachers->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection