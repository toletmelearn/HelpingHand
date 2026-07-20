@extends('layouts.teacher')

@section('title', 'Create Professional Lesson Plan')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-plus-circle"></i> Create New Lesson Plan
                    </h4>
                </div>
                
                <div class="card-body">
                    <form action="{{ route('teacher.professional-lesson-plans.store') }}" method="POST">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="class_id" class="font-weight-bold">Class <span class="text-danger">*</span></label>
                                    <select name="class_id" id="class_id" class="form-control @error('class_id') is-invalid @enderror" required>
                                        <option value="">Select Class</option>
                                        @foreach($classes as $class)
                                            <option value="{{ $class->id }}" {{ old('class_id') == $class->id ? 'selected' : '' }}>
                                                {{ $class->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('class_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="subject_id" class="font-weight-bold">Subject <span class="text-danger">*</span></label>
                                    <select name="subject_id" id="subject_id" class="form-control @error('subject_id') is-invalid @enderror" required>
                                        <option value="">Select Subject</option>
                                        @foreach($subjects as $subject)
                                            <option value="{{ $subject->id }}" {{ old('subject_id') == $subject->id ? 'selected' : '' }}>
                                                {{ $subject->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('subject_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="date" class="font-weight-bold">Date <span class="text-danger">*</span></label>
                                    <input type="date" name="date" id="date" class="form-control @error('date') is-invalid @enderror" 
                                           value="{{ old('date') ?? date('Y-m-d') }}" required>
                                    @error('date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="plan_type" class="font-weight-bold">Plan Type <span class="text-danger">*</span></label>
                                    <select name="plan_type" id="plan_type" class="form-control @error('plan_type') is-invalid @enderror" required>
                                        <option value="">Select Type</option>
                                        <option value="daily" {{ old('plan_type') == 'daily' ? 'selected' : '' }}>Daily</option>
                                        <option value="weekly" {{ old('plan_type') == 'weekly' ? 'selected' : '' }}>Weekly</option>
                                        <option value="monthly" {{ old('plan_type') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                    </select>
                                    @error('plan_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="title" class="font-weight-bold">Lesson Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror" 
                                   value="{{ old('title') }}" placeholder="Enter lesson title" required>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="form-group">
                            <label for="topic" class="font-weight-bold">Topic (Optional)</label>
                            <input type="text" name="topic" id="topic" class="form-control @error('topic') is-invalid @enderror" 
                                   value="{{ old('topic') }}" placeholder="Enter lesson topic">
                            @error('topic')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="form-group">
                            <label for="duration" class="font-weight-bold">Duration (minutes) (Optional)</label>
                            <input type="number" name="duration" id="duration" class="form-control @error('duration') is-invalid @enderror" 
                                   value="{{ old('duration') }}" min="1" placeholder="Enter duration in minutes">
                            @error('duration')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <hr>
                        
                        <h5 class="mb-3"><i class="fas fa-chalkboard-teacher"></i> Full Content (For Admin/Teacher Use)</h5>
                        
                        <div class="form-group">
                            <label for="full_content" class="font-weight-bold">Full Lesson Content <span class="text-danger">*</span></label>
                            <textarea name="full_content" id="full_content" class="form-control @error('full_content') is-invalid @enderror" 
                                      rows="8" placeholder="Enter complete lesson content for administrative purposes" required>{{ old('full_content') }}</textarea>
                            @error('full_content')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="form-group">
                            <label for="learning_objectives" class="font-weight-bold">Learning Objectives (Optional)</label>
                            <textarea name="learning_objectives" id="learning_objectives" class="form-control @error('learning_objectives') is-invalid @enderror" 
                                      rows="3" placeholder="Enter learning objectives">{{ old('learning_objectives') }}</textarea>
                            @error('learning_objectives')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="form-group">
                            <label for="teaching_method" class="font-weight-bold">Teaching Method (Optional)</label>
                            <input type="text" name="teaching_method" id="teaching_method" class="form-control @error('teaching_method') is-invalid @enderror" 
                                   value="{{ old('teaching_method') }}" placeholder="Enter teaching method">
                            @error('teaching_method')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="form-group">
                            <label for="homework_classwork" class="font-weight-bold">Homework/Classwork (Optional)</label>
                            <textarea name="homework_classwork" id="homework_classwork" class="form-control @error('homework_classwork') is-invalid @enderror" 
                                      rows="3" placeholder="Enter homework or classwork details">{{ old('homework_classwork') }}</textarea>
                            @error('homework_classwork')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="form-group">
                            <label for="books_notebooks_required" class="font-weight-bold">Books/Notebooks Required (Optional)</label>
                            <textarea name="books_notebooks_required" id="books_notebooks_required" class="form-control @error('books_notebooks_required') is-invalid @enderror" 
                                      rows="2" placeholder="Enter required books or notebooks">{{ old('books_notebooks_required') }}</textarea>
                            @error('books_notebooks_required')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="form-group">
                            <label for="submission_assessment_notes" class="font-weight-bold">Submission/Assessment Notes (Optional)</label>
                            <textarea name="submission_assessment_notes" id="submission_assessment_notes" class="form-control @error('submission_assessment_notes') is-invalid @enderror" 
                                      rows="3" placeholder="Enter submission or assessment notes">{{ old('submission_assessment_notes') }}</textarea>
                            @error('submission_assessment_notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <hr>
                        
                        <h5 class="mb-3"><i class="fas fa-user-friends"></i> Parent Visible Content</h5>
                        
                        <div class="form-group">
                            <div class="form-check mb-3">
                                <input type="checkbox" name="visible_to_parent" id="visible_to_parent" class="form-check-input" 
                                       value="1" {{ old('visible_to_parent') ? 'checked' : '' }}>
                                <label for="visible_to_parent" class="form-check-label font-weight-bold">
                                    Make this lesson plan visible to parents
                                </label>
                                <small class="form-text text-muted">
                                    Check this box to allow parents to view this lesson plan
                                </small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="parent_visible_content" class="font-weight-bold">Content for Parents (Optional)</label>
                            <textarea name="parent_visible_content" id="parent_visible_content" class="form-control @error('parent_visible_content') is-invalid @enderror" 
                                      rows="6" placeholder="Enter content that will be visible to parents (e.g., what students will learn, how parents can help at home)">{{ old('parent_visible_content') }}</textarea>
                            <small class="form-text text-muted">
                                This content will be shown to parents if the visibility checkbox is checked above.
                            </small>
                            @error('parent_visible_content')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Create Lesson Plan
                            </button>
                            <a href="{{ route('teacher.professional-lesson-plans.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection