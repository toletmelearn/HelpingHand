@extends('layouts.teacher')

@section('title', 'Create Exam - Teacher Panel')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas fa-plus-circle"></i> Create New Exam</h2>
            <p class="text-muted">Create an exam for your assigned classes and subjects</p>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-file-alt"></i> Exam Details</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('teacher.exams.store') }}" method="POST">
                        @csrf
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Exam Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                   id="name" name="name" value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="class_id" class="form-label">Class <span class="text-danger">*</span></label>
                                    <select class="form-select @error('class_id') is-invalid @enderror" 
                                            id="class_id" name="class_id" required>
                                        <option value="">Select Class</option>
                                        @foreach($classes as $class)
                                            <option value="{{ $class->class_id }}" {{ old('class_id') == $class->class_id ? 'selected' : '' }}>
                                                {{ $class->class_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('class_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="subject_id" class="form-label">Subject <span class="text-danger">*</span></label>
                                    <select class="form-select @error('subject_id') is-invalid @enderror" 
                                            id="subject_id" name="subject_id" required>
                                        <option value="">Select Subject</option>
                                        @foreach($subjects as $sub)
                                            <option value="{{ $sub->subject_id }}" {{ old('subject_id') == $sub->subject_id ? 'selected' : '' }}>
                                                {{ $sub->subject_name }}
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
                                <div class="mb-3">
                                    <label for="exam_date" class="form-label">Exam Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control @error('exam_date') is-invalid @enderror" 
                                           id="exam_date" name="exam_date" value="{{ old('exam_date') }}" 
                                           min="{{ now()->addDay()->format('Y-m-d') }}" required>
                                    <div class="form-text">Exam date must be in the future</div>
                                    @error('exam_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="max_marks" class="form-label">Maximum Marks <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control @error('max_marks') is-invalid @enderror" 
                                           id="max_marks" name="max_marks" value="{{ old('max_marks') }}" 
                                           min="1" max="100" required>
                                    @error('max_marks')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="exam_type" class="form-label">Exam Type <span class="text-danger">*</span></label>
                                    <select class="form-select @error('exam_type') is-invalid @enderror" 
                                             id="exam_type" name="exam_type" required>
                                        @foreach($examTypes as $type)
                                            <option value="{{ $type }}" {{ old('exam_type') == $type ? 'selected' : '' }}>{{ $type }}</option>
                                        @endforeach
                                    </select>
                                    @error('exam_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="academic_year" class="form-label">Academic Year</label>
                                    <input type="text" class="form-control @error('academic_year') is-invalid @enderror" 
                                           id="academic_year" name="academic_year" value="{{ old('academic_year', date('Y').'-'.(date('Y')+1)) }}" 
                                           placeholder="e.g., 2025-26">
                                    @error('academic_year')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="term" class="form-label">Term</label>
                                    <select class="form-select @error('term') is-invalid @enderror" 
                                             id="term" name="term">
                                        <option value="">Select Term</option>
                                        @foreach($examTerms as $termOpt)
                                            <option value="{{ $termOpt }}" {{ old('term') == $termOpt ? 'selected' : '' }}>{{ $termOpt }}</option>
                                        @endforeach
                                    </select>
                                    @error('term')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control @error('description') is-invalid @enderror" 
                                              id="description" name="description" rows="3">{{ old('description') }}</textarea>
                                    <div class="form-text">Optional description for the exam</div>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="start_time" class="form-label">Start Time</label>
                                    <input type="time" class="form-control @error('start_time') is-invalid @enderror" 
                                           id="start_time" name="start_time" value="{{ old('start_time') }}">
                                    @error('start_time')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="end_time" class="form-label">End Time</label>
                                    <input type="time" class="form-control @error('end_time') is-invalid @enderror" 
                                           id="end_time" name="end_time" value="{{ old('end_time') }}">
                                    @error('end_time')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('teacher.exams.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Exams
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Create Exam
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> Instructions</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-check text-success"></i> Only assigned classes and subjects are available</li>
                        <li class="mb-2"><i class="fas fa-check text-success"></i> Exam date must be in the future</li>
                        <li class="mb-2"><i class="fas fa-check text-success"></i> Maximum marks between 1-100</li>
                        <li class="mb-2"><i class="fas fa-check text-success"></i> Created exams will be visible to admins and exam heads</li>
                        <li class="mb-2"><i class="fas fa-check text-success"></i> You can only edit/delete exams you created</li>
                    </ul>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header bg-warning">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Important</h5>
                </div>
                <div class="card-body">
                    <p class="mb-0">
                        <small>
                            Once created, exams will be visible to school administrators and exam coordinators. 
                            Students will be able to see upcoming exams and results will be managed through the marks upload system.
                        </small>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection