@extends('layouts.app')

@section('title', 'Edit Exam Paper')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-pencil-square"></i> Edit Exam Paper</h2>
        <a href="{{ route('exam-papers.show', $examPaper) }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to Details
        </a>
    </div>

    <div class="row">
        <div class="col-md-10 mx-auto">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Edit Exam Paper Details</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('exam-papers.update', $examPaper) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Title *</label>
                                    <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                           id="title" name="title" value="{{ old('title', $examPaper->title) }}" required>
                                    @error('title')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="subject" class="form-label">Subject *</label>
                                    <select class="form-select @error('subject') is-invalid @enderror" 
                                            id="subject" name="subject" required>
                                        <option value="">Select Subject</option>
                                        @foreach($subjects as $subject)
                                            <option value="{{ $subject }}" 
                                                {{ old('subject', $examPaper->subject) == $subject ? 'selected' : '' }}>
                                                {{ $subject }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('subject')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="class_section" class="form-label">Class/Section *</label>
                                    <select class="form-select @error('class_section') is-invalid @enderror" 
                                            id="class_section" name="class_section" required>
                                        <option value="">Select Class/Section</option>
                                        @foreach($classSections as $classSection)
                                            <option value="{{ $classSection }}" 
                                                {{ old('class_section', $examPaper->class_section) == $classSection ? 'selected' : '' }}>
                                                {{ $classSection }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('class_section')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="exam_type" class="form-label">Exam Type *</label>
                                    <select class="form-select @error('exam_type') is-invalid @enderror" 
                                            id="exam_type" name="exam_type" required>
                                        @foreach($examTypes as $type)
                                            <option value="{{ $type }}" 
                                                {{ old('exam_type', $examPaper->exam_type) == $type ? 'selected' : '' }}>
                                                {{ $type }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('exam_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="paper_type" class="form-label">Paper Type *</label>
                                    <select class="form-select @error('paper_type') is-invalid @enderror" 
                                            id="paper_type" name="paper_type" required>
                                        @foreach($paperTypes as $type)
                                            <option value="{{ $type }}" 
                                                {{ old('paper_type', $examPaper->paper_type) == $type ? 'selected' : '' }}>
                                                {{ $type }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('paper_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="academic_year" class="form-label">Academic Year</label>
                                    <select class="form-select @error('academic_year') is-invalid @enderror" 
                                            id="academic_year" name="academic_year">
                                        <option value="">Select Academic Year</option>
                                        @foreach($academicYears as $year)
                                            <option value="{{ $year }}" 
                                                {{ old('academic_year', $examPaper->academic_year) == $year ? 'selected' : '' }}>
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
                                    <label for="semester" class="form-label">Semester</label>
                                    <input type="text" class="form-control @error('semester') is-invalid @enderror" 
                                           id="semester" name="semester" value="{{ old('semester', $examPaper->semester) }}">
                                    @error('semester')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="duration_minutes" class="form-label">Duration (minutes)</label>
                                    <input type="number" class="form-control @error('duration_minutes') is-invalid @enderror" 
                                           id="duration_minutes" name="duration_minutes" 
                                           value="{{ old('duration_minutes', $examPaper->duration_minutes) }}">
                                    @error('duration_minutes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="total_marks" class="form-label">Total Marks</label>
                                    <input type="number" class="form-control @error('total_marks') is-invalid @enderror" 
                                           id="total_marks" name="total_marks" 
                                           value="{{ old('total_marks', $examPaper->total_marks) }}">
                                    @error('total_marks')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="exam_date" class="form-label">Exam Date</label>
                                    <input type="date" class="form-control @error('exam_date') is-invalid @enderror" 
                                           id="exam_date" name="exam_date" 
                                           value="{{ old('exam_date', $examPaper->exam_date) }}">
                                    @error('exam_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="exam_time" class="form-label">Exam Time</label>
                                    <input type="time" class="form-control @error('exam_time') is-invalid @enderror" 
                                           id="exam_time" name="exam_time" 
                                           value="{{ old('exam_time', $examPaper->exam_time ? $examPaper->exam_time->format('H:i') : '') }}">
                                    @error('exam_time')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="access_level" class="form-label">Access Level *</label>
                                    <select class="form-select @error('access_level') is-invalid @enderror" 
                                            id="access_level" name="access_level" required>
                                        @foreach($accessLevels as $level)
                                            <option value="{{ $level }}" 
                                                {{ old('access_level', $examPaper->access_level) == $level ? 'selected' : '' }}>
                                                {{ ucfirst(str_replace('_', ' ', $level)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('access_level')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input @error('is_published') is-invalid @enderror" 
                                           id="is_published" name="is_published" 
                                           {{ old('is_published', $examPaper->is_published) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_published">
                                        Publish Paper
                                    </label>
                                    @error('is_published')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input @error('password_protected') is-invalid @enderror" 
                                           id="password_protected" name="password_protected" 
                                           {{ old('password_protected', $examPaper->password_protected) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="password_protected">
                                        Password Protected
                                    </label>
                                    @error('password_protected')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div id="password_field" class="mb-3" style="@if(old('password_protected', $examPaper->password_protected)) display:block; @else display:none; @endif">
                                    <label for="access_password" class="form-label">Access Password</label>
                                    <input type="password" class="form-control @error('access_password') is-invalid @enderror" 
                                           id="access_password" name="access_password" 
                                           placeholder="Leave blank to keep current password">
                                    @error('access_password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">Minimum 6 characters</small>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="instructions" class="form-label">Instructions</label>
                            <textarea class="form-control @error('instructions') is-invalid @enderror" 
                                      id="instructions" name="instructions" rows="3">{{ old('instructions', $examPaper->instructions) }}</textarea>
                            @error('instructions')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="file" class="form-label">Upload New File (optional)</label>
                            <input type="file" class="form-control @error('file') is-invalid @enderror" 
                                   id="file" name="file" accept=".pdf,.doc,.docx,.txt,.jpeg,.jpg,.png">
                            <div class="form-text">
                                Current file: {{ $examPaper->file_name }} ({{ $examPaper->getFileSizeFormatted() }})
                            </div>
                            @error('file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="valid_from" class="form-label">Valid From</label>
                            <input type="datetime-local" class="form-control @error('valid_from') is-invalid @enderror" 
                                   id="valid_from" name="valid_from" 
                                   value="{{ old('valid_from', $examPaper->valid_from ? $examPaper->valid_from->format('Y-m-d\TH:i') : '') }}">
                            @error('valid_from')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="valid_until" class="form-label">Valid Until</label>
                            <input type="datetime-local" class="form-control @error('valid_until') is-invalid @enderror" 
                                   id="valid_until" name="valid_until" 
                                   value="{{ old('valid_until', $examPaper->valid_until ? $examPaper->valid_until->format('Y-m-d\TH:i') : '') }}">
                            @error('valid_until')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="{{ route('exam-papers.show', $examPaper) }}" class="btn btn-secondary me-md-2">
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Update Exam Paper
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordProtectedCheckbox = document.getElementById('password_protected');
    const passwordField = document.getElementById('password_field');
    
    passwordProtectedCheckbox.addEventListener('change', function() {
        passwordField.style.display = this.checked ? 'block' : 'none';
    });
});
</script>
@endsection