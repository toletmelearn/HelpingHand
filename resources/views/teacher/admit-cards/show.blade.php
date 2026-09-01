@extends('layouts.teacher')

@section('title', 'Admit Card - ' . ($admitCard->exam->name ?? 'Exam'))

@section('content')
<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h2><i class="fas fa-id-card"></i> Admit Card - {{ $admitCard->exam->name ?? 'Exam' }}</h2>
            <a href="{{ route('teacher.admit-cards.download-pdf', $admitCard) }}" class="btn btn-primary">Download PDF</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="border p-4" style="font-family: Arial, sans-serif;">
                <div class="text-center mb-4">
                    <h3>{{ $admitCard->data['school_name'] ?? 'School Name' }}</h3>
                    <p>{{ $admitCard->data['academic_session'] ?? 'Academic Session' }}</p>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Student Name:</strong> {{ $admitCard->data['student_name'] ?? 'N/A' }}</p>
                        <p><strong>Roll Number:</strong> {{ $admitCard->data['roll_number'] ?? 'N/A' }}</p>
                        <p><strong>Class:</strong> {{ $admitCard->data['class_name'] ?? 'N/A' }}</p>
                        <p><strong>Section:</strong> {{ $admitCard->data['section'] ?? 'N/A' }}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Exam Name:</strong> {{ $admitCard->data['exam_name'] ?? 'N/A' }}</p>
                        <p><strong>Exam Date:</strong> {{ $admitCard->data['exam_date'] ?? 'N/A' }}</p>
                        <p><strong>Exam Time:</strong> {{ $admitCard->data['exam_time'] ?? 'N/A' }}</p>
                    </div>
                </div>

                <div class="mt-4">
                    <h5>Instructions:</h5>
                    <p>{{ $admitCard->data['instructions'] ?? 'No instructions available.' }}</p>
                </div>
            </div>

            <div class="mt-3 text-center">
                <a href="{{ route('teacher.admit-cards.index') }}" class="btn btn-secondary">Back</a>
                <a href="{{ route('teacher.admit-cards.download-pdf', $admitCard) }}" class="btn btn-primary">Download PDF</a>
            </div>
        </div>
    </div>
</div>
@endsection
