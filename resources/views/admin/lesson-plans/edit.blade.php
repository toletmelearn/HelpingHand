@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1>Edit Lesson Plan</h1>
            <p class="mb-4">Update the lesson plan details</p>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Edit Lesson Plan</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.lesson-plans.update', $lessonPlan) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="teacher_id" class="form-label">Teacher *</label>
                                <select name="teacher_id" id="teacher_id" class="form-select" required>
                                    <option value="">Select Teacher</option>
                                    @foreach($teachers as $teacher)
                                        <option value="{{ $teacher->id }}" {{ old('teacher_id', $lessonPlan->teacher_id) == $teacher->id ? 'selected' : '' }}>
                                            {{ $teacher->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('teacher_id')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="class_id" class="form-label">Class *</label>
                                <select name="class_id" id="class_id" class="form-select" required>
                                    <option value="">Select Class</option>
                                    @foreach($classes as $class)
                                        <option value="{{ $class->id }}" {{ old('class_id', $lessonPlan->class_id) == $class->id ? 'selected' : '' }}>
                                            {{ $class->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('class_id')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="section_id" class="form-label">Section *</label>
                                <select name="section_id" id="section_id" class="form-select" required>
                                    <option value="">Select Section</option>
                                    @foreach($sections as $section)
                                        <option value="{{ $section->id }}" {{ old('section_id', $lessonPlan->section_id) == $section->id ? 'selected' : '' }}>
                                            {{ $section->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('section_id')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="subject_id" class="form-label">Subject *</label>
                                <select name="subject_id" id="subject_id" class="form-select" required>
                                    <option value="">Select Subject</option>
                                    @foreach($subjects as $subject)
                                        <option value="{{ $subject->id }}" {{ old('subject_id', $lessonPlan->subject_id) == $subject->id ? 'selected' : '' }}>
                                            {{ $subject->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('subject_id')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="date" class="form-label">Date *</label>
                                <input type="date" name="date" id="date" class="form-control" 
                                       value="{{ old('date', $lessonPlan->date->format('Y-m-d')) }}" required>
                                @error('date')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="plan_type" class="form-label">Plan Type *</label>
                                <select name="plan_type" id="plan_type" class="form-select" required>
                                    <option value="">Select Type</option>
                                    <option value="daily" {{ old('plan_type', $lessonPlan->plan_type) == 'daily' ? 'selected' : '' }}>Daily</option>
                                    <option value="weekly" {{ old('plan_type', $lessonPlan->plan_type) == 'weekly' ? 'selected' : '' }}>Weekly</option>
                                    <option value="monthly" {{ old('plan_type', $lessonPlan->plan_type) == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                </select>
                                @error('plan_type')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="topic" class="form-label">Topic / Chapter Name *</label>
                                <input type="text" name="topic" id="topic" class="form-control" 
                                       value="{{ old('topic', $lessonPlan->topic) }}" required>
                                @error('topic')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="learning_objectives" class="form-label">Learning Objectives *</label>
                                <textarea name="learning_objectives" id="learning_objectives" class="form-control" 
                                          rows="3" required>{{ old('learning_objectives', $lessonPlan->learning_objectives) }}</textarea>
                                @error('learning_objectives')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="teaching_method" class="form-label">Teaching Method (Optional)</label>
                                <textarea name="teaching_method" id="teaching_method" class="form-control" 
                                          rows="2">{{ old('teaching_method', $lessonPlan->teaching_method) }}</textarea>
                                @error('teaching_method')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="homework_classwork" class="form-label">Homework / Classwork *</label>
                                <textarea name="homework_classwork" id="homework_classwork" class="form-control" 
                                          rows="3" required>{{ old('homework_classwork', $lessonPlan->homework_classwork) }}</textarea>
                                @error('homework_classwork')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="books_notebooks_required" class="form-label">Books / Notebooks Required *</label>
                                <textarea name="books_notebooks_required" id="books_notebooks_required" class="form-control" 
                                          rows="3" required>{{ old('books_notebooks_required', $lessonPlan->books_notebooks_required) }}</textarea>
                                @error('books_notebooks_required')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="submission_assessment_notes" class="form-label">Submission / Assessment Notes</label>
                                <textarea name="submission_assessment_notes" id="submission_assessment_notes" class="form-control" 
                                          rows="2">{{ old('submission_assessment_notes', $lessonPlan->submission_assessment_notes) }}</textarea>
                                @error('submission_assessment_notes')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.lesson-plans.index') }}" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Update Lesson Plan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
