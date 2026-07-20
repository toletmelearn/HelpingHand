@extends('layouts.parent')

@section('title', 'Lesson Plan Details')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title">
                        <i class="fas fa-book-open mr-2"></i>
                        Lesson Plan: {{ $lessonPlan->title }}
                    </h4>
                    <a href="{{ route('parent.lesson-plans.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Plans
                    </a>
                </div>
                
                <div class="card-body">
                    <!-- Basic Information Section (Always Visible to Parents) -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-group">
                                <label class="font-weight-bold text-primary">
                                    <i class="fas fa-chalkboard-teacher"></i> Teacher:
                                </label>
                                <p class="mb-3">{{ $lessonPlan->teacher->name ?? 'Not Assigned' }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-group">
                                <label class="font-weight-bold text-primary">
                                    <i class="fas fa-book"></i> Subject:
                                </label>
                                <p class="mb-3">{{ $lessonPlan->subject->name ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Core Lesson Information -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="info-group">
                                <label class="font-weight-bold text-primary">
                                    <i class="fas fa-calendar"></i> Date:
                                </label>
                                <p>{{ $lessonPlan->date->format('F d, Y') }}</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-group">
                                <label class="font-weight-bold text-primary">
                                    <i class="fas fa-clock"></i> Duration:
                                </label>
                                <p>{{ $lessonPlan->duration }}</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-group">
                                <label class="font-weight-bold text-primary">
                                    <i class="fas fa-tag"></i> Type:
                                </label>
                                <p>{{ ucfirst($lessonPlan->plan_type) }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Class Information -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="info-group">
                                <label class="font-weight-bold text-primary">
                                    <i class="fas fa-school"></i> Class:
                                </label>
                                <p>{{ $lessonPlan->class->name ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Parent-Visible Content Sections -->
                    <div class="mt-4">
                        <h5 class="border-bottom pb-2 text-success">
                            <i class="fas fa-graduation-cap"></i> What Your Child Will Learn
                        </h5>
                        <div class="p-3 bg-light rounded">
                            {!! nl2br(e($lessonPlan->learning_objectives ?? 'No learning objectives specified')) !!}
                        </div>
                    </div>

                    <div class="mt-4">
                        <h5 class="border-bottom pb-2 text-success">
                            <i class="fas fa-pencil-alt"></i> Homework/Classwork
                        </h5>
                        <div class="p-3 bg-light rounded">
                            {!! nl2br(e($lessonPlan->homework_classwork ?? 'No homework assigned')) !!}
                        </div>
                    </div>

                    <div class="mt-4">
                        <h5 class="border-bottom pb-2 text-success">
                            <i class="fas fa-cubes"></i> Materials Needed
                        </h5>
                        <div class="p-3 bg-light rounded">
                            {!! nl2br(e($lessonPlan->materials ?? 'No special materials required')) !!}
                        </div>
                    </div>

                    <div class="mt-4">
                        <h5 class="border-bottom pb-2 text-success">
                            <i class="fas fa-hands-helping"></i> How You Can Support at Home
                        </h5>
                        <div class="p-3 bg-light rounded">
                            {!! nl2br(e($lessonPlan->activities ?? 'No specific activities provided')) !!}
                        </div>
                    </div>

                    <!-- Main Parent Content - Highlight this as the main section -->
                    @if($lessonPlan->parent_visible_content)
                    <div class="mt-4">
                        <h5 class="border-bottom pb-2 text-info">
                            <i class="fas fa-star"></i> Information for Parents
                        </h5>
                        <div class="p-4 bg-info text-white rounded">
                            <strong>Main Content:</strong><br>
                            {!! nl2br(e($lessonPlan->parent_visible_content)) !!}
                        </div>
                    </div>
                    @else
                    <!-- Fallback content if parent_visible_content is not available -->
                    <div class="mt-4">
                        <h5 class="border-bottom pb-2 text-success">
                            <i class="fas fa-graduation-cap"></i> What Your Child Will Learn
                        </h5>
                        <div class="p-3 bg-light rounded">
                            {!! nl2br(e($lessonPlan->learning_objectives ?? 'No learning objectives specified')) !!}
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection