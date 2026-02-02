@extends('layouts.admin')

@section('title', 'Exam Paper Details')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>
            <i class="bi bi-file-earmark-text"></i>
            {{ $examPaper->title }}
        </h2>
        <div>
            <a href="{{ route('exam-papers.edit', $examPaper) }}" class="btn btn-primary">
                <i class="bi bi-pencil"></i> Edit
            </a>
            <a href="{{ route('exam-papers.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <!-- Exam Paper Details -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Paper Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Subject:</strong> {{ $examPaper->subject }}</p>
                            <p><strong>Class/Section:</strong> {{ $examPaper->class_section }}</p>
                            <p><strong>Exam Type:</strong> 
                                <span class="badge bg-{{ $examPaper->getExamTypeInfo()['color'] }}">
                                    {{ $examPaper->exam_type }}
                                </span>
                            </p>
                            <p><strong>Paper Type:</strong> 
                                <span class="badge bg-{{ $examPaper->getPaperTypeBadge() }}">
                                    {{ $examPaper->paper_type }}
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Academic Year:</strong> {{ $examPaper->academic_year ?? 'N/A' }}</p>
                            <p><strong>Total Marks:</strong> {{ $examPaper->total_marks ?? 'N/A' }}</p>
                            <p><strong>Duration:</strong> {{ $examPaper->duration_minutes ? $examPaper->duration_minutes . ' minutes' : 'N/A' }}</p>
                            <p><strong>Status:</strong> 
                                @if($examPaper->is_published)
                                    <span class="badge bg-success">Published</span>
                                @else
                                    <span class="badge bg-secondary">Draft</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    
                    @if($examPaper->instructions)
                    <hr>
                    <h6><i class="bi bi-card-text"></i> Instructions:</h6>
                    <p>{{ $examPaper->instructions }}</p>
                    @endif
                    
                    @if($examPaper->exam_date)
                    <hr>
                    <p><strong>Exam Date:</strong> {{ $examPaper->exam_date->format('F j, Y') }}</p>
                    @endif
                    
                    @if($examPaper->exam_time)
                    <p><strong>Exam Time:</strong> {{ $examPaper->exam_time->format('h:i A') }}</p>
                    @endif
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- File Information -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-file"></i> File Information</h5>
                </div>
                <div class="card-body">
                    <p><strong>File Name:</strong> {{ $examPaper->file_name }}</p>
                    <p><strong>File Size:</strong> {{ $examPaper->getFileSizeFormatted() }}</p>
                    <p><strong>File Type:</strong> {{ strtoupper($examPaper->file_extension) }}</p>
                    <p><strong>Downloads:</strong> {{ $examPaper->download_count }}</p>
                    
                    <hr>
                    
                    <div class="d-grid gap-2">
                        <a href="{{ route('exam-papers.download', $examPaper) }}" class="btn btn-success">
                            <i class="bi bi-download"></i> Download File
                        </a>
                        
                        @if($examPaper->password_protected)
                            <div class="alert alert-warning">
                                <i class="bi bi-shield-lock"></i> Password Protected
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            
            <!-- Access Information -->
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="bi bi-key"></i> Access Information</h5>
                </div>
                <div class="card-body">
                    <p><strong>Access Level:</strong> {{ ucfirst(str_replace('_', ' ', $examPaper->access_level)) }}</p>
                    
                    @if($examPaper->uploadedBy)
                        <p><strong>Uploaded By:</strong> {{ $examPaper->uploadedBy->name }}</p>
                    @endif
                    
                    @if($examPaper->approvedBy)
                        <p><strong>Approved By:</strong> {{ $examPaper->approvedBy->name }}</p>
                    @endif
                    
                    <p><strong>Created:</strong> {{ $examPaper->created_at->format('M j, Y g:i A') }}</p>
                    <p><strong>Last Updated:</strong> {{ $examPaper->updated_at->format('M j, Y g:i A') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
