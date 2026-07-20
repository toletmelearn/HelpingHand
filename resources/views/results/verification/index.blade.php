@extends('layouts.app')

@section('title', 'Result Verification Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-clipboard-check me-2"></i>
                        Result Verification Dashboard
                    </h4>
                    <div class="btn-group">
                        <button class="btn btn-light" onclick="refreshStatistics()">
                            <i class="fas fa-sync-alt me-1"></i> Refresh Stats
                        </button>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Statistics Cards -->
                    <div class="row mb-4" id="statistics-container">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h3 id="total-results">0</h3>
                                    <p class="mb-0">Total Results</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h3 id="verified-results">0</h3>
                                    <p class="mb-0">Verified Results</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h3 id="pending-results">0</h3>
                                    <p class="mb-0">Pending Results</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h3 id="verification-rate">0%</h3>
                                    <p class="mb-0">Verification Rate</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <form method="GET" action="{{ route('results.verification.index') }}" class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-4">
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
                            
                            <div class="col-md-4">
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
                            
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-filter me-1"></i> Filter
                                </button>
                                <a href="{{ route('results.verification.index') }}" class="btn btn-secondary">
                                    <i class="fas fa-redo me-1"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>

                    <!-- Students Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Student</th>
                                    <th>Class</th>
                                    <th>Total Subjects</th>
                                    <th>Verified</th>
                                    <th>Pending</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($students as $student)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-2">
                                                <img src="{{ $student->photo ? asset('storage/' . $student->photo) : asset('images/default-avatar.png') }}" 
                                                     alt="{{ $student->name }}" 
                                                     class="rounded-circle" 
                                                     width="40" 
                                                     height="40">
                                            </div>
                                            <div>
                                                <strong>{{ $student->name }}</strong>
                                                <br>
                                                <small class="text-muted">Roll: {{ $student->roll_number }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        {{ $student->class->name }} 
                                        @if($student->section)
                                            - {{ $student->section->name }}
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary">{{ $student->total_subjects }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success">{{ $student->verified_count }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-{{ $student->pending_count > 0 ? 'warning' : 'secondary' }}">{{ $student->pending_count }}</span>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            @php
                                                $progressClass = $student->verification_progress == 100 ? 'success' : ($student->verification_progress >= 50 ? 'primary' : 'warning');
                                            @endphp
                                            <div class="progress-bar bg-{{ $progressClass }}" 
                                                 role="progressbar" 
                                                 style="width: {{ $student->verification_progress }}%">
                                                {{ $student->verification_progress }}%
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        @if($student->all_verified)
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle me-1"></i> Complete
                                            </span>
                                        @elseif($student->pending_count > 0)
                                            <span class="badge bg-warning">
                                                <i class="fas fa-clock me-1"></i> In Progress
                                            </span>
                                        @else
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-minus-circle me-1"></i> No Results
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="{{ route('results.verification.show', $student->id) }}{{ request('exam_id') ? '?exam_id=' . request('exam_id') : '' }}" 
                                               class="btn btn-outline-primary" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            @if($student->pending_count > 0)
                                            @php
                                                $onclickFunction = "verifyStudent(" . $student->id . ")";
                                            @endphp
                                            <button type="button" class="btn btn-outline-success" 
                                                    onclick="{{ $onclickFunction }}" 
                                                    title="Verify All Pending">
                                                <i class="fas fa-check-double"></i>
                                            </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                        <h5>No students found</h5>
                                        <p class="text-muted">Try adjusting your filters.</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div class="text-muted">
                            Showing {{ $students->firstItem() }} to {{ $students->lastItem() }} of {{ $students->total() }} students
                        </div>
                        <div>
                            {{ $students->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Verification Modal -->
<div class="modal fade" id="verificationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Verification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="verificationForm" method="POST">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="result_ids[]" id="resultIds">
                    <div class="mb-3">
                        <label for="verificationComments" class="form-label">Verification Comments (Optional)</label>
                        <textarea class="form-control" id="verificationComments" name="verification_comments" rows="3" placeholder="Add verification comments..."></textarea>
                    </div>
                    <div class="alert alert-info">
                        Are you sure you want to verify all selected results?
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Verify Results</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function refreshStatistics() {
    const examId = document.getElementById('exam_id').value;
    const url = "{{ route('results.verification.statistics') }}" + (examId ? '?exam_id=' + examId : '');
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            document.getElementById('total-results').textContent = data.total_results;
            document.getElementById('verified-results').textContent = data.verified_results;
            document.getElementById('pending-results').textContent = data.pending_results;
            document.getElementById('verification-rate').textContent = data.verification_rate + '%';
        })
        .catch(error => console.error('Error:', error));
}

function verifyStudent(studentId) {
    // Get all pending result IDs for this student
    fetch("{{ url('api/student-results/') }}/" + studentId + "/pending")
        .then(response => response.json())
        .then(data => {
            if (data.length > 0) {
                document.getElementById('resultIds').value = data.join(',');
                document.getElementById('verificationForm').action = "{{ route('results.verification.bulk-verify') }}";
                new bootstrap.Modal(document.getElementById('verificationModal')).show();
            } else {
                alert('No pending results found for this student.');
            }
        })
        .catch(error => console.error('Error:', error));
}

// Load statistics on page load
document.addEventListener('DOMContentLoaded', function() {
    refreshStatistics();
});
</script>
@endpush

@push('styles')
<style>
.progress {
    height: 20px;
}
.table th {
    font-weight: 600;
}
.badge {
    font-size: 0.85em;
}
.card-header {
    border-bottom: none;
}
</style>
@endpush