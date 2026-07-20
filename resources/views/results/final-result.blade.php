@extends('layouts.app')

@section('title', 'Final Result Card')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card">
                <div class="card-header bg-primary text-white text-center">
                    <h3 class="mb-0">
                        <i class="fas fa-graduation-cap me-2"></i>
                        Final Result Card
                    </h3>
                </div>
                
                <div class="card-body">
                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-center gap-2 mb-4">
                        <a href="{{ route('results.final.pdf', [$student_id ?? '', $exam_id ?? '']) }}" 
                           class="btn btn-danger" target="_blank">
                            <i class="fas fa-file-pdf me-1"></i> Download PDF
                        </a>
                        <button onclick="window.print()" class="btn btn-success">
                            <i class="fas fa-print me-1"></i> Print Result
                        </button>
                        <a href="{{ route('results.verification.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back to Verification
                        </a>
                    </div>

                    <!-- Result Card -->
                    <div class="result-card border rounded p-4" style="max-width: 900px; margin: 0 auto;">
                        <!-- Header -->
                        <div class="text-center mb-4">
                            <div class="row align-items-center">
                                <div class="col-2 text-start">
                                    <img src="{{ $school_logo }}" alt="School Logo" style="width: 80px; height: 80px;">
                                </div>
                                <div class="col-8">
                                    <h2 class="fw-bold mb-2">{{ $school_name }}</h2>
                                    <p class="mb-1">{{ $school_address }}</p>
                                    <p class="mb-1">Affiliation No: {{ $affiliation_no }}</p>
                                    <h4 class="mt-3 fw-bold">ACADEMIC RESULT CARD</h4>
                                    <p class="mb-0">Session: {{ $academic_year }}</p>
                                </div>
                                <div class="col-2 text-end">
                                    <img src="{{ $student_photo }}" alt="Student Photo" 
                                         style="width: 80px; height: 90px; border: 1px solid #000;">
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Student Information -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <p><strong>Student Name:</strong> {{ $student_name }}</p>
                                <p><strong>Father's Name:</strong> {{ $father_name }}</p>
                                <p><strong>Admission No:</strong> {{ $admission_no }}</p>
                                <p><strong>DOB:</strong> {{ $dob }}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Class:</strong> {{ $class }} - {{ $section }}</p>
                                <p><strong>Roll No:</strong> {{ $roll_no }}</p>
                                <p><strong>Exam:</strong> {{ $exam_name }}</p>
                                <p><strong>Term:</strong> {{ $term }}</p>
                            </div>
                        </div>

                        <!-- Results Table -->
                        {!! $results_table !!}

                        <!-- Summary -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <p><strong>Total Marks Obtained:</strong> {{ $total_obtained }}</p>
                                <p><strong>Total Marks:</strong> {{ $total_marks }}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Overall Percentage:</strong> 
                                    <span class="badge bg-{{ $overall_percentage >= 75 ? 'success' : ($overall_percentage >= 60 ? 'warning' : 'danger') }}">
                                        {{ $overall_percentage }}%
                                    </span>
                                </p>
                                <p><strong>Overall Grade:</strong> 
                                    <span class="badge bg-{{ in_array($overall_grade, ['A1', 'A2']) ? 'success' : (in_array($overall_grade, ['B1', 'B2']) ? 'primary' : 'secondary') }}">
                                        {{ $overall_grade }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <!-- Final Result -->
                        <div class="text-center mb-4">
                            <h4 class="fw-bold">FINAL RESULT: 
                                <span class="badge bg-{{ $final_result === 'PASS' ? 'success' : 'danger' }}" style="font-size: 1.5rem;">
                                    {{ $final_result }}
                                </span>
                            </h4>
                        </div>

                        <!-- Attendance -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <p><strong>Attendance:</strong> {{ $attendance }} days</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Working Days:</strong> {{ $working_days }} days</p>
                            </div>
                        </div>

                        <!-- Remarks -->
                        <div class="mb-4">
                            <p><strong>Class Teacher Remarks:</strong> {{ $remarks }}</p>
                        </div>

                        <!-- Signatures -->
                        <div class="row text-center mt-5">
                            <div class="col-md-4">
                                <p>___________________________</p>
                                <p><strong>Class Teacher</strong></p>
                            </div>
                            <div class="col-md-4">
                                <p>___________________________</p>
                                <p><strong>Principal</strong></p>
                            </div>
                            <div class="col-md-4">
                                <p>___________________________</p>
                                <p><strong>Parent/Guardian</strong></p>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="text-center mt-4 pt-3 border-top">
                            <p class="text-muted small">
                                Generated by: {{ $generated_by }} | Date: {{ $generated_date }}<br>
                                This is a computer generated result card | Powered by HelpingHand ERP
                            </p>
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
@media print {
    body * {
        visibility: hidden;
    }
    .result-card, .result-card * {
        visibility: visible;
    }
    .result-card {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    .no-print {
        display: none !important;
    }
}

.result-card {
    font-family: Arial, sans-serif;
    font-size: 14px;
}

.badge {
    font-size: 1em;
}

.card-header {
    border-bottom: none;
}
</style>
@endpush