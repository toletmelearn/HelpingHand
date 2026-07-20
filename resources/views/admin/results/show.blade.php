@extends('layouts.admin')

@section('title', 'Result Details')

@section('content')

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Student Result Details</h3>
        <a href="{{ route('admin.results.report-card', ['studentId' => $result->student_id, 'examId' => $result->exam_id]) }}" class="btn btn-success" target="_blank">
            <i class="bi bi-printer"></i> Print Report Card
        </a>
    </div>
    
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="text-muted mb-3">Student Information</h5>
                    <p><strong>Student Name:</strong> {{ $result->student->name ?? 'N/A' }}</p>
                    <p><strong>Class:</strong> {{ $result->student->class_name ?? 'N/A' }}</p>
                    <p><strong>Section:</strong> {{ $result->student->section ?? 'N/A' }}</p>
                </div>
                <div class="col-md-6">
                    <h5 class="text-muted mb-3">Exam Information</h5>
                    <p><strong>Exam:</strong> {{ $result->exam->name ?? 'N/A' }}</p>
                    <p><strong>Subject:</strong> {{ $result->subject ?? '' }}</p>
                    <p><strong>Academic Year:</strong> {{ $result->academic_year ?? date('Y') }}</p>
                    <p><strong>Term:</strong> {{ $result->term ?? '' }}</p>
                </div>
            </div>
            
            <hr>
            
            <div class="row">
                <div class="col-md-12">
                    <h5 class="text-muted mb-3">Result Details</h5>
                    <table class="table table-bordered">
                        <tr>
                            <th style="width:25%">Marks Obtained</th>
                            <td style="width:25%"><strong class="text-primary">{{ $result->marks_obtained ?? '' }}</strong></td>
                            <th style="width:25%">Total Marks</th>
                            <td style="width:25%">{{ $result->total_marks ?? '' }}</td>
                        </tr>
                        <tr>
                            <th>Percentage</th>
                            <td>
                                <span class="badge bg-{{ $result->percentage >= 60 ? 'success' : ($result->percentage >= 40 ? 'warning' : 'danger') }} fs-6">
                                    {{ $result->percentage ?? '' }}%
                                </span>
                            </td>
                            <th>Grade</th>
                            <td><strong class="fs-5">{{ $result->grade ?? '' }}</strong></td>
                        </tr>
                        <tr>
                            <th>Result Status</th>
                            <td colspan="3">
                                <span class="badge bg-{{ $result->result_status == 'pass' ? 'success' : 'danger' }} fs-6">
                                    {{ strtoupper($result->result_status ?? '') }}
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            @if($result->comments)
            <hr>
            <div class="row">
                <div class="col-md-12">
                    <h5 class="text-muted mb-3">Teacher's Remarks</h5>
                    <p class="alert alert-info">{{ $result->comments }}</p>
                </div>
            </div>
            @endif
            
            <hr>
            
            <div class="d-flex justify-content-between mt-3">
                <a href="{{ route('admin.results.index') }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Results
                </a>
                <div>
                    <a href="{{ route('admin.results.edit', $result) }}" class="btn btn-warning">
                        <i class="bi bi-pencil"></i> Edit Result
                    </a>
                    <a href="{{ route('admin.results.report-card', ['studentId' => $result->student_id, 'examId' => $result->exam_id]) }}" class="btn btn-success" target="_blank">
                        <i class="bi bi-printer"></i> Print Report Card
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection