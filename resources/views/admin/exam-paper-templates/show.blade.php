@extends('layouts.admin')

@section('content')

<div class="container">
    <h3>Exam Paper Template Details</h3>
    
    <div class="card">
        <div class="card-body">
            <p><strong>Template Name:</strong> {{ $examPaperTemplate->name ?? '' }}</p>
            <p><strong>Subject:</strong> {{ $examPaperTemplate->subject ?? '' }}</p>
            <p><strong>Class:</strong> {{ $examPaperTemplate->class_section ?? '' }}</p>
            <p><strong>Status:</strong> {{ $examPaperTemplate->is_active ? 'Active' : 'Inactive' }}</p>
            <p><strong>Created At:</strong> {{ $examPaperTemplate->created_at->format('d-m-Y H:i') }}</p>
            <p><strong>Updated At:</strong> {{ $examPaperTemplate->updated_at->format('d-m-Y H:i') }}</p>
            
            @if($examPaperTemplate->description)
                <hr>
                <h5>Description</h5>
                <div>{{ $examPaperTemplate->description }}</div>
            @endif
            
            <hr>
            <h5>Header</h5>
            <div>{!! $examPaperTemplate->header_content ?? '' !!}</div>

            <h5>Instructions</h5>
            <div>{!! $examPaperTemplate->instruction_block ?? '' !!}</div>

            <h5>Template Content</h5>
            <div>{!! $examPaperTemplate->template_content ?? '' !!}</div>

            <h5>Footer</h5>
            <div>{!! $examPaperTemplate->footer_content ?? '' !!}</div>
            
            <hr>
            <div class="mt-3">
                <a href="{{ route('admin.exam-paper-templates.index') }}" class="btn btn-secondary">Back to Templates</a>
                <a href="{{ route('admin.exam-paper-templates.edit', $examPaperTemplate) }}" class="btn btn-primary">Edit Template</a>
            </div>
        </div>
    </div>
</div>
@endsection