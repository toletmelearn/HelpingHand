@extends('layouts.app')

@section('title', 'Result Entry System')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-clipboard-list me-2"></i>
                        Multi-Subject Result Entry System
                    </h4>
                    @can('create', App\Models\Result::class)
                    <div class="btn-group">
                        <a href="{{ route('results.entry.create') }}" class="btn btn-light">
                            <i class="fas fa-plus-circle me-1"></i> Add Individual Result
                        </a>
                        <a href="{{ route('results.entry.bulk-form') }}" class="btn btn-success">
                            <i class="fas fa-file-excel me-1"></i> Bulk Entry
                        </a>
                    </div>
                    @endcan
                </div>
                
                <div class="card-body">
                    <!-- Filters -->
                    <form method="GET" action="{{ route('results.entry.index') }}" class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="exam_id" class="form-label">Exam</label>
                                <select name="exam_id" id="exam_id" class="form-select">
                                    <option value="">All Exams</option>
                                    @foreach($exams as $exam)
                                        <option value="{{ $exam->id }}" {{ request('exam_id') == $exam->id ? 'selected' : '' }}>
                                            {{ $exam->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="class_id" class="form-label">Class</label>
                                <select name="class_id" id="class_id" class="form-select">
                                    <option value="">All Classes</option>
                                    @foreach($classes as $class)
                                        <option value="{{ $class->id }}" {{ request('class_id') == $class->id ? 'selected' : '' }}>
                                            {{ $class->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="subject" class="form-label">Subject</label>
                                <select name="subject" id="subject" class="form-select">
                                    <option value="">All Subjects</option>
                                    @foreach($subjects as $subject)
                                        <option value="{{ $subject->name }}" {{ request('subject') == $subject->name ? 'selected' : '' }}>
                                            {{ $subject->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search me-1"></i> Filter
                                </button>
                                <a href="{{ route('results.entry.index') }}" class="btn btn-secondary">
                                    <i class="fas fa-redo me-1"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>

                    <!-- Results Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Student</th>
                                    <th>Class</th>
                                    <th>Exam</th>
                                    <th>Subject</th>
                                    <th>Marks Obtained</th>
                                    <th>Total Marks</th>
                                    <th>Percentage</th>
                                    <th>Grade</th>
                                    <th>Status</th>
                                    <th>Verified</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($results as $result)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-2">
                                                <img src="{{ $result->student->photo ? asset('storage/' . $result->student->photo) : asset('images/default-avatar.png') }}" 
                                                     alt="{{ $result->student->name }}" 
                                                     class="rounded-circle" 
                                                     width="40" 
                                                     height="40">
                                            </div>
                                            <div>
                                                <strong>{{ $result->student->name }}</strong>
                                                <br>
                                                <small class="text-muted">Roll: {{ $result->student->roll_number }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        {{ $result->student->class->name }} 
                                        @if($result->student->section)
                                            - {{ $result->student->section->name }}
                                        @endif
                                    </td>
                                    <td>{{ $result->exam->name }}</td>
                                    <td>{{ $result->subject }}</td>
                                    <td><span class="badge bg-primary">{{ $result->marks_obtained }}</span></td>
                                    <td>{{ $result->total_marks }}</td>
                                    <td>
                                        <span class="badge bg-{{ $result->percentage >= 75 ? 'success' : ($result->percentage >= 60 ? 'warning' : 'danger') }}">
                                            {{ $result->percentage }}%
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ in_array($result->grade, ['A1', 'A2']) ? 'success' : (in_array($result->grade, ['B1', 'B2']) ? 'primary' : 'secondary') }}">
                                            {{ $result->grade }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $result->result_status == 'pass' ? 'success' : 'danger' }}">
                                            {{ ucfirst($result->result_status) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($result->is_verified)
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle me-1"></i> Verified
                                            </span>
                                            <br>
                                            <small class="text-muted">by {{ $result->verifiedBy?->name ?? 'Unknown' }}</small>
                                        @else
                                            <span class="badge bg-warning">
                                                <i class="fas fa-clock me-1"></i> Pending
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            @can('view', $result)
                                            <a href="{{ route('results.show', $result) }}" class="btn btn-outline-primary" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            @endcan
                                            
                                            @can('update', $result)
                                            @if(!$result->is_verified)
                                            <a href="{{ route('results.entry.edit', $result) }}" class="btn btn-outline-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endif
                                            @endcan
                                            
                                            @can('verify', $result)
                                            @if(!$result->is_verified)
                                            <form action="{{ route('results.entry.verify', $result) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-success" title="Verify Result"
                                                        onclick="return confirm('Verify this result? This action cannot be undone.')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            @else
                                            <form action="{{ route('results.entry.unverify', $result) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-danger" title="Unverify Result"
                                                        onclick="return confirm('Unverify this result?')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                            @endif
                                            @endcan
                                            
                                            @can('delete', $result)
                                            @if(!$result->is_verified)
                                            <form action="{{ route('results.entry.destroy', $result) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger" title="Delete"
                                                        onclick="return confirm('Are you sure you want to delete this result?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            @endif
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="11" class="text-center py-5">
                                        <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                        <h5>No results found</h5>
                                        <p class="text-muted">Try adjusting your filters or add new results.</p>
                                        @can('create', App\Models\Result::class)
                                        <div class="btn-group">
                                            <a href="{{ route('results.entry.create') }}" class="btn btn-primary">
                                                <i class="fas fa-plus-circle me-1"></i> Add First Result
                                            </a>
                                            <a href="{{ route('results.entry.bulk-form') }}" class="btn btn-success">
                                                <i class="fas fa-file-excel me-1"></i> Bulk Entry
                                            </a>
                                        </div>
                                        @endcan
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div class="text-muted">
                            Showing {{ $results->firstItem() }} to {{ $results->lastItem() }} of {{ $results->total() }} results
                        </div>
                        <div>
                            {{ $results->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
.table th {
    font-weight: 600;
}
.badge {
    font-size: 0.85em;
}
.btn-group .btn {
    margin-right: 2px;
}
.card-header {
    border-bottom: none;
}
</style>
@endpush