@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1>Lesson Plan Details</h1>
            <p class="mb-4">View detailed information about the lesson plan</p>
            
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Lesson Plan Information</h5>
                        <div>
                            <a href="{{ route('admin.lesson-plans.index') }}" class="btn btn-secondary me-2">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                            <a href="{{ route('admin.lesson-plans.edit', $lessonPlan) }}" class="btn btn-primary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Teacher</h6>
                            <p class="text-muted">{{ $lessonPlan->teacher->name }}</p>
                            
                            <h6>Class</h6>
                            <p class="text-muted">{{ $lessonPlan->class->name }} - {{ $lessonPlan->section->name }}</p>
                            
                            <h6>Subject</h6>
                            <p class="text-muted">{{ $lessonPlan->subject->name }}</p>
                            
                            <h6>Date</h6>
                            <p class="text-muted">{{ $lessonPlan->date->format('M d, Y') }}</p>
                            
                            <h6>Plan Type</h6>
                            <p class="text-muted">
                                <span class="badge bg-{{ $lessonPlan->plan_type === 'daily' ? 'primary' : ($lessonPlan->plan_type === 'weekly' ? 'success' : 'warning') }}">
                                    {{ ucfirst($lessonPlan->plan_type) }}
                                </span>
                            </p>
                        </div>
                        
                        <div class="col-md-6">
                            <h6>Created By</h6>
                            <p class="text-muted">{{ $lessonPlan->createdBy->name ?? 'N/A' }}</p>
                            
                            <h6>Modified By</h6>
                            <p class="text-muted">{{ $lessonPlan->modifiedBy->name ?? 'N/A' }}</p>
                            
                            <h6>Created At</h6>
                            <p class="text-muted">{{ $lessonPlan->created_at->format('M d, Y g:i A') }}</p>
                            
                            <h6>Updated At</h6>
                            <p class="text-muted">{{ $lessonPlan->updated_at->format('M d, Y g:i A') }}</p>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-12">
                            <h6>Topic / Chapter Name</h6>
                            <p class="text-muted">{{ $lessonPlan->topic }}</p>
                            
                            <h6>Learning Objectives</h6>
                            <p class="text-muted">{{ $lessonPlan->learning_objectives }}</p>
                            
                            <h6>Teaching Method</h6>
                            <p class="text-muted">{{ $lessonPlan->teaching_method ?: 'Not specified' }}</p>
                            
                            <h6>Homework / Classwork</h6>
                            <p class="text-muted">{{ $lessonPlan->homework_classwork }}</p>
                            
                            <h6>Books / Notebooks Required</h6>
                            <p class="text-muted">{{ $lessonPlan->books_notebooks_required }}</p>
                            
                            <h6>Submission / Assessment Notes</h6>
                            <p class="text-muted">{{ $lessonPlan->submission_assessment_notes ?: 'None' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
