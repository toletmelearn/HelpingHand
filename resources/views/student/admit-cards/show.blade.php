@extends('layouts.app')

@section('title', 'Admit Card - ' . ($admitCard->exam->name ?? 'Exam'))

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Admit Card - {{ $admitCard->exam->name ?? 'Exam' }}</h4>
                    <a href="{{ route('student.admit-cards.download-pdf', $admitCard) }}" class="btn btn-primary">Download PDF</a>
                </div>
                <div class="card-body">
                    <div class="border p-4" style="font-family: Arial, sans-serif;">
                        <!-- Header -->
                        <div class="text-center mb-4">
                            <h2>{{ $admitCard->data['school_name'] ?? 'School Name' }}</h2>
                            <p>{{ $admitCard->data['academic_session'] ?? 'Academic Session' }}</p>
                        </div>

                        <!-- Main Content -->
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Student Name:</strong> {{ $admitCard->data['student_name'] ?? 'N/A' }}</p>
                                <p><strong>Roll Number:</strong> {{ $admitCard->data['roll_number'] ?? 'N/A' }}</p>
                                <p><strong>Class:</strong> {{ $admitCard->data['class_name'] ?? 'N/A' }}</p>
                                <p><strong>Section:</strong> {{ $admitCard->data['section'] ?? 'N/A' }}</p>
                                <p><strong>Date of Birth:</strong> {{ $admitCard->data['dob'] ?? 'N/A' }}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Exam Name:</strong> {{ $admitCard->data['exam_name'] ?? 'N/A' }}</p>
                                <p><strong>Exam Date:</strong> {{ $admitCard->data['exam_date'] ?? 'N/A' }}</p>
                                <p><strong>Exam Time:</strong> {{ $admitCard->data['exam_time'] ?? 'N/A' }}</p>
                            </div>
                        </div>

                        <!-- Subjects -->
                        <div class="mt-4">
                            <h5>Subjects:</h5>
                            <ul>
                                @foreach($admitCard->data['subjects'] ?? [] as $subject)
                                    <li>{{ $subject }}</li>
                                @endforeach
                            </ul>
                        </div>

                        <!-- Instructions -->
                        <div class="mt-4">
                            <h5>Instructions:</h5>
                            <p>{{ $admitCard->data['instructions'] ?? 'No instructions available.' }}</p>
                        </div>

                        <!-- Footer -->
                        <div class="mt-5 text-center">
                            <p><em>Please bring this admit card along with valid ID proof for the examination.</em></p>
                            <div class="mt-4">
                                <p><strong>Principal Signature</strong></p>
                                <hr style="width: 150px; margin: auto;">
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 text-center">
                        <a href="{{ route('student.admit-cards.index') }}" class="btn btn-secondary">Back to My Admit Cards</a>
                        <a href="{{ route('student.admit-cards.download-pdf', $admitCard) }}" class="btn btn-primary">Download PDF</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection