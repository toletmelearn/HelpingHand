@extends('layouts.admin')

@section('title', 'Edit Teacher-Class Assignment')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Edit Teacher-Class Assignment</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.teacher-class-assignments.update', $assignment->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="teacher_id">Teacher <span class="text-danger">*</span></label>
                                    <select name="teacher_id" id="teacher_id" class="form-control @error('teacher_id') is-invalid @enderror" required>
                                        <option value="">Select Teacher</option>
                                        @foreach($teachers as $teacher)
                                            <option value="{{ $teacher->id }}" {{ old('teacher_id', $assignment->teacher_id) == $teacher->id ? 'selected' : '' }}>
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
                                    <label for="class_id">Class <span class="text-danger">*</span></label>
                                    <select name="class_id" id="class_id" class="form-control @error('class_id') is-invalid @enderror" required>
                                        <option value="">Select Class</option>
                                        @foreach($classes as $class)
                                            <option value="{{ $class->id }}" {{ old('class_id', $assignment->class_id) == $class->id ? 'selected' : '' }}>
                                                {{ $class->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('class_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="role">Role <span class="text-danger">*</span></label>
                                    <select name="role" id="role" class="form-control @error('role') is-invalid @enderror" required>
                                        <option value="">Select Role</option>
                                        <option value="class_teacher" {{ old('role', $assignment->role) == 'class_teacher' ? 'selected' : '' }}>Class Teacher</option>
                                        <option value="subject_teacher" {{ old('role', $assignment->role) == 'subject_teacher' ? 'selected' : '' }}>Subject Teacher</option>
                                        <option value="assistant_teacher" {{ old('role', $assignment->role) == 'assistant_teacher' ? 'selected' : '' }}>Assistant Teacher</option>
                                    </select>
                                    @error('role')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="subject_assigned">Subject Assigned</label>
                                    <input type="text" name="subject_assigned" id="subject_assigned" 
                                           class="form-control @error('subject_assigned') is-invalid @enderror" 
                                           value="{{ old('subject_assigned', $assignment->subject_assigned) }}" 
                                           placeholder="e.g., Mathematics, Science">
                                    @error('subject_assigned')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">Optional: Specify subject if role is Subject Teacher</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="is_primary" name="is_primary" value="1" {{ old('is_primary', $assignment->is_primary) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_primary">
                                    Primary Assignment
                                </label>
                            </div>
                            <small class="form-text text-muted">Check if this is the primary assignment for this teacher in this class</small>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Update Assignment</button>
                            <a href="{{ route('admin.teacher-class-assignments.index') }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
