@extends('layouts.teacher')

@section('title', 'Exam Paper Details')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Exam Paper Details</h3>
                    <div class="card-tools">
                        <a href="{{ route('teacher.exam-papers.index') }}" class="btn btn-default">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><strong>Title:</strong></label>
                                <p>{{ $examPaper->title }}</p>
                            </div>
                            
                            <div class="form-group">
                                <label><strong>Exam:</strong></label>
                                <p>{{ $examPaper->exam->name ?? 'N/A' }}</p>
                            </div>
                            
                            <div class="form-group">
                                <label><strong>Class:</strong></label>
                                <p>{{ $examPaper->class->name ?? 'N/A' }}</p>
                            </div>
                            
                            <div class="form-group">
                                <label><strong>Subject:</strong></label>
                                <p>{{ $examPaper->subject }}</p>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><strong>Status:</strong></label>
                                <p>
                                    @if($examPaper->status == 'draft')
                                        <span class="badge badge-warning">Draft</span>
                                    @elseif($examPaper->status == 'submitted')
                                        <span class="badge badge-info">Submitted</span>
                                    @elseif($examPaper->status == 'approved')
                                        <span class="badge badge-success">Approved</span>
                                    @elseif($examPaper->status == 'rejected')
                                        <span class="badge badge-danger">Rejected</span>
                                    @else
                                        <span class="badge badge-secondary">{{ ucfirst($examPaper->status) }}</span>
                                    @endif
                                </p>
                            </div>
                            
                            <div class="form-group">
                                <label><strong>Created Date:</strong></label>
                                <p>{{ $examPaper->created_at->format('d M Y h:i A') }}</p>
                            </div>
                            
                            <div class="form-group">
                                <label><strong>File:</strong></label>
                                <p>
                                    @if($examPaper->file_path)
                                        <a href="{{ asset('storage/'.$examPaper->file_path) }}" 
                                           class="btn btn-sm btn-secondary" target="_blank">
                                            <i class="fas fa-download"></i> View File
                                        </a>
                                    @else
                                        <span class="text-muted">No file uploaded</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    @if($examPaper->instructions)
                    <div class="form-group">
                        <label><strong>Instructions:</strong></label>
                        <div class="border p-3 bg-light">
                            {!! nl2br(e($examPaper->instructions)) !!}
                        </div>
                    </div>
                    @endif
                    
                    @if($examPaper->paper_content)
                    <div class="form-group">
                        <label><strong>Paper Content:</strong></label>
                        <div class="border p-3 bg-light">
                            {!! nl2br(e($examPaper->paper_content)) !!}
                        </div>
                    </div>
                    @endif
                </div>
                <!-- /.card-body -->
                
                <div class="card-footer">
                    @if($examPaper->status == 'draft' || $examPaper->status == 'rejected')
                    <a href="{{ route('teacher.exam-papers.edit', $examPaper->id) }}" 
                       class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    @endif
                    
                    @if($examPaper->status == 'draft')
                    <form method="POST" action="{{ route('admin.exam-papers.submit', $examPaper->id) }}" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-success"
                                onclick="return confirm('Are you sure you want to submit this exam paper for approval?')">
                            <i class="fas fa-paper-plane"></i> Submit for Approval
                        </button>
                    </form>
                    @endif
                </div>
            </div>
            <!-- /.card -->
        </div>
    </div>
</div>
@endsection