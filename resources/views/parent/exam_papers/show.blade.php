@extends('layouts.parent')

@section('title', 'Exam Paper Details')

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4>{{ $examPaper->title }}</h4>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>Subject:</strong> {{ $examPaper->subject ?? 'N/A' }}</p>
                            <p><strong>Class:</strong> {{ $student->class ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Exam Type:</strong> {{ $examPaper->exam_type ?? 'N/A' }}</p>
                            <p><strong>Academic Year:</strong> {{ $examPaper->academic_year ?? 'N/A' }}</p>
                        </div>
                    </div>

                    @if($examPaper->instructions)
                    <div class="mb-3">
                        <h5>Instructions:</h5>
                        <div class="alert alert-info">
                            {!! nl2br(e($examPaper->instructions)) !!}
                        </div>
                    </div>
                    @endif

                    @if($examPaper->duration_minutes)
                    <p><strong>Duration:</strong> {{ $examPaper->duration_minutes }} minutes</p>
                    @endif

                    @if($examPaper->total_marks)
                    <p><strong>Total Marks:</strong> {{ $examPaper->total_marks }}</p>
                    @endif

                    @if($examPaper->file_path)
                    <div class="mt-4">
                        <a href="{{ route('parent.exam-papers.download', $examPaper->id) }}" class="btn btn-primary">
                            <i class="fas fa-download"></i> Download Exam Paper
                        </a>
                    </div>
                    @else
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle"></i> No file available for download.
                    </div>
                    @endif

                    <div class="mt-4">
                        <a href="{{ route('parent.exam-papers.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Exam Papers
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
