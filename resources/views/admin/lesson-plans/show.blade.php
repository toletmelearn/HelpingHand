@extends('layouts.admin')

@section('title', 'Lesson Plan Details')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4><i class="fas fa-book"></i> Lesson Plan Details</h4>
                    <a href="{{ route('admin.lesson-plans.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
                
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Teacher:</strong>
                            <p>{{ $lessonPlan->teacher->name ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-6">
                            <strong>Class:</strong>
                            <p>{{ $lessonPlan->class->name ?? 'N/A' }}</p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Subject:</strong>
                            <p>{{ $lessonPlan->subject->name ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-6">
                            <strong>Date:</strong>
                            <p>{{ $lessonPlan->date }}</p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Title:</strong>
                            <p>{{ $lessonPlan->title }}</p>
                        </div>
                        <div class="col-md-6">
                            <strong>Plan Type:</strong>
                            <p>{{ ucfirst($lessonPlan->plan_type) }}</p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Duration:</strong>
                            <p>{{ $lessonPlan->duration }}</p>
                        </div>
                        <div class="col-md-6">
                            <strong>Visible to Parents:</strong>
                            <p>
                                <span class="badge bg-{{ $lessonPlan->show_to_parents ? 'success' : 'secondary' }}">
                                    {{ $lessonPlan->show_to_parents ? 'Yes' : 'No' }}
                                </span>
                            </p>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Topic:</strong>
                        <p>{!! nl2br(e($lessonPlan->topic)) !!}</p>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Learning Objectives:</strong>
                        <p>{!! nl2br(e($lessonPlan->learning_objectives)) !!}</p>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Teaching Method:</strong>
                        <p>{!! nl2br(e($lessonPlan->teaching_method)) !!}</p>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Homework/Classwork:</strong>
                        <p>{!! nl2br(e($lessonPlan->homework_classwork)) !!}</p>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Full Content:</strong>
                        <p>{!! nl2br(e($lessonPlan->full_content)) !!}</p>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Materials:</strong>
                        <p>{!! nl2br(e($lessonPlan->materials)) !!}</p>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Activities:</strong>
                        <p>{!! nl2br(e($lessonPlan->activities)) !!}</p>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Assessment:</strong>
                        <p>{!! nl2br(e($lessonPlan->assessment)) !!}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection