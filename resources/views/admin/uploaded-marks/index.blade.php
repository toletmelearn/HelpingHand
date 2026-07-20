@extends('layouts.admin')

@section('title', 'Uploaded Marks Management')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4><i class="fas fa-chart-bar"></i> Uploaded Marks Management</h4>
                </div>
                
                <!-- Filter Section -->
                <form method="GET" action="{{ route('admin.uploaded-marks.index') }}">
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <label for="exam_id" class="form-label">Select Exam</label>
                            <select name="exam_id" id="exam_id" class="form-control">
                                <option value="">All Exams</option>
                                @foreach($exams as $exam)
                                    <option value="{{ $exam->id }}" {{ request('exam_id') == $exam->id ? 'selected' : '' }}>
                                        {{ $exam->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="class_id" class="form-label">Select Class</label>
                            <select name="class_id" id="class_id" class="form-control">
                                <option value="">All Classes</option>
                                @foreach($classes as $class)
                                    <option value="{{ $class->id }}" {{ request('class_id') == $class->id ? 'selected' : '' }}>
                                        {{ $class->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="subject" class="form-label">Select Subject</label>
                            <select name="subject" id="subject" class="form-control">
                                <option value="">All Subjects</option>
                                @foreach($subjects as $subjectName)
                                    <option value="{{ $subjectName }}" {{ request('subject') == $subjectName ? 'selected' : '' }}>
                                        {{ $subjectName }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="teacher_id" class="form-label">Select Teacher</label>
                            <select name="teacher_id" id="teacher_id" class="form-control">
                                <option value="">All Teachers</option>
                                @foreach($teachers as $teacher)
                                    <option value="{{ $teacher->id }}" {{ request('teacher_id') == $teacher->id ? 'selected' : '' }}>
                                        {{ $teacher->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <a href="{{ route('admin.uploaded-marks.export') }}?{{ http_build_query(request()->only(['exam_id', 'class_id', 'subject', 'teacher_id'])) }}" 
                           class="btn btn-success" id="exportBtn" @if(!$summary) style="display:none;" @endif>
                            <i class="fas fa-file-export"></i> Export to Excel
                        </a>
                    </div>
                </form>
                    
                    <!-- Summary Section -->
                    @if($summary)
                    <div class="alert alert-info">
                        <h5>Summary</h5>
                        <p>
                            <strong>Total Students:</strong> {{ $summary['total_students'] }} |
                            <strong>Teacher:</strong> {{ $summary['teacher_name'] }} |
                            <strong>Subject:</strong> {{ $summary['subject'] }} |
                            <strong>Exam:</strong> {{ $summary['exam'] }}
                        </p>
                    </div>
                    @endif
                    
                    <!-- Show total records when no filters applied -->
                    @if(!$summary)
                    <div class="alert alert-info text-center">
                        <h5><i class="fas fa-info-circle"></i> Showing all uploaded marks ({{ $results->count() }} records)</h5>
                    </div>
                    @endif
                    
                    <!-- Results Table -->
                    @if($results && $results->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>Student</th>
                                    <th>Roll</th>
                                    <th>Class</th>
                                    <th>Subject</th>
                                    <th>Exam</th>
                                    <th>Marks</th>
                                    <th>Teacher</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($results as $result)
                                <tr>
                                    <td>{{ $result->student->name ?? 'N/A' }}</td>
                                    <td>{{ $result->student->roll_number ?? 'N/A' }}</td>
                                    <td>{{ $result->schoolClass->name ?? 'N/A' }}</td>
                                    <td>{{ $result->subject }}</td>
                                    <td>{{ $result->exam->name ?? 'N/A' }}</td>
                                    <td>{{ $result->marks_obtained }} / {{ $result->total_marks }} ({{ round($result->percentage, 2) }}%)</td>
                                    <td>{{ $result->uploadedByTeacher->name ?? 'N/A' }}</td>
                                    <td>
                                        <span class="badge bg-{{ $result->is_locked ? 'success' : 'warning' }}">
                                            {{ $result->is_locked ? 'Locked' : 'Unlocked' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group" aria-label="Actions">
                                            @if($result->is_locked)
                                                <button type="button" class="btn btn-outline-warning unlock-btn" data-bs-toggle="tooltip" title="Unlock Result" data-id="{{ $result->id }}">
                                                    <i class="fas fa-unlock"></i>
                                                </button>
                                            @endif
                                            <a href="{{ route('admin.results.show', $result) }}" class="btn btn-outline-primary" data-bs-toggle="tooltip" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger delete-btn" data-bs-toggle="tooltip" title="Delete Result" data-id="{{ $result->id }}">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @elseif($summary && $results->count() == 0)
                    <div class="alert alert-info text-center">
                        <h5>No results found for the selected filters.</h5>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
// Initialize tooltips and set up event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Delete button event listeners
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const resultId = this.getAttribute('data-id');
            if (confirm('Are you sure you want to delete this result?')) {
                fetch(`/admin/uploaded-marks/delete/${resultId}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error deleting result');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting result');
                });
            }
        });
    });
    
    // Unlock button event listeners
    document.querySelectorAll('.unlock-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const resultId = this.getAttribute('data-id');
            if (confirm('Are you sure you want to unlock this result?')) {
                fetch(`/admin/uploaded-marks/unlock/${resultId}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error unlocking result');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error unlocking result');
                });
            }
        });
    });
});
</script>
@endsection