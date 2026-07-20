@extends('layouts.teacher')

@section('title', 'Exam Details - Teacher Panel')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas fa-file-alt"></i> Exam Details</h2>
            <p class="text-muted">View exam information and manage related actions</p>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> {{ $exam->name }}</h5>
                    @if($exam->created_by == Auth::guard('teacher')->id())
                    <div>
                        <a href="{{ route('teacher.exams.edit', $exam) }}" class="btn btn-sm btn-light">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                    </div>
                    @endif
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-school"></i> Class</h6>
                            <p class="text-muted">{{ $exam->schoolClass->name ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-book"></i> Subject</h6>
                                                        <p class="text-muted">{{ $exam->subjectInfo->name ?? 'N/A' }}</p>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-calendar"></i> Exam Date</h6>
                            <p class="text-muted">{{ \Carbon\Carbon::parse($exam->exam_date)->format('d F Y') }}</p>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-star"></i> Maximum Marks</h6>
                            <p class="text-muted">{{ $exam->max_marks }}</p>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-calendar-plus"></i> Created</h6>
                            <p class="text-muted">{{ $exam->created_at->format('d M Y') }}</p>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-sync"></i> Last Updated</h6>
                            <p class="text-muted">{{ $exam->updated_at->format('d M Y') }}</p>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <h6><i class="fas fa-info-circle"></i> Description</h6>
                            <p class="text-muted">
                                @if($exam->description)
                                    {{ $exam->description }}
                                @else
                                    <em>No description provided</em>
                                @endif
                            </p>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <h6><i class="fas fa-flag"></i> Status</h6>
                            @if($exam->exam_date > now())
                                <span class="badge bg-warning fs-6">Upcoming</span>
                                <p class="text-muted mt-2">This exam is scheduled for the future</p>
                            @elseif($exam->exam_date <= now() && $exam->exam_date >= now()->subDays(7))
                                <span class="badge bg-info fs-6">Recent</span>
                                <p class="text-muted mt-2">This exam was recently conducted</p>
                            @else
                                <span class="badge bg-secondary fs-6">Past</span>
                                <p class="text-muted mt-2">This exam has already been conducted</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-tasks"></i> Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('teacher.marks.index') }}" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload Marks
                        </a>
                        <a href="{{ route('teacher.exams.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Exams
                        </a>
                        @if($exam->created_by == Auth::guard('teacher')->id())
                        <form action="{{ route('teacher.exams.destroy', $exam) }}" 
                              method="POST" 
                              onsubmit="return confirm('Are you sure you want to delete this exam? This action cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger w-100">
                                <i class="fas fa-trash"></i> Delete Exam
                            </button>
                        </form>
                        @endif
                    </div>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> Exam Information</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-check text-success"></i> Academic Year: {{ $exam->academic_year }}</li>
                        <li class="mb-2"><i class="fas fa-check text-success"></i> Created by: {{ $exam->createdByTeacher->name ?? 'Unknown' }}</li>
                        <li class="mb-2"><i class="fas fa-check text-success"></i> Visible to: Admins, Exam Heads</li>
                        <li class="mb-2"><i class="fas fa-check text-success"></i> Students can view: Upcoming exams</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection