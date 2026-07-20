@extends('layouts.teacher')

@section('content')
<div class="container-fluid px-4 py-4">
    <div class="mb-4">
        <a href="{{ route('teacher.homework.submissions.index', $submission->homework_notice_id) }}" class="text-secondary text-decoration-none">
            <i class="bi bi-arrow-left"></i> Back to Submissions List
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-lg mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h3 class="card-title text-dark font-weight-bold mb-0">Evaluate Submission</h3>
                    <p class="text-secondary mb-0">Grade and review homework uploaded by <strong>{{ $submission->student->name }}</strong>.</p>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <span class="text-secondary d-block mb-1">Student Name</span>
                            <span class="text-dark font-weight-bold" style="font-size: 1.1rem;">{{ $submission->student->name }}</span>
                            <small class="text-secondary d-block">Roll Number: {{ $submission->student->roll_number }}</small>
                        </div>
                        <div class="col-md-6">
                            <span class="text-secondary d-block mb-1">Submission Time</span>
                            <span class="text-dark font-weight-bold" style="font-size: 1.1rem;">{{ $submission->submission_date->format('M d, Y h:i A') }}</span>
                        </div>
                    </div>

                    @if($submission->file_path)
                        <div class="p-3 bg-light rounded mb-4 d-flex align-items-center justify-content-between border border-light">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-file-earmark-pdf text-danger me-3" style="font-size: 2.2rem;"></i>
                                <div>
                                    <span class="d-block text-dark font-weight-bold">Submitted Document</span>
                                    <small class="text-secondary">Click download to inspect the document</small>
                                </div>
                            </div>
                            <a href="{{ asset('storage/' . $submission->file_path) }}" target="_blank" class="btn btn-outline-primary rounded-pill px-4 font-weight-bold">View Document</a>
                        </div>
                    @endif

                    @if($submission->student_notes)
                        <div class="mb-4">
                            <span class="text-secondary d-block mb-1">Student Notes</span>
                            <div class="p-3 bg-light rounded text-dark" style="white-space: pre-wrap; font-size: 0.95rem;">{{ $submission->student_notes }}</div>
                        </div>
                    @endif

                    <hr class="my-4 text-light">

                    <form action="{{ route('teacher.homework.submissions.store-evaluation', $submission->id) }}" method="POST">
                        @csrf

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="marks_obtained" class="form-label text-secondary font-weight-bold">Marks Obtained</label>
                                <input type="number" step="0.1" class="form-control text-dark" id="marks_obtained" name="marks_obtained" value="{{ $submission->marks_obtained }}" placeholder="Score (e.g. 8.5)">
                            </div>
                            <div class="col-md-6">
                                <label for="grade" class="form-label text-secondary font-weight-bold">Grade</label>
                                <select class="form-select text-dark" id="grade" name="grade">
                                    <option value="" disabled selected>Select Grade...</option>
                                    <option value="A+" {{ $submission->grade == 'A+' ? 'selected' : '' }}>A+ (Excellent)</option>
                                    <option value="A" {{ $submission->grade == 'A' ? 'selected' : '' }}>A (Very Good)</option>
                                    <option value="B" {{ $submission->grade == 'B' ? 'selected' : '' }}>B (Good)</option>
                                    <option value="C" {{ $submission->grade == 'C' ? 'selected' : '' }}>C (Satisfactory)</option>
                                    <option value="D" {{ $submission->grade == 'D' ? 'selected' : '' }}>D (Needs Improvement)</option>
                                    <option value="F" {{ $submission->grade == 'F' ? 'selected' : '' }}>F (Fail)</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="remarks" class="form-label text-secondary font-weight-bold">Evaluation Remarks / Feedback</label>
                            <textarea class="form-control text-dark" id="remarks" name="remarks" rows="4" placeholder="Write feedback for the student..." required>{{ $submission->remarks }}</textarea>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary px-5 py-2 rounded-pill font-weight-bold shadow-sm">Save Evaluation</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
