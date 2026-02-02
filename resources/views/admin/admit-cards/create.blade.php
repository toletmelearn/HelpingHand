@extends('layouts.admin')

@section('title', 'Generate Admit Cards')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4>Generate Admit Cards</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.admit-cards.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label for="exam_id" class="form-label">Select Exam *</label>
                            <select class="form-control @error('exam_id') is-invalid @enderror" 
                                    id="exam_id" name="exam_id" required>
                                <option value="">Select Exam</option>
                                @foreach($exams as $exam)
                                    <option value="{{ $exam->id }}">{{ $exam->name }} - {{ $exam->class_name }} ({{ $exam->subject }})</option>
                                @endforeach
                            </select>
                            @error('exam_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="admit_card_format_id" class="form-label">Select Format *</label>
                            <select class="form-control @error('admit_card_format_id') is-invalid @enderror" 
                                    id="admit_card_format_id" name="admit_card_format_id" required>
                                <option value="">Select Format</option>
                                @foreach($formats as $format)
                                    <option value="{{ $format->id }}">{{ $format->name }}</option>
                                @endforeach
                            </select>
                            @error('admit_card_format_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="academic_session" class="form-label">Academic Session *</label>
                            <input type="text" class="form-control @error('academic_session') is-invalid @enderror" 
                                   id="academic_session" name="academic_session" value="{{ old('academic_session', date('Y').'-'.(date('Y')+1)) }}" required>
                            <div class="form-text">Format: YYYY-YYYY (e.g., 2024-2025)</div>
                            @error('academic_session')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="{{ route('admin.admit-cards.index') }}" class="btn btn-secondary me-md-2">Cancel</a>
                            <button type="submit" class="btn btn-primary">Generate Admit Cards</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
