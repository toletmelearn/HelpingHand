@extends('layouts.admin')

@section('title', 'Assign Teacher - Admin Dashboard')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0"><i class="fas fa-user-plus"></i> Assign Teacher</h2>
                    <p class="text-muted mb-0">Assign teacher to class and subject</p>
                </div>
                <a href="{{ route('admin.teacher-subject-assignments.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Assignments
                </a>
            </div>
        </div>
    </div>

    <!-- Error Messages -->
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> Please fix the following errors:
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Assignment Form -->
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-edit"></i> Assignment Details</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.teacher-subject-assignments.store') }}" method="POST">
                        @csrf

                        <!-- Teacher Selection -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <label for="teacher_id" class="form-label fw-bold">
                                    <i class="fas fa-chalkboard-teacher"></i> Select Teacher <span class="text-danger">*</span>
                                </label>
                                <select name="teacher_id" id="teacher_id" class="form-select form-select-lg @error('teacher_id') is-invalid @enderror" required>
                                    <option value="">-- Choose Teacher --</option>
                                    @foreach($teachers as $teacher)
                                        <option value="{{ $teacher->id }}" {{ old('teacher_id') == $teacher->id ? 'selected' : '' }}>
                                            {{ $teacher->name }} - {{ $teacher->designation ?? 'Teacher' }} ({{ $teacher->phone ?? 'N/A' }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('teacher_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Class & Section -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="class_id" class="form-label fw-bold">
                                    <i class="fas fa-school"></i> Select Class <span class="text-danger">*</span>
                                </label>
                                <select name="class_id" id="class_id" class="form-select @error('class_id') is-invalid @enderror" required>
                                    <option value="">-- Choose Class --</option>
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
                            <div class="col-md-6">
                                <label for="section_id" class="form-label fw-bold">
                                    <i class="fas fa-door-open"></i> Select Section <small class="text-muted">(Optional)</small>
                                </label>
                                <select name="section_id" id="section_id" class="form-select @error('section_id') is-invalid @enderror">
                                    <option value="">-- All Sections --</option>
                                    @foreach($sections as $section)
                                        <option value="{{ $section->id }}" {{ old('section_id') == $section->id ? 'selected' : '' }}>
                                            {{ $section->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('section_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Subject -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <label for="subject_id" class="form-label fw-bold">
                                    <i class="fas fa-book"></i> Select Subject <span class="text-danger">*</span>
                                </label>
                                <select name="subject_id" id="subject_id" class="form-select @error('subject_id') is-invalid @enderror" required>
                                    <option value="">-- Choose Subject --</option>
                                    @foreach($subjects as $subject)
                                        <option value="{{ $subject->id }}" {{ old('subject_id') == $subject->id ? 'selected' : '' }}>
                                            {{ $subject->name }} ({{ $subject->code }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('subject_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Academic Year -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="academic_year" class="form-label fw-bold">
                                    <i class="fas fa-calendar-alt"></i> Academic Year
                                </label>
                                <input type="text" name="academic_year" id="academic_year" 
                                       class="form-control @error('academic_year') is-invalid @enderror" 
                                       value="{{ old('academic_year', date('Y') . '-' . (date('Y') + 1)) }}" 
                                       placeholder="e.g., 2025-2026">
                                @error('academic_year')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Checkboxes -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card border-info">
                                    <div class="card-body">
                                        <div class="form-check">
                                            <input type="checkbox" name="is_primary_subject_teacher" id="is_primary_subject_teacher" 
                                                   class="form-check-input" value="1" {{ old('is_primary_subject_teacher') ? 'checked' : '' }}>
                                            <label class="form-check-label fw-bold" for="is_primary_subject_teacher">
                                                <i class="fas fa-star text-warning"></i> Primary Subject Teacher
                                            </label>
                                            <p class="text-muted small mb-0">Check if this teacher is the primary teacher for this subject</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-success">
                                    <div class="card-body">
                                        <div class="form-check">
                                            <input type="checkbox" name="is_class_teacher" id="is_class_teacher" 
                                                   class="form-check-input" value="1" {{ old('is_class_teacher') ? 'checked' : '' }}>
                                            <label class="form-check-label fw-bold" for="is_class_teacher">
                                                <i class="fas fa-crown text-success"></i> Make Class Teacher
                                            </label>
                                            <p class="text-muted small mb-0">Check to assign as class teacher (replaces existing)</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="row">
                            <div class="col-12 text-center">
                                <button type="submit" class="btn btn-primary btn-lg px-5">
                                    <i class="fas fa-save"></i> Save Assignment
                                </button>
                                <a href="{{ route('admin.teacher-subject-assignments.index') }}" class="btn btn-secondary btn-lg px-5 ms-2">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
