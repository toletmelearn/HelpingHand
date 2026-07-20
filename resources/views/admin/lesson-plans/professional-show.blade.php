@extends('layouts.admin')

@section('title', 'Lesson Plan Details')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-book-open"></i> Lesson Plan Details
                    </h4>
                    <div>
                        <a href="{{ route('admin.professional-lesson-plans.index') }}" class="btn btn-light">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-group mb-3">
                                <label class="font-weight-bold text-primary">
                                    <i class="fas fa-calendar"></i> Date:
                                </label>
                                <p>{{ $lessonPlan->date->format('F d, Y') }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-group mb-3">
                                <label class="font-weight-bold text-primary">
                                    <i class="fas fa-clock"></i> Duration:
                                </label>
                                <p>{{ $lessonPlan->duration ?? 'N/A' }} minutes</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="info-group mb-3">
                                <label class="font-weight-bold text-primary">
                                    <i class="fas fa-chalkboard-teacher"></i> Teacher:
                                </label>
                                <p>{{ $lessonPlan->teacher->name ?? 'Not Assigned' }}</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-group mb-3">
                                <label class="font-weight-bold text-primary">
                                    <i class="fas fa-school"></i> Class:
                                </label>
                                <p>{{ $lessonPlan->class->name ?? 'N/A' }}</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-group mb-3">
                                <label class="font-weight-bold text-primary">
                                    <i class="fas fa-book"></i> Subject:
                                </label>
                                <p>{{ $lessonPlan->subject->name ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-group mb-3">
                                <label class="font-weight-bold text-primary">
                                    <i class="fas fa-tag"></i> Type:
                                </label>
                                <p>
                                    <span class="badge badge-{{ $lessonPlan->plan_type == 'daily' ? 'success' : ($lessonPlan->plan_type == 'weekly' ? 'warning' : 'primary') }}">
                                        {{ ucfirst($lessonPlan->plan_type) }}
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-group mb-3">
                                <label class="font-weight-bold text-primary">
                                    <i class="fas fa-user-friends"></i> Parent Visibility:
                                </label>
                                <p>
                                    @if($lessonPlan->show_to_parents)
                                        <span class="badge badge-success">
                                            <i class="fas fa-check"></i> Visible to Parents
                                        </span>
                                    @else
                                        <span class="badge badge-secondary">
                                            <i class="fas fa-times"></i> Not Visible to Parents
                                        </span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h5 class="border-bottom pb-2 text-success">
                            <i class="fas fa-heading"></i> Lesson Title
                        </h5>
                        <div class="p-3 bg-light rounded">
                            {{ $lessonPlan->title }}
                        </div>
                    </div>
                    
                    @if($lessonPlan->topic)
                    <div class="mt-4">
                        <h5 class="border-bottom pb-2 text-success">
                            <i class="fas fa-info-circle"></i> Topic
                        </h5>
                        <div class="p-3 bg-light rounded">
                            {{ $lessonPlan->topic }}
                        </div>
                    </div>
                    @endif
                    
                    <div class="mt-4">
                        <h5 class="border-bottom pb-2 text-primary">
                            <i class="fas fa-file-alt"></i> Full Content (Admin/Teacher View)
                        </h5>
                        <div class="p-3 bg-info text-white rounded">
                            {!! nl2br(e($lessonPlan->full_content ?? 'No content provided')) !!}
                        </div>
                    </div>
                    
                    @if($lessonPlan->learning_objectives)
                    <div class="mt-4">
                        <h5 class="border-bottom pb-2 text-warning">
                            <i class="fas fa-bullseye"></i> Learning Objectives
                        </h5>
                        <div class="p-3 bg-light rounded">
                            {!! nl2br(e($lessonPlan->learning_objectives)) !!}
                        </div>
                    </div>
                    @endif
                    
                    @if($lessonPlan->teaching_method)
                    <div class="mt-4">
                        <h5 class="border-bottom pb-2 text-warning">
                            <i class="fas fa-chalkboard"></i> Teaching Method
                        </h5>
                        <div class="p-3 bg-light rounded">
                            {{ $lessonPlan->teaching_method }}
                        </div>
                    </div>
                    @endif
                    
                    @if($lessonPlan->homework_classwork)
                    <div class="mt-4">
                        <h5 class="border-bottom pb-2 text-warning">
                            <i class="fas fa-pencil-alt"></i> Homework/Classwork
                        </h5>
                        <div class="p-3 bg-light rounded">
                            {!! nl2br(e($lessonPlan->homework_classwork)) !!}
                        </div>
                    </div>
                    @endif
                    
                    @if($lessonPlan->books_notebooks_required)
                    <div class="mt-4">
                        <h5 class="border-bottom pb-2 text-warning">
                            <i class="fas fa-book"></i> Required Materials
                        </h5>
                        <div class="p-3 bg-light rounded">
                            {!! nl2br(e($lessonPlan->books_notebooks_required)) !!}
                        </div>
                    </div>
                    @endif
                    
                    @if($lessonPlan->submission_assessment_notes)
                    <div class="mt-4">
                        <h5 class="border-bottom pb-2 text-warning">
                            <i class="fas fa-clipboard-check"></i> Assessment Notes
                        </h5>
                        <div class="p-3 bg-light rounded">
                            {!! nl2br(e($lessonPlan->submission_assessment_notes)) !!}
                        </div>
                    </div>
                    @endif
                    
                    @if($lessonPlan->parent_visible_content)
                    <div class="mt-4">
                        <h5 class="border-bottom pb-2 text-success">
                            <i class="fas fa-user-friends"></i> Parent Visible Content
                        </h5>
                        <div class="p-4 bg-success text-white rounded">
                            <strong>Content for Parents:</strong><br>
                            {!! nl2br(e($lessonPlan->parent_visible_content)) !!}
                        </div>
                    </div>
                    @endif
                    
                    <div class="mt-4">
                        <h5 class="border-bottom pb-2 text-info">
                            <i class="fas fa-info-circle"></i> System Information
                        </h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-group">
                                    <label class="font-weight-bold">Created By:</label>
                                    <p>{{ $lessonPlan->createdBy->name ?? 'N/A' }}</p>
                                </div>
                                <div class="info-group">
                                    <label class="font-weight-bold">Created At:</label>
                                    <p>{{ $lessonPlan->created_at->format('F d, Y h:i A') }}</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-group">
                                    <label class="font-weight-bold">Last Modified By:</label>
                                    <p>{{ $lessonPlan->modifiedBy->name ?? 'N/A' }}</p>
                                </div>
                                <div class="info-group">
                                    <label class="font-weight-bold">Last Modified:</label>
                                    <p>{{ $lessonPlan->updated_at->format('F d, Y h:i A') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 d-flex justify-content-between">
                        <a href="{{ route('admin.professional-lesson-plans.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                        
                        <form action="{{ route('admin.professional-lesson-plans.destroy', $lessonPlan) }}" 
                              method="POST" 
                              onsubmit="return confirm('Are you sure you want to delete this lesson plan? This action cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Delete Lesson Plan
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection