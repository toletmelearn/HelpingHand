@extends('layouts.admin')

@section('title', 'Report Card Designer & Promotion Rules')

@section('content')
<div class="container">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Report Card Designer &amp; Promotion Rules</h1>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row">
        <!-- Template Canvas Layout Config -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Report Card Customizer</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.exams.reports.store-template') }}" method="POST">
                        @csrf
                        <div class="form-group mb-3">
                            <label for="name" class="form-label font-weight-bold">Template Label Name</label>
                            <input type="text" name="name" id="name" class="form-control" required placeholder="e.g. CBSE Term 1 Report">
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="show_logo" id="show_logo" checked>
                            <label class="form-check-input-label font-weight-bold" for="show_logo">Include School Logo</label>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="show_attendance" id="show_attendance" checked>
                            <label class="form-check-input-label font-weight-bold" for="show_attendance">Show Attendance Metrics</label>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="show_grades" id="show_grades" checked>
                            <label class="form-check-input-label font-weight-bold" for="show_grades">Display CBSE Letter Grades</label>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="remarks_box" id="remarks_box" checked>
                            <label class="form-check-input-label font-weight-bold" for="remarks_box">Enable Teacher Remarks Box</label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Save Configuration Layout</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Promotion Policies Setup -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Grade Promotion Policies</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.exams.reports.store-rule') }}" method="POST">
                        @csrf
                        <div class="form-group mb-3">
                            <label for="class_name" class="form-label font-weight-bold">Class Name</label>
                            <input type="text" name="class_name" id="class_name" class="form-control" required placeholder="e.g. Grade 10">
                        </div>

                        <div class="form-group mb-3">
                            <label for="min_overall_percentage" class="form-label font-weight-bold">Min Passing Percentage (%)</label>
                            <input type="number" step="0.1" name="min_overall_percentage" id="min_overall_percentage" class="form-control" required min="0" max="100" value="33.0">
                        </div>

                        <div class="form-group mb-3">
                            <label for="max_failed_subjects" class="form-label font-weight-bold">Max Failed Subjects Allowed</label>
                            <input type="number" name="max_failed_subjects" id="max_failed_subjects" class="form-control" required min="0" value="1">
                        </div>

                        <div class="form-group mb-3">
                            <label for="min_attendance_percentage" class="form-label font-weight-bold">Min Attendance Percentage (%)</label>
                            <input type="number" step="0.1" name="min_attendance_percentage" id="min_attendance_percentage" class="form-control" required min="0" max="100" value="75.0">
                        </div>

                        <div class="form-group mb-3">
                            <label for="academic_year" class="form-label font-weight-bold">Academic Year</label>
                            <input type="text" name="academic_year" id="academic_year" class="form-control" required placeholder="e.g. 2026">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Set Promotion Policy</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Student Promotion Evaluation Wizard -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Promotion Evaluator</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.exams.reports.check-promotion') }}" method="POST">
                        @csrf
                        <div class="form-group mb-3">
                            <label for="student_id" class="form-label font-weight-bold">Student ID</label>
                            <input type="number" name="student_id" id="student_id" class="form-control" required placeholder="e.g. Student ID">
                        </div>

                        <div class="form-group mb-3">
                            <label for="promo_year" class="form-label font-weight-bold">Academic Year</label>
                            <input type="text" name="academic_year" id="promo_year" class="form-control" required placeholder="e.g. 2026">
                        </div>

                        <button type="submit" class="btn btn-success w-100">Evaluate Eligibility Status</button>
                    </form>

                    @if(session('promotion_result'))
                        <hr>
                        <div class="alert @if(session('promotion_result')['promoted']) alert-success @else alert-danger @endif mt-3">
                            <h6 class="font-weight-bold">Result: {{ session('promotion_result')['promoted'] ? 'PROMOTED' : 'DETAINED' }}</h6>
                            <p class="small mb-1"><strong>Student:</strong> {{ session('promotion_result')['student'] }}</p>
                            <p class="small mb-1"><strong>Details:</strong> {{ session('promotion_result')['reason'] }}</p>
                            <ul class="small pl-3 mb-0">
                                <li><strong>Average score:</strong> {{ session('promotion_result')['stats']['percentage'] }}%</li>
                                <li><strong>Failed subjects:</strong> {{ session('promotion_result')['stats']['failed_count'] }}</li>
                                <li><strong>Attendance rate:</strong> {{ session('promotion_result')['stats']['attendance'] }}%</li>
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
