@extends('layouts.app')

@section('title', 'Teacher Substitutions - Admin Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-exchange-alt"></i> Teacher Substitutions
                    </h4>
                    <a href="{{ route('admin.teacher-substitutions.create') }}" class="btn btn-light">
                        <i class="fas fa-plus"></i> Add New Substitution
                    </a>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <form method="GET" action="{{ route('admin.teacher-substitutions.index') }}" class="mb-4">
                        <div class="row">
                            <div class="col-md-2">
                                <input type="date" name="date" class="form-control" value="{{ request('date', now()->format('Y-m-d')) }}">
                            </div>
                            <div class="col-md-2">
                                <select name="status" class="form-control">
                                    <option value="">All Statuses</option>
                                    @foreach($statuses as $key => $label)
                                        <option value="{{ $key }}" {{ request('status') == $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="class_id" class="form-control">
                                    <option value="">All Classes</option>
                                    @foreach($classes as $class)
                                        <option value="{{ $class->id }}" {{ request('class_id') == $class->id ? 'selected' : '' }}>
                                            {{ $class->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="teacher_id" class="form-control">
                                    <option value="">All Teachers</option>
                                    @foreach($teachers as $teacher)
                                        <option value="{{ $teacher->id }}" {{ request('teacher_id') == $teacher->id ? 'selected' : '' }}>
                                            {{ $teacher->teacher_id }} - {{ $teacher->user->name ?? 'N/A' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <a href="{{ route('admin.teacher-substitutions.index') }}" class="btn btn-secondary">Reset</a>
                            </div>
                        </div>
                    </form>

                    <!-- Substitutions Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date</th>
                                    <th>Absent Teacher</th>
                                    <th>Substitute Teacher</th>
                                    <th>Class</th>
                                    <th>Section</th>
                                    <th>Subject</th>
                                    <th>Period</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($substitutions as $substitution)
                                <tr>
                                    <td>{{ $substitution->substitution_date->format('d M Y') }}</td>
                                    <td>
                                        {{ $substitution->absentTeacher->user->name ?? 'N/A' }} 
                                        <br><small class="text-muted">{{ $substitution->absentTeacher->teacher_id }}</small>
                                    </td>
                                    <td>
                                        @if($substitution->substituteTeacher)
                                            {{ $substitution->substituteTeacher->user->name ?? 'N/A' }}
                                            <br><small class="text-muted">{{ $substitution->substituteTeacher->teacher_id }}</small>
                                        @else
                                            <span class="badge badge-warning">Not Assigned</span>
                                        @endif
                                    </td>
                                    <td>{{ $substitution->class->name }}</td>
                                    <td>{{ $substitution->section->name }}</td>
                                    <td>{{ $substitution->subject->name }}</td>
                                    <td>
                                        {{ $substitution->period_number }}
                                        @if($substitution->period_name)
                                            <br><small class="text-muted">{{ $substitution->period_name }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge 
                                            @if($substitution->status == 'pending') badge-warning
                                            @elseif($substitution->status == 'assigned') badge-info
                                            @elseif($substitution->status == 'approved') badge-success
                                            @elseif($substitution->status == 'cancelled') badge-danger
                                            @endif
                                        ">
                                            {{ $substitution->getReadableStatus() }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('admin.teacher-substitutions.show', $substitution) }}" 
                                               class="btn btn-outline-primary" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('admin.teacher-substitutions.edit', $substitution) }}" 
                                               class="btn btn-outline-secondary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @if($substitution->isPending())
                                                <a href="{{ route('admin.teacher-substitutions.assign', $substitution) }}" 
                                                   class="btn btn-outline-success" title="Assign Substitute">
                                                    <i class="fas fa-user-check"></i>
                                                </a>
                                            @endif
                                            @if($substitution->isAssigned())
                                                <a href="{{ route('admin.teacher-substitutions.approve', $substitution) }}" 
                                                   class="btn btn-outline-success" title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            @endif
                                            <form action="{{ route('admin.teacher-substitutions.destroy', $substitution) }}" 
                                                  method="POST" style="display:inline;" 
                                                  onsubmit="return confirm('Are you sure you want to delete this substitution?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9" class="text-center">No substitutions found for the selected criteria.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-center">
                        {{ $substitutions->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection