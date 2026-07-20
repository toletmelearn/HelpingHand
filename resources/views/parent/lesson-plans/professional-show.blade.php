@extends('layouts.parent')

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
                    <a href="{{ route('parent.professional-lesson-plans.index') }}" class="btn btn-light">
                        <i class="fas fa-arrow-left"></i> Back to Plans
                    </a>
                </div>
                
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="info-group mb-3">
                                <label class="font-weight-bold text-primary">
                                    <i class="fas fa-calendar"></i> Date:
                                </label>
                                <p>{{ $lessonPlan->date->format('F d, Y') }}</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-group mb-3">
                                <label class="font-weight-bold text-primary">
                                    <i class="fas fa-clock"></i> Duration:
                                </label>
                                <p>{{ $lessonPlan->duration ?? 'N/A' }} minutes</p>
                            </div>
                        </div>
                        <div class="col-md-4">
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
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-group mb-3">
                                <label class="font-weight-bold text-primary">
                                    <i class="fas fa-chalkboard-teacher"></i> Teacher:
                                </label>
                                <p>{{ $lessonPlan->teacher->name ?? 'Not Assigned' }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-group mb-3">
                                <label class="font-weight-bold text-primary">
                                    <i class="fas fa-book"></i> Subject:
                                </label>
                                <p>{{ $lessonPlan->subject->name ?? 'N/A' }}</p>
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
                    <div class="mt-4">
                        <h5 class="border-bottom pb-2 text-success">
                            <i class="fas fa-graduation-cap"></i> What Your Child Will Learn
                        </h5>
                        <div class="p-3 bg-light rounded">
                            {!! nl2br(e($lessonPlan->learning_objectives ?? 'No learning objectives specified')) !!}
                        </div>
                    </div>
                    @endif
                    
                    @if($lessonPlan->materials)
                    <div class="mt-4">
                        <h5 class="border-bottom pb-2 text-warning">
                            <i class="fas fa-cubes"></i> Materials Needed
                        </h5>
                        <div class="p-3 bg-light rounded">
                            {!! nl2br(e($lessonPlan->materials ?? 'No special materials required')) !!}
                        </div>
                    </div>
                    @endif
                    
                    @if($lessonPlan->homework_classwork)
                    <div class="mt-4">
                        <h5 class="border-bottom pb-2 text-warning">
                            <i class="fas fa-pencil-alt"></i> Homework/Classwork
                        </h5>
                        <div class="p-3 bg-light rounded">
                            {!! nl2br(e($lessonPlan->homework_classwork ?? 'No homework assigned')) !!}
                        </div>
                    </div>
                    @endif
                    
                    @if($lessonPlan->activities)
                    <div class="mt-4">
                        <h5 class="border-bottom pb-2 text-warning">
                            <i class="fas fa-hands-helping"></i> How You Can Support at Home
                        </h5>
                        <div class="p-3 bg-light rounded">
                            {!! nl2br(e($lessonPlan->activities ?? 'No specific activities provided')) !!}
                        </div>
                    </div>
                    @endif
                    
                    @if($lessonPlan->assessment)
                    <div class="mt-4">
                        <h5 class="border-bottom pb-2 text-warning">
                            <i class="fas fa-chart-bar"></i> Assessment
                        </h5>
                        <div class="p-3 bg-light rounded">
                            {!! nl2br(e($lessonPlan->assessment)) !!}
                        </div>
                    </div>
                    @endif
                    
                    <div class="mt-4 text-center">
                        <a href="{{ route('parent.professional-lesson-plans.index') }}" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Back to All Lesson Plans
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection