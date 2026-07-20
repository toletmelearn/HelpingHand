@extends('layouts.teacher')

@section('content')
<div class="container-fluid px-4 py-4">
    <div class="mb-4">
        <a href="{{ route('teacher.dashboard') }}" class="text-secondary text-decoration-none">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div class="row mb-4">
        <div class="col-md-12">
            <h1 class="h2 text-dark font-weight-bold">Homework Submissions</h1>
            <p class="text-secondary">Evaluate student submissions for assignment: <strong>{{ $homework->title }}</strong></p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0 rounded-lg">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-secondary font-weight-bold text-uppercase" style="font-size: 0.8rem;">
                        <tr>
                            <th class="ps-4 py-3">Student Name</th>
                            <th class="py-3">Roll No</th>
                            <th class="py-3">Submission Date</th>
                            <th class="py-3">Submitted File</th>
                            <th class="py-3 text-center">Marks Obtained</th>
                            <th class="py-3 text-center">Grade</th>
                            <th class="py-3 text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($submissions as $submission)
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; font-weight: 600;">
                                            {{ strtoupper(substr($submission->student->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <h6 class="mb-0 text-dark font-weight-bold">{{ $submission->student->name }}</h6>
                                            <small class="text-secondary">Adm No: {{ $submission->student->admission_no }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $submission->student->roll_number }}</td>
                                <td>
                                    <div class="text-dark">{{ $submission->submission_date->format('M d, Y') }}</div>
                                    <small class="text-secondary">{{ $submission->submission_date->format('h:i A') }}</small>
                                </td>
                                <td>
                                    @if($submission->file_path)
                                        <a href="{{ asset('storage/' . $submission->file_path) }}" target="_blank" class="text-decoration-none font-weight-bold">
                                            <i class="bi bi-file-earmark-arrow-down"></i> View File
                                        </a>
                                    @else
                                        <span class="text-secondary">-</span>
                                    @endif
                                </td>
                                <td class="text-center text-dark font-weight-bold">
                                    {{ $submission->marks_obtained !== null ? number_format($submission->marks_obtained, 1) : '-' }}
                                </td>
                                <td class="text-center">
                                    @if($submission->grade)
                                        <span class="badge bg-primary px-3 py-2 rounded-pill">{{ $submission->grade }}</span>
                                    @else
                                        <span class="text-secondary">-</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    @if($submission->status == 'evaluated')
                                        <a href="{{ route('teacher.homework.submissions.evaluate', $submission->id) }}" class="btn btn-sm btn-outline-secondary px-3 rounded-pill">Edit Grade</a>
                                    @else
                                        <a href="{{ route('teacher.homework.submissions.evaluate', $submission->id) }}" class="btn btn-sm btn-primary px-3 rounded-pill font-weight-bold">Grade Submission</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="text-secondary mb-3">
                                        <i class="bi bi-folder-x" style="font-size: 3rem;"></i>
                                    </div>
                                    <h5 class="text-dark font-weight-bold">No Submissions Yet</h5>
                                    <p class="text-secondary mb-0">No students have uploaded their homework submissions for this assignment.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
