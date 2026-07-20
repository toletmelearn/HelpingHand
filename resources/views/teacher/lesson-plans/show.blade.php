@extends('layouts.teacher')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Lesson Plan Details</h1>
                <div>
                    <a href="{{ route('teacher.lesson-plans.index') }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Lesson Plans
                    </a>
                    <a href="{{ route('teacher.lesson-plans.edit', $lessonPlan) }}" class="btn btn-primary">
                        <i class="bi bi-pencil"></i> Edit
                    </a>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ $lessonPlan->title }}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td width="30%"><strong>Class:</strong></td>
                                    <td>{{ $lessonPlan->class?->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Section:</strong></td>
                                    <td>{{ $lessonPlan->section?->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Subject:</strong></td>
                                    <td>{{ $lessonPlan->subject?->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Type:</strong></td>
                                    <td>
                                        <span class="badge bg-{{ $lessonPlan->plan_type === 'daily' ? 'primary' : ($lessonPlan->plan_type === 'weekly' ? 'success' : ($lessonPlan->plan_type === '15days' ? 'warning' : 'info')) }}">
                                            {{ ucfirst($lessonPlan->plan_type) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Date:</strong></td>
                                    <td>{{ $lessonPlan->date?->format('M d, Y') ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Start Date:</strong></td>
                                    <td>{{ $lessonPlan->start_date?->format('M d, Y') ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>End Date:</strong></td>
                                    <td>{{ $lessonPlan->end_date?->format('M d, Y') ?? 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td width="30%"><strong>Topic:</strong></td>
                                    <td>{{ $lessonPlan->topic ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Learning Objectives:</strong></td>
                                    <td>{{ $lessonPlan->learning_objectives ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Teaching Method:</strong></td>
                                    <td>{{ $lessonPlan->teaching_method ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Homework/Classwork:</strong></td>
                                    <td>{{ $lessonPlan->homework_classwork ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Books Required:</strong></td>
                                    <td>{{ $lessonPlan->books_notebooks_required ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Created By:</strong></td>
                                    <td>{{ $lessonPlan->createdBy?->name ?? ($lessonPlan->teacher?->name ? $lessonPlan->teacher->name : 'Teacher') }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-12">
                            <h5>Full Lesson Plan Content</h5>
                            <div class="border p-3 bg-light">
                                {!! nl2br(e($lessonPlan->full_content)) !!}
                            </div>
                        </div>
                    </div>
                    
                    @if($lessonPlan->parent_visible_content || $lessonPlan->show_to_parents)
                    <div class="row mt-4">
                        <div class="col-12">
                            <h5>Parent Visible Content</h5>
                            <div class="border p-3 bg-light">
                                {!! nl2br(e($lessonPlan->parent_visible_content)) !!}
                            </div>
                            <div class="mt-2">
                                <strong>Show to parents:</strong> 
                                <span class="badge bg-{{ $lessonPlan->show_to_parents ? 'success' : 'secondary' }}">
                                    {{ $lessonPlan->show_to_parents ? 'Yes' : 'No' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    @endif
                    
                    @if($lessonPlan->submission_assessment_notes)
                    <div class="row mt-4">
                        <div class="col-12">
                            <h5>Submission & Assessment Notes</h5>
                            <div class="border p-3 bg-light">
                                {!! nl2br(e($lessonPlan->submission_assessment_notes)) !!}
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection