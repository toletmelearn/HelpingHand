@extends('layouts.admin')

@section('title', 'Assign Teacher to Subject')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Assign Teacher to Subject</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.teacher-subject-assignments.store') }}" method="POST">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="teacher_id">Teacher <span class="text-danger">*</span></label>
                                    <select name="teacher_id" id="teacher_id" class="form-control @error('teacher_id') is-invalid @enderror" required>
                                        <option value="">Select Teacher</option>
                                        @foreach($teachers as $teacher)
                                            <option value="{{ $teacher->id }}" {{ old('teacher_id') == $teacher->id ? 'selected' : '' }}>
                                                {{ $teacher->name }} ({{ $teacher->designation }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('teacher_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="class_ids">Classes <span class="text-danger">*</span></label>
                                    <select name="class_ids[]" id="class_ids" class="form-control select2 @error('class_ids') is-invalid @enderror" multiple required>
                                        @foreach($classes as $class)
                                            <option value="{{ $class->id }}" {{ in_array($class->id, old('class_ids', [])) ? 'selected' : '' }}>
                                                {{ $class->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('class_ids')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">Hold Ctrl/Cmd to select multiple classes</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="subject_ids">Subjects <span class="text-danger">*</span></label>
                            <select name="subject_ids[]" id="subject_ids" class="form-control select2 @error('subject_ids') is-invalid @enderror" multiple required>
                                @foreach($subjects as $subject)
                                    <option value="{{ $subject->id }}" {{ in_array($subject->id, old('subject_ids', [])) ? 'selected' : '' }}>
                                        {{ $subject->name }} ({{ $subject->code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('subject_ids')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">Hold Ctrl/Cmd to select multiple subjects</small>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Assign Teacher to Subjects</button>
                            <a href="{{ route('admin.teacher-subject-assignments.index') }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Select2 CSS and JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    $('.select2').select2({
        placeholder: "Select options",
        allowClear: true
    });
});
</script>
@endsection
