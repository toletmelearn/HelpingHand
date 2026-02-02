@extends('layouts.admin')

@section('title', 'Upload Exam Paper')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1>Upload Exam Paper</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('exam-papers.index') }}">Exam Papers</a></li>
                    <li class="breadcrumb-item active">Upload</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Upload New Exam Paper</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('exam-papers.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Title *</label>
                                    <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                           id="title" name="title" value="{{ old('title') }}" required>
                                    @error('title')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="subject" class="form-label">Subject *</label>
                                    <select class="form-select @error('subject') is-invalid @enderror" 
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
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="class_section" class="form-label">Class/Section *</label>
                                    <select class="form-select @error('class_section') is-invalid @enderror" 
                                            id="class_section" name="class_section" required>
                                        <option value="">Select Class/Section</option>
                                        @foreach($classSections as $class)
                                            <option value="{{ $class }}" {{ old('class_section') == $class ? 'selected' : '' }}>
                                                {{ $class }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('class_section')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="exam_type" class="form-label">Exam Type *</label>
                                    <select class="form-select @error('exam_type') is-invalid @enderror" 
                                            id="exam_type" name="exam_type" required>
                                        <option value="">Select Exam Type</option>
                                        @foreach($examTypes as $type)
                                            <option value="{{ $type }}" {{ old('exam_type') == $type ? 'selected' : '' }}>
                                                {{ ucfirst(str_replace('_', ' ', $type)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('exam_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="paper_type" class="form-label">Paper Type *</label>
                                    <select class="form-select @error('paper_type') is-invalid @enderror" 
                                            id="paper_type" name="paper_type" required>
                                        <option value="">Select Paper Type</option>
                                        @foreach($paperTypes as $type)
                                            <option value="{{ $type }}" {{ old('paper_type') == $type ? 'selected' : '' }}>
                                                {{ ucfirst(str_replace('_', ' ', $type)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('paper_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="access_level" class="form-label">Access Level *</label>
                                    <select class="form-select @error('access_level') is-invalid @enderror" 
                                            id="access_level" name="access_level" required>
                                        <option value="">Select Access Level</option>
                                        @foreach($accessLevels as $level)
                                            <option value="{{ $level }}" {{ old('access_level') == $level ? 'selected' : '' }}>
                                                {{ ucfirst(str_replace('_', ' ', $level)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('access_level')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="academic_year" class="form-label">Academic Year</label>
                                    <select class="form-select @error('academic_year') is-invalid @enderror" 
                                            id="academic_year" name="academic_year">
                                        <option value="">Select Academic Year</option>
                                        @foreach($academicYears as $year)
                                            <option value="{{ $year }}" {{ old('academic_year') == $year ? 'selected' : '' }}>
                                                {{ $year }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('academic_year')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="file" class="form-label">Upload File *</label>
                                    <input type="file" class="form-control @error('file') is-invalid @enderror" 
                                           id="file" name="file" accept=".pdf,.doc,.docx,.txt,.jpeg,.jpg,.png" required>
                                    <div class="form-text">Supported formats: PDF, DOC, DOCX, TXT, JPEG, JPG, PNG (Max 10MB)</div>
                                    @error('file')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="total_marks" class="form-label">Total Marks</label>
                                    <input type="number" class="form-control @error('total_marks') is-invalid @enderror" 
                                           id="total_marks" name="total_marks" value="{{ old('total_marks') }}" min="1">
                                    @error('total_marks')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="duration_minutes" class="form-label">Duration (minutes)</label>
                                    <input type="number" class="form-control @error('duration_minutes') is-invalid @enderror" 
                                           id="duration_minutes" name="duration_minutes" value="{{ old('duration_minutes') }}" min="1">
                                    @error('duration_minutes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="exam_date" class="form-label">Exam Date</label>
                                    <input type="date" class="form-control @error('exam_date') is-invalid @enderror" 
                                           id="exam_date" name="exam_date" value="{{ old('exam_date') }}">
                                    @error('exam_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="exam_time" class="form-label">Exam Time</label>
                                    <input type="time" class="form-control @error('exam_time') is-invalid @enderror" 
                                           id="exam_time" name="exam_time" value="{{ old('exam_time') }}">
                                    @error('exam_time')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="instructions" class="form-label">Instructions</label>
                            <textarea class="form-control @error('instructions') is-invalid @enderror" 
                                      id="instructions" name="instructions" rows="4">{{ old('instructions') }}</textarea>
                            @error('instructions')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_published" name="is_published" 
                                   value="1" {{ old('is_published') ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_published">
                                Publish immediately
                            </label>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_answer_key" name="is_answer_key" 
                                   value="1" {{ old('is_answer_key') ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_answer_key">
                                This is an answer key/solution
                            </label>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('exam-papers.index') }}" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Upload Paper</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
