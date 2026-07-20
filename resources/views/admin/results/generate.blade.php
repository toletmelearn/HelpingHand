@extends('layouts.admin')

@section('title', 'Generate Report Card')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="bi bi-file-earmark-text"></i> Generate Report Card
                    </h4>
                </div>
                <div class="card-body">
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <form action="{{ route('admin.results.generate') }}" method="POST">
                        @csrf
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> 
                            <strong>Note:</strong> Results must already exist for the selected student and exam. 
                            This tool generates formatted report cards from existing data.
                        </div>

                        <div class="form-group mb-4">
                            <label for="student_id" class="form-label">
                                <i class="bi bi-person"></i> Select Student *
                            </label>
                            <select name="student_id" id="student_id" class="form-control form-control-lg @error('student_id') is-invalid @enderror" required>
                                <option value="">Choose a student...</option>
                                @foreach($students as $student)
                                    <option value="{{ $student->id }}" {{ old('student_id') == $student->id ? 'selected' : '' }}>
                                        {{ $student->name }} 
                                        @if($student->class_name)
                                            ({{ $student->class_name }}{{ $student->section ? ' - ' . $student->section : '' }})
                                        @endif
                                        @if($student->roll_number)
                                            - Roll: {{ $student->roll_number }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('student_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group mb-4">
                            <label for="exam_id" class="form-label">
                                <i class="bi bi-clipboard-check"></i> Select Exam *
                            </label>
                            <select name="exam_id" id="exam_id" class="form-control form-control-lg @error('exam_id') is-invalid @enderror" required>
                                <option value="">Choose an exam...</option>
                                @foreach($exams as $exam)
                                    <option value="{{ $exam->id }}" {{ old('exam_id') == $exam->id ? 'selected' : '' }}>
                                        {{ $exam->name }}
                                        @if($exam->subject)
                                            - {{ $exam->subject }}
                                        @endif
                                        @if($exam->term)
                                            ({{ $exam->term }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('exam_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="{{ route('admin.results.index') }}" class="btn btn-secondary btn-lg">
                                <i class="bi bi-arrow-left"></i> Back to Results
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-file-earmark-bar-graph"></i> Generate Report Card
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-lightbulb"></i> How It Works
                    </h5>
                </div>
                <div class="card-body">
                    <ol>
                        <li>Select a student from the dropdown</li>
                        <li>Select an exam they have taken</li>
                        <li>Click "Generate Report Card"</li>
                        <li>View the professionally formatted CBSE result card</li>
                        <li>Print or save the report card as needed</li>
                    </ol>
                    <div class="alert alert-warning mt-3">
                        <i class="bi bi-exclamation-triangle"></i> 
                        <strong>Important:</strong> Only students with existing results for the selected exam will generate a report card.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection