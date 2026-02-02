@extends('layouts.admin')

@section('title', 'View Exam Paper - ' . $examPaper->title)

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Exam Paper Details</h4>
                    <div>
                        <a href="{{ route('admin.exam-papers.edit', $examPaper) }}" class="btn btn-primary">Edit</a>
                        <a href="{{ route('admin.exam-papers.index') }}" class="btn btn-secondary">Back to List</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="30%">Title:</th>
                                    <td>{{ $examPaper->title }}</td>
                                </tr>
                                <tr>
                                    <th>Subject:</th>
                                    <td>{{ $examPaper->subject }}</td>
                                </tr>
                                <tr>
                                    <th>Class Section:</th>
                                    <td>{{ $examPaper->class_section }}</td>
                                </tr>
                                <tr>
                                    <th>Exam Type:</th>
                                    <td>
                                        <span class="badge bg-{{ $examPaper->getExamTypeInfo()['color'] }}">
                                            {{ $examPaper->getExamTypeInfo()['type'] }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Paper Type:</th>
                                    <td>
                                        <span class="badge bg-{{ $examPaper->getPaperTypeBadge() }}">
                                            {{ $examPaper->paper_type }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <span class="badge bg-{{ 
                                            $examPaper->status == 'draft' ? 'secondary' : 
                                            ($examPaper->status == 'submitted' ? 'warning' : 
                                            ($examPaper->status == 'approved' ? 'success' : 
                                            ($examPaper->status == 'locked' ? 'dark' : 'danger'))) 
                                        }}">
                                            {{ ucfirst($examPaper->status) }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="30%">Uploaded By:</th>
                                    <td>{{ $examPaper->uploadedBy->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Created:</th>
                                    <td>{{ $examPaper->created_at->format('d-m-Y H:i') }}</td>
                                </tr>
                                <tr>
                                    <th>Academic Year:</th>
                                    <td>{{ $examPaper->academic_year ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Semester:</th>
                                    <td>{{ $examPaper->semester ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Total Marks:</th>
                                    <td>{{ $examPaper->total_marks ?? $examPaper->auto_calculated_total ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Duration:</th>
                                    <td>{{ $examPaper->duration_minutes ? $examPaper->duration_minutes . ' mins' : 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    @if($examPaper->instructions)
                    <div class="mb-3">
                        <h6>Instructions:</h6>
                        <p>{!! nl2br(e($examPaper->instructions)) !!}</p>
                    </div>
                    @endif
                    
                    @if($examPaper->questions_data)
                    <div class="mb-3">
                        <h6>Questions:</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Question</th>
                                        <th>Type</th>
                                        <th>Marks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($examPaper->questions_data as $index => $question)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{!! nl2br(e($question['text'] ?? '')) !!}</td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                {{ ucfirst(str_replace('-', ' ', $question['type'] ?? 'short-answer')) }}
                                            </span>
                                        </td>
                                        <td>{{ $question['marks'] ?? 0 }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif
                    
                    @if($examPaper->file_path)
                    <div class="mb-3">
                        <h6>File Attachment:</h6>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-file-pdf me-2 text-danger" style="font-size: 24px;"></i>
                            <div>
                                <a href="{{ Storage::url($examPaper->file_path) }}" target="_blank" class="text-decoration-none">
                                    {{ $examPaper->file_name }}
                                </a>
                                <br>
                                <small class="text-muted">{{ $examPaper->getFileSizeFormatted() }}</small>
                            </div>
                        </div>
                    </div>
                    @endif
                    
                    <div class="mt-4">
                        <h6>Actions:</h6>
                        <div class="btn-group" role="group">
                            @if($examPaper->status == 'draft')
                                <a href="{{ route('admin.exam-papers.submit', $examPaper) }}" class="btn btn-warning" 
                                   onclick="return confirm('Are you sure you want to submit this paper for approval?')">Submit for Approval</a>
                            @elseif($examPaper->status == 'submitted')
                                <a href="{{ route('admin.exam-papers.approve', $examPaper) }}" class="btn btn-success" 
                                   onclick="return confirm('Are you sure you want to approve this paper?')">Approve</a>
                                <a href="{{ route('admin.exam-papers.edit', $examPaper) }}" class="btn btn-primary">Edit</a>
                            @elseif($examPaper->status == 'approved')
                                <a href="{{ route('admin.exam-papers.lock', $examPaper) }}" class="btn btn-dark" 
                                   onclick="return confirm('Are you sure you want to lock this paper?')">Lock</a>
                                <a href="{{ route('admin.exam-papers.download', $examPaper) }}" class="btn btn-info">Download</a>
                                <a href="{{ route('admin.exam-papers.print', $examPaper) }}" class="btn btn-secondary">Print</a>
                            @endif
                            
                            <a href="{{ route('admin.exam-papers.clone', $examPaper) }}" class="btn btn-outline-secondary">Clone</a>
                            
                            <form action="{{ route('admin.exam-papers.destroy', $examPaper) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger" 
                                        onclick="return confirm('Are you sure you want to delete this paper?')">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
