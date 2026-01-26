@extends('layouts.app')

@section('title', 'Edit Teacher Substitution - Admin Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-edit"></i> Edit Teacher Substitution
                    </h4>
                    <span class="badge badge-light">
                        {{ $teacherSubstitution->substitution_date->format('d M Y') }} - 
                        Period {{ $teacherSubstitution->period_number }}
                    </span>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.teacher-substitutions.update', $teacherSubstitution) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="substitution_date" class="form-label">Substitution Date *</label>
                                    <input type="date" name="substitution_date" id="substitution_date" 
                                           class="form-control @error('substitution_date') is-invalid @enderror" 
                                           value="{{ old('substitution_date', $teacherSubstitution->substitution_date->format('Y-m-d')) }}" required>
                                    @error('substitution_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="absent_teacher_id" class="form-label">Absent Teacher *</label>
                                    <select name="absent_teacher_id" id="absent_teacher_id" 
                                            class="form-control @error('absent_teacher_id') is-invalid @enderror" required>
                                        <option value="">Select Absent Teacher</option>
                                        @foreach($teachers as $teacher)
                                            <option value="{{ $teacher->id }}" 
                                                    {{ old('absent_teacher_id', $teacherSubstitution->absent_teacher_id) == $teacher->id ? 'selected' : '' }}>
                                                {{ $teacher->teacher_id }} - {{ $teacher->user->name ?? 'N/A' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('absent_teacher_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label for="class_id" class="form-label">Class *</label>
                                    <select name="class_id" id="class_id" 
                                            class="form-control @error('class_id') is-invalid @enderror" required>
                                        <option value="">Select Class</option>
                                        @foreach($classes as $class)
                                            <option value="{{ $class->id }}" 
                                                    {{ old('class_id', $teacherSubstitution->class_id) == $class->id ? 'selected' : '' }}>
                                                {{ $class->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('class_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label for="section_id" class="form-label">Section *</label>
                                    <select name="section_id" id="section_id" 
                                            class="form-control @error('section_id') is-invalid @enderror" required>
                                        <option value="">Select Section</option>
                                        @foreach($sections as $section)
                                            <option value="{{ $section->id }}" 
                                                    {{ old('section_id', $teacherSubstitution->section_id) == $section->id ? 'selected' : '' }}>
                                                {{ $section->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('section_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label for="subject_id" class="form-label">Subject *</label>
                                    <select name="subject_id" id="subject_id" 
                                            class="form-control @error('subject_id') is-invalid @enderror" required>
                                        <option value="">Select Subject</option>
                                        @foreach($subjects as $subject)
                                            <option value="{{ $subject->id }}" 
                                                    {{ old('subject_id', $teacherSubstitution->subject_id) == $subject->id ? 'selected' : '' }}>
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
                                <div class="form-group mb-3">
                                    <label for="period_number" class="form-label">Period Number *</label>
                                    <select name="period_number" id="period_number" 
                                            class="form-control @error('period_number') is-invalid @enderror" required>
                                        <option value="">Select Period</option>
                                        @foreach($periods as $period)
                                            <option value="{{ $period }}" 
                                                    {{ old('period_number', $teacherSubstitution->period_number) == $period ? 'selected' : '' }}>
                                                Period {{ $period }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('period_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="period_name" class="form-label">Period Name</label>
                                    <input type="text" name="period_name" id="period_name" 
                                           class="form-control @error('period_name') is-invalid @enderror" 
                                           value="{{ old('period_name', $teacherSubstitution->period_name) }}" 
                                           placeholder="e.g., Morning Assembly, Break, etc.">
                                    @error('period_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="status" class="form-label">Status *</label>
                                    <select name="status" id="status" 
                                            class="form-control @error('status') is-invalid @enderror" required>
                                        <option value="pending" {{ old('status', $teacherSubstitution->status) == 'pending' ? 'selected' : '' }}>
                                            Pending
                                        </option>
                                        <option value="assigned" {{ old('status', $teacherSubstitution->status) == 'assigned' ? 'selected' : '' }}>
                                            Assigned
                                        </option>
                                        <option value="approved" {{ old('status', $teacherSubstitution->status) == 'approved' ? 'selected' : '' }}>
                                            Approved
                                        </option>
                                        <option value="cancelled" {{ old('status', $teacherSubstitution->status) == 'cancelled' ? 'selected' : '' }}>
                                            Cancelled
                                        </option>
                                    </select>
                                    @error('status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="substitute_teacher_id" class="form-label">Substitute Teacher</label>
                                    <select name="substitute_teacher_id" id="substitute_teacher_id" 
                                            class="form-control @error('substitute_teacher_id') is-invalid @enderror">
                                        <option value="">Select Substitute Teacher (Optional)</option>
                                        @foreach($teachers as $teacher)
                                            <option value="{{ $teacher->id }}" 
                                                    {{ old('substitute_teacher_id', $teacherSubstitution->substitute_teacher_id) == $teacher->id ? 'selected' : '' }}>
                                                {{ $teacher->teacher_id }} - {{ $teacher->user->name ?? 'N/A' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('substitute_teacher_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label for="reason" class="form-label">Reason for Absence</label>
                            <textarea name="reason" id="reason" 
                                      class="form-control @error('reason') is-invalid @enderror" 
                                      rows="3" 
                                      placeholder="Enter reason for teacher's absence">{{ old('reason', $teacherSubstitution->reason) }}</textarea>
                            @error('reason')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.teacher-substitutions.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to List
                            </a>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Substitution
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection