@extends('layouts.teacher')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1>Create Lesson Plan</h1>
            <p class="mb-4">Create a new lesson plan</p>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Lesson Plan Details</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('teacher.lesson-plans.store') }}" method="POST">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="class_id" class="form-label">Class *</label>
                                <select name="class_id" id="class_id" class="form-select" required>
                                    <option value="">Select Class</option>
                                    @foreach($classes as $id => $name)
                                        <option value="{{ $id }}" {{ old('class_id') == $id ? 'selected' : '' }}>
                                            {{ $name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('class_id')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="subject_id" class="form-label">Subject *</label>
                                <select name="subject_id" id="subject_id" class="form-select" required>
                                    <option value="">Select Subject</option>
                                    @foreach($subjects as $id => $name)
                                        <option value="{{ $id }}" {{ old('subject_id') == $id ? 'selected' : '' }}>
                                            {{ $name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('subject_id')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="title" class="form-label">Lesson Plan Title *</label>
                                <input type="text" name="title" id="title" class="form-control" 
                                       value="{{ old('title') }}" required>
                                @error('title')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="plan_type" class="form-label">Plan Type *</label>
                                <select name="plan_type" id="plan_type" class="form-select" required>
                                    <option value="">Select Type</option>
                                    <option value="daily" {{ old('plan_type') == 'daily' ? 'selected' : '' }}>Daily</option>
                                    <option value="weekly" {{ old('plan_type') == 'weekly' ? 'selected' : '' }}>Weekly</option>
                                    <option value="15days" {{ old('plan_type') == '15days' ? 'selected' : '' }}>15 Days</option>
                                    <option value="monthly" {{ old('plan_type') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                </select>
                                @error('plan_type')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="start_date" class="form-label">Start Date *</label>
                                <input type="date" name="start_date" id="start_date" class="form-control" 
                                       value="{{ old('start_date') }}" required>
                                @error('start_date')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="end_date" class="form-label">End Date *</label>
                                <input type="date" name="end_date" id="end_date" class="form-control" 
                                       value="{{ old('end_date') }}" required>
                                @error('end_date')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="full_content" class="form-label">Full Lesson Plan (for Admin) *</label>
                                <textarea name="full_content" id="full_content" class="form-control" 
                                          rows="6" required placeholder="Complete syllabus planning, activities, homework, tests...">{{ old('full_content') }}</textarea>
                                @error('full_content')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="parent_visible_content" class="form-label">What Parents Can See</label>
                                <textarea name="parent_visible_content" id="parent_visible_content" class="form-control" 
                                          rows="4" placeholder="Only topics to bring books, homework instructions...">{{ old('parent_visible_content') }}</textarea>
                                @error('parent_visible_content')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="col-12 mb-3">
                                <div class="form-check">
                                    <input type="checkbox" name="show_to_parents" id="show_to_parents" class="form-check-input" 
                                           value="1" {{ old('show_to_parents') ? 'checked' : '' }}>
                                    <label for="show_to_parents" class="form-check-label">
                                        Show to parents?
                                    </label>
                                </div>
                                @error('show_to_parents')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                                                    
                        <div class="col-md-6 mb-3">
                            <label for="topic" class="form-label">Topic</label>
                            <input type="text" name="topic" id="topic" class="form-control" 
                                   value="{{ old('topic') }}">
                            @error('topic')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                                                    
                        <div class="col-md-6 mb-3">
                            <label for="learning_objectives" class="form-label">Learning Objectives</label>
                            <input type="text" name="learning_objectives" id="learning_objectives" class="form-control" 
                                   value="{{ old('learning_objectives') }}">
                            @error('learning_objectives')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                                                    
                        <div class="col-md-6 mb-3">
                            <label for="teaching_method" class="form-label">Teaching Method</label>
                            <input type="text" name="teaching_method" id="teaching_method" class="form-control" 
                                   value="{{ old('teaching_method') }}">
                            @error('teaching_method')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                                                    
                        <div class="col-md-6 mb-3">
                            <label for="homework_classwork" class="form-label">Homework/Classwork</label>
                            <input type="text" name="homework_classwork" id="homework_classwork" class="form-control" 
                                   value="{{ old('homework_classwork') }}">
                            @error('homework_classwork')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                                                    
                        <div class="col-md-6 mb-3">
                            <label for="books_notebooks_required" class="form-label">Books & Notebooks Required</label>
                            <input type="text" name="books_notebooks_required" id="books_notebooks_required" class="form-control" 
                                   value="{{ old('books_notebooks_required') }}">
                            @error('books_notebooks_required')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                                                    
                        <div class="col-12 mb-3">
                            <label for="submission_assessment_notes" class="form-label">Submission & Assessment Notes</label>
                            <textarea name="submission_assessment_notes" id="submission_assessment_notes" class="form-control" 
                                      rows="3">{{ old('submission_assessment_notes') }}</textarea>
                            @error('submission_assessment_notes')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                                                    
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('teacher.lesson-plans.index') }}" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Create Lesson Plan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
