@extends('layouts.admin')

@section('title', 'Marks Moderation & Grace Marks Control')

@section('content')
<div class="container">
    <!-- Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Marks Moderation &amp; Grace Marks Control</h1>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row">
        <!-- Flat Moderation Card -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Subject Marks Moderation Scaling</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Scale all student scores for a given exam and subject by a percentage. (e.g. +5% or -2%)</p>
                    <form action="{{ route('admin.exams.moderation.moderate') }}" method="POST">
                        @csrf
                        <div class="form-group mb-3">
                            <label for="exam_id" class="form-label font-weight-bold">Select Scheduled Exam</label>
                            <select name="exam_id" id="exam_id" class="form-control" required>
                                <option value="">-- Select Exam --</option>
                                @foreach($exams as $exam)
                                    <option value="{{ $exam->id }}">{{ $exam->name }} ({{ $exam->class_name }} - {{ $exam->subject }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label for="subject" class="form-label font-weight-bold">Subject Code / Name</label>
                            <input type="text" name="subject" id="subject" class="form-control" required placeholder="e.g. Mathematics">
                        </div>

                        <div class="form-group mb-3">
                            <label for="adjustment_percentage" class="form-label font-weight-bold">Adjustment Percentage (%)</label>
                            <input type="number" step="0.1" name="adjustment_percentage" id="adjustment_percentage" class="form-control" required min="-50" max="50" placeholder="e.g. 5.0">
                        </div>

                        <div class="form-group mb-3">
                            <label for="reason" class="form-label font-weight-bold">Reason for Adjustment</label>
                            <input type="text" name="reason" id="reason" class="form-control" placeholder="e.g. Moderation due to out-of-syllabus questions">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Apply Moderation Scaling</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Grace Marks Policy Card -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Automated Grace Marks Allocation</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Distribute grace marks to push close-to-passing grades into pass status for a student.</p>
                    <form action="{{ route('admin.exams.moderation.grace') }}" method="POST">
                        @csrf
                        <div class="form-group mb-3">
                            <label for="student_id" class="form-label font-weight-bold">Select Student ID / Name</label>
                            <input type="number" name="student_id" id="student_id" class="form-control" required placeholder="e.g. Student ID (e.g. 1)">
                        </div>

                        <div class="form-group mb-3">
                            <label for="academic_year" class="form-label font-weight-bold">Academic Year</label>
                            <input type="text" name="academic_year" id="academic_year" class="form-control" required placeholder="e.g. 2026">
                        </div>

                        <div class="form-group mb-3">
                            <label for="max_grace_marks" class="form-label font-weight-bold">Max Allowed Grace Marks (Total Pool)</label>
                            <input type="number" name="max_grace_marks" id="max_grace_marks" class="form-control" required min="1" max="20" value="5">
                        </div>

                        <button type="submit" class="btn btn-success w-100">Distribute Grace Marks</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
