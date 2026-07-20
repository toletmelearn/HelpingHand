@extends('layouts.admin')

@section('title', 'Final Result - ' . $student->name)

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="bi bi-award"></i> Final Result Card (CBSE Format)
                        </h4>
                        <button class="btn btn-light" onclick="window.print()">
                            <i class="bi bi-printer"></i> Print Result
                        </button>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Student Information -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5>Student Details</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Name:</strong></td>
                                    <td>{{ $student->name }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Class:</strong></td>
                                    <td>{{ $student->class_name ?? 'N/A' }} {{ $student->section ? '- ' . $student->section : '' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Roll No:</strong></td>
                                    <td>{{ $student->roll_number ?? $student->id }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5>Exam Details</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Exam:</strong></td>
                                    <td>{{ $examName }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Academic Year:</strong></td>
                                    <td>{{ date('Y') . '-' . (date('Y') + 1) }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Date Generated:</strong></td>
                                    <td>{{ date('d M Y') }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Subject-wise Results -->
                    <h5 class="mb-3">Subject-wise Performance</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Subject</th>
                                    <th>Marks Obtained</th>
                                    <th>Total Marks</th>
                                    <th>Percentage</th>
                                    <th>Grade</th>
                                    <th>Result</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($results as $result)
                                <tr>
                                    <td><strong>{{ $result->subject }}</strong></td>
                                    <td class="text-center">{{ $result->marks_obtained }}</td>
                                    <td class="text-center">{{ $result->total_marks }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-{{ $result->percentage >= 60 ? 'success' : ($result->percentage >= 40 ? 'warning' : 'danger') }}">
                                            {{ $result->percentage }}%
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary">{{ $result->grade }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-{{ $result->result_status == 'pass' ? 'success' : 'danger' }}">
                                            {{ strtoupper($result->result_status) }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Final Summary -->
                    <div class="row mt-4">
                        <div class="col-md-8">
                            <h5>Final Summary</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <tr>
                                        <th style="width: 50%">Total Marks Obtained</th>
                                        <td><strong class="text-primary fs-5">{{ $totalObtained }}</strong></td>
                                    </tr>
                                    <tr>
                                        <th>Total Maximum Marks</th>
                                        <td>{{ $totalMax }}</td>
                                    </tr>
                                    <tr>
                                        <th>Overall Percentage</th>
                                        <td>
                                            <span class="badge bg-{{ $percentage >= 60 ? 'success' : ($percentage >= 40 ? 'warning' : 'danger') }} fs-6">
                                                {{ $percentage }}%
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Overall Grade</th>
                                        <td><strong class="fs-5">{{ $overallGrade }}</strong></td>
                                    </tr>
                                    <tr>
                                        <th>Final Result</th>
                                        <td>
                                            <span class="badge bg-{{ $finalResult == 'PASS' ? 'success' : 'danger' }} fs-6" style="font-size: 1.2em;">
                                                {{ $finalResult }}
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Performance Analysis</h5>
                                    <div class="mt-3">
                                        @if($percentage >= 90)
                                            <div class="alert alert-success">
                                                <i class="bi bi-stars"></i> Outstanding Performance!
                                            </div>
                                        @elseif($percentage >= 75)
                                            <div class="alert alert-success">
                                                <i class="bi bi-award"></i> Excellent Work!
                                            </div>
                                        @elseif($percentage >= 60)
                                            <div class="alert alert-info">
                                                <i class="bi bi-hand-thumbs-up"></i> Good Performance
                                            </div>
                                        @elseif($percentage >= 40)
                                            <div class="alert alert-warning">
                                                <i class="bi bi-exclamation-triangle"></i> Satisfactory
                                            </div>
                                        @else
                                            <div class="alert alert-danger">
                                                <i class="bi bi-emoji-frown"></i> Needs Improvement
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Signatures -->
                    <div class="row mt-5">
                        <div class="col-md-4 text-center">
                            <hr>
                            <p>Class Teacher</p>
                        </div>
                        <div class="col-md-4 text-center">
                            <hr>
                            <p>Exam Coordinator</p>
                        </div>
                        <div class="col-md-4 text-center">
                            <hr>
                            <p>Principal</p>
                        </div>
                    </div>

                    <div class="text-center mt-4 text-muted">
                        <small>
                            <i class="bi bi-info-circle"></i> This is a computer-generated result card. 
                            HelpingHand School ERP System
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .btn, .alert {
        display: none !important;
    }
    
    .card-header {
        background: #000 !important;
        color: white !important;
        -webkit-print-color-adjust: exact;
        color-adjust: exact;
    }
    
    .table-dark {
        background-color: #343a40 !important;
        -webkit-print-color-adjust: exact;
        color-adjust: exact;
    }
    
    .badge {
        border: 1px solid #000 !important;
        -webkit-print-color-adjust: exact;
        color-adjust: exact;
    }
}
</style>
@endsection