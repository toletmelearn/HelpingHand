@extends('layouts.teacher')

@section('title', 'Teacher Dashboard')

@php
$teacher = $teacher ?? null;

$assignedClasses = $assignedClasses ?? collect();
$assignedSubjects = $assignedSubjects ?? [];

$uploadedResults = $uploadedResults ?? 0;
$isExamHead = $isExamHead ?? false;

$recentExams = $recentExams ?? collect();
$notices = $notices ?? collect();
$homeworks = $homeworks ?? collect();

$classTeacherAssignments = $classTeacherAssignments ?? collect();
$assignments = $assignments ?? collect();
$invigilatorDuties = $invigilatorDuties ?? collect();
$relievingDuties = $relievingDuties ?? collect();
@endphp

@php
    function safePrint($value){
        if(is_array($value) || is_object($value)){
            return '';
        }
        return $value;
    }
@endphp

@section('content')

    <div class="container-fluid mt-4">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('warning'))
            <div class="alert alert-warning alert-dismissible fade show">
                <strong>Important!</strong> {{ session('warning') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-gradient bg-success text-white">
                    <div class="card-body">
                        <h3><i class="fas fa-hand-wave"></i> Welcome, {{ $teacher->name ?? 'Teacher' }}!</h3>
                        <p class="mb-0">{{ $teacher->designation ?? '' }} | Employee ID: {{ $teacher->employee_id ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <i class="fas fa-school fa-3x text-primary mb-2"></i>
                        <h4>
                            @if(!empty($assignedClasses))
                                @foreach($assignedClasses as $c)
                                    {{ $c->class_name ?? '' }} {{ $c->section ?? '' }},
                                @endforeach
                            @else
                                No Class Assigned
                            @endif
                        </h4>
                        <p class="text-muted mb-0">Assigned Classes</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <i class="fas fa-book fa-3x text-success mb-2"></i>
                        <h4>
                            @if(!empty($assignedSubjects))
                                {{ is_array($assignedSubjects) ? implode(', ', $assignedSubjects) : $assignedSubjects }}
                            @else
                                Not Assigned
                            @endif
                        </h4>
                        <p class="text-muted mb-0">Subjects Teaching</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <i class="fas fa-clipboard-check fa-3x text-warning mb-2"></i>
                        <h4>{{ safePrint($uploadedResults) }}</h4>
                        <p class="text-muted mb-0">Marks Uploaded</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <i class="fas fa-user-shield fa-3x text-info mb-2"></i>
                        <h4>{{ isset($isExamHead) ? ($isExamHead ? 'Yes' : 'No') : 'No' }}</h4>
                        <p class="text-muted mb-0">Exam Head</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="{{ route('teacher.classes') }}" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-users fa-2x mb-2"></i><br>
                                    My Classes
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="{{ route('teacher.marks.index') }}" class="btn btn-outline-success w-100">
                                    <i class="fas fa-upload fa-2x mb-2"></i><br>
                                    Upload Marks
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="{{ route('teacher.exams.index') }}" class="btn btn-outline-warning w-100">
                                    <i class="fas fa-file-alt fa-2x mb-2"></i><br>
                                    My Exams
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="{{ route('teacher.homework.index') }}" class="btn btn-outline-info w-100">
                                    <i class="fas fa-book-reader fa-2x mb-2"></i><br>
                                    Homework
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Class Teacher Info -->
        @if($classTeacherAssignments->isNotEmpty())
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-success">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-star"></i> Class Teacher Assignments</h5>
                    </div>
                    <div class="card-body">
                        @foreach($classTeacherAssignments as $assignment)
                            <div class="alert alert-success">
                                <strong>{{ $assignment['class']->name ?? '' }}</strong>
                                @if($assignment['section'])
                                    - Section: {{ $assignment['section']->name ?? '' }}
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Exam Head Access -->
        @if($isExamHead)
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-user-shield"></i> Exam Head Access</h5>
                    </div>
                    <div class="card-body">
                        <p>You have Exam Head privileges. You can review and approve marks submitted by other teachers.</p>
                        <a href="{{ route('teacher.examhead.marks') }}" class="btn btn-danger">
                            <i class="fas fa-clipboard-check"></i> Review Submitted Marks
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Assigned Subjects -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-list"></i> My Assignments</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Class</th>
                                        <th>Section</th>
                                        <th>Subject</th>
                                        <th>Primary Subject</th>
                                        <th>Class Teacher</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($assignments as $assignment)
                                    <tr>
                                        <td>{{ $assignment['class_name'] ?? '' }}</td>
                                        <td>{{ $assignment['section_name'] ?? 'All' }}</td>
                                        <td>{{ $assignment['subject_name'] ?? '' }}</td>
                                        <td>
                                            @if($assignment['is_primary_subject_teacher'])
                                                <span class="badge bg-info"><i class="fas fa-star"></i> Yes</span>
                                            @else
                                                <span class="badge bg-light text-dark">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($assignment['is_class_teacher'])
                                                <span class="badge bg-success"><i class="fas fa-crown"></i> Yes</span>
                                            @else
                                                <span class="badge bg-secondary">No</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-5">
                                            <div class="text-muted">
                                                <i class="fas fa-info-circle fa-3x mb-3"></i>
                                                <h5>No Class Assigned Yet</h5>
                                                <p>Please contact the administrator to assign classes and subjects to you.</p>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                </div>
            </div>
        </div>

        <!-- Exam Duties Section -->
        <div class="row mt-4">
            <!-- Invigilator Duties Card -->
            <div class="col-md-6 mb-4">
                <div class="card h-100 border-info shadow-sm">
                    <div class="card-header bg-info text-white d-flex align-items-center justify-content-between">
                        <h5 class="mb-0"><i class="fas fa-user-clock text-white me-2"></i> Invigilator Duties</h5>
                        <span class="badge bg-light text-info">{{ $invigilatorDuties->count() }} Duty(s)</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Exam</th>
                                        <th>Date/Time</th>
                                        <th>Room</th>
                                        <th>Role</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($invigilatorDuties as $duty)
                                    <tr>
                                        <td>
                                            <span class="fw-bold text-dark">{{ $duty->exam->name ?? 'N/A' }}</span>
                                            <br>
                                            <small class="text-muted">Class: {{ $duty->exam->class_name ?? 'N/A' }}</small>
                                        </td>
                                        <td>
                                            @if($duty->exam)
                                                <i class="far fa-calendar-alt text-muted me-1"></i> {{ \Carbon\Carbon::parse($duty->exam->exam_date)->format('d M, Y') }}
                                                <br>
                                                <i class="far fa-clock text-muted me-1"></i> {{ $duty->exam->start_time }} - {{ $duty->exam->end_time }}
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary p-2"><i class="fas fa-door-open"></i> {{ $duty->room_number }}</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><i class="fas fa-user-tag"></i> {{ $duty->role }}</span>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                                <p class="mb-0">No invigilator duties assigned.</p>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Relieving Duties Card -->
            <div class="col-md-6 mb-4">
                <div class="card h-100 border-warning shadow-sm">
                    <div class="card-header bg-warning text-dark d-flex align-items-center justify-content-between">
                        <h5 class="mb-0 text-dark"><i class="fas fa-hands-helping me-2"></i> Relieving / Standby Duties</h5>
                        <span class="badge bg-dark text-white">{{ $relievingDuties->count() }} Shift(s)</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Exam</th>
                                        <th>Date</th>
                                        <th>Time Slot</th>
                                        <th>Relief Room</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($relievingDuties as $duty)
                                    <tr>
                                        <td>
                                            <span class="fw-bold text-dark">{{ $duty->exam->name ?? 'N/A' }}</span>
                                            <br>
                                            <small class="text-muted">Class: {{ $duty->exam->class_name ?? 'N/A' }}</small>
                                        </td>
                                        <td>
                                            @if($duty->exam)
                                                <i class="far fa-calendar-alt text-muted me-1"></i> {{ \Carbon\Carbon::parse($duty->exam->exam_date)->format('d M, Y') }}
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark"><i class="far fa-clock"></i> {{ $duty->time_slot }}</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary p-2"><i class="fas fa-door-open"></i> {{ $duty->room_number }}</span>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                                <p class="mb-0">No relieving duties assigned.</p>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assigned Admission Enquiries (Counsellor) -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card border-info shadow-sm">
                    <div class="card-header bg-info text-white d-flex align-items-center justify-content-between">
                        <h5 class="mb-0"><i class="fas fa-comments me-2"></i> My Assigned Admission Enquiries</h5>
                        <span class="badge bg-light text-dark fw-bold">{{ $assignedEnquiries->count() }} active</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Candidate Name</th>
                                        <th>Parent Contact</th>
                                        <th>Status</th>
                                        <th>Next Follow-Up Date</th>
                                        <th>Last Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($assignedEnquiries as $enquiry)
                                    <tr>
                                        <td class="fw-bold">{{ $enquiry->candidate_name }}</td>
                                        <td>{{ $enquiry->parent_name }}<br><small class="text-muted">{{ $enquiry->phone }}</small></td>
                                        <td>
                                            @if($enquiry->status === 'new')
                                                <span class="badge bg-info">New</span>
                                            @elseif($enquiry->status === 'interested')
                                                <span class="badge bg-success">Interested</span>
                                            @elseif($enquiry->status === 'follow_up')
                                                <span class="badge bg-warning text-dark">Follow-Up</span>
                                            @elseif($enquiry->status === 'admitted')
                                                <span class="badge bg-primary">Admitted</span>
                                            @elseif($enquiry->status === 'closed')
                                                <span class="badge bg-secondary">Closed</span>
                                            @else
                                                <span class="badge bg-secondary text-capitalize">{{ $enquiry->status }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($enquiry->follow_up_date)
                                                <i class="far fa-calendar-alt text-muted me-1"></i> {{ $enquiry->follow_up_date->format('Y-m-d') }}
                                            @else
                                                <span class="text-muted small">Not Scheduled</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="text-muted small">{{ Str::limit($enquiry->remarks ?: ($enquiry->follow_up_notes ?: 'N/A'), 60) }}</span>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            <i class="fas fa-comment-slash fa-2x mb-2 d-block text-secondary"></i>
                                            No admission candidates currently assigned to you for counselling.
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@endsection
