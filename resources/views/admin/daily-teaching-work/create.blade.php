@extends('layouts.admin')

@section('title', 'Create Daily Teaching Work')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">
                    <h4>Create Daily Teaching Work</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.daily-teaching-work.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date" class="form-label">Date *</label>
                                    <input type="date" class="form-control @error('date') is-invalid @enderror" 
                                           id="date" name="date" value="{{ old('date', today()->format('Y-m-d')) }}" required>
                                    @error('date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="class_name" class="form-label">Class *</label>
                                    <select class="form-control @error('class_name') is-invalid @enderror" 
                                            id="class_name" name="class_name" required>
                                        <option value="">Select Class</option>
                                        @foreach($classes as $class)
                                            <option value="{{ $class }}" {{ old('class_name') == $class ? 'selected' : '' }}>
                                                {{ $class }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('class_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="section" class="form-label">Section *</label>
                                    <select class="form-control @error('section') is-invalid @enderror" 
                                            id="section" name="section" required>
                                        <option value="">Select Section</option>
                                        @foreach($sections as $section)
                                            <option value="{{ $section }}" {{ old('section') == $section ? 'selected' : '' }}>
                                                {{ $section }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('section')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="subject" class="form-label">Subject *</label>
                                    <select class="form-control @error('subject') is-invalid @enderror" 
                                            id="subject" name="subject" required>
                                        <option value="">Select Subject</option>
                                        @foreach($subjects as $subject)
                                            <option value="{{ $subject }}" {{ old('subject') == $subject ? 'selected' : '' }}>
                                                {{ $subject }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('subject')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="teacher_id" class="form-label">Teacher *</label>
                                    <select class="form-control @error('teacher_id') is-invalid @enderror" 
                                            id="teacher_id" name="teacher_id" required>
                                        <option value="">Select Teacher</option>
                                        @foreach($teachers as $teacher)
                                            <option value="{{ $teacher->id }}" {{ old('teacher_id') == $teacher->id ? 'selected' : '' }}>
                                                {{ $teacher->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('teacher_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="period_number" class="form-label">Period Number</label>
                                    <input type="number" class="form-control @error('period_number') is-invalid @enderror" 
                                           id="period_number" name="period_number" value="{{ old('period_number') }}" min="1" max="10">
                                    @error('period_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="topic_covered" class="form-label">Topic Covered *</label>
                                    <input type="text" class="form-control @error('topic_covered') is-invalid @enderror" 
                                           id="topic_covered" name="topic_covered" value="{{ old('topic_covered') }}" maxlength="255">
                                    @error('topic_covered')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="academic_session" class="form-label">Academic Session</label>
                                    <input type="text" class="form-control @error('academic_session') is-invalid @enderror" 
                                           id="academic_session" name="academic_session" value="{{ old('academic_session', config('app.academic_session', date('Y').'-'.(date('Y')+1))) }}" placeholder="e.g. 2024-2025">
                                    @error('academic_session')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="teaching_summary" class="form-label">Teaching Summary</label>
                            <textarea class="form-control @error('teaching_summary') is-invalid @enderror" 
                                      id="teaching_summary" name="teaching_summary" rows="4">{{ old('teaching_summary') }}</textarea>
                            @error('teaching_summary')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label for="attachments" class="form-label">Attachments</label>
                            <input type="file" class="form-control @error('attachments') is-invalid @enderror" 
                                   id="attachments" name="attachments[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.mp4,.mov,.avi">
                            <div class="form-text">Select multiple files (PDF, DOC, DOCX, JPG, PNG, MP4)</div>
                            @error('attachments')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label for="homework_description" class="form-label">Homework Description</label>
                            <textarea class="form-control @error('homework_description') is-invalid @enderror" 
                                      id="homework_description" name="homework_description" rows="2">{{ old('homework_description') }}</textarea>
                            @error('homework_description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="homework_due_date" class="form-label">Homework Due Date</label>
                                <input type="date" class="form-control @error('homework_due_date') is-invalid @enderror" 
                                       id="homework_due_date" name="homework_due_date" value="{{ old('homework_due_date') }}">
                                @error('homework_due_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="syllabus_mapping" class="form-label">Syllabus Mapping</label>
                            <select class="form-control @error('syllabus_mapping') is-invalid @enderror" 
                                    id="syllabus_mapping" name="syllabus_mapping[]">
                                <option value="">Map to Syllabus Unit (Optional)</option>
                                <!-- Options would be populated dynamically -->
                            </select>
                            @error('syllabus_mapping')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="{{ route('admin.daily-teaching-work.index') }}" class="btn btn-secondary me-md-2">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save Daily Teaching Work</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
