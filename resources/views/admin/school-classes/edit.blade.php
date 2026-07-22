@extends('layouts.admin')

@section('title', 'Edit Class')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Edit Class: {{ $schoolClass->name }}</h1>
        <a href="{{ route('admin.school-classes.index') }}" class="btn btn-secondary">Back to Classes</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.school-classes.update', $schoolClass) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="name" class="form-label">Class Name *</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                   id="name" name="name" value="{{ old('name', $schoolClass->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="section_id" class="form-label">Section</label>
                            <select class="form-control @error('section_id') is-invalid @enderror"
                                    id="section_id" name="section_id">
                                <option value="">-- None --</option>
                                @foreach($sections as $section)
                                    <option value="{{ $section->id }}" {{ old('section_id', $schoolClass->section_id) == $section->id ? 'selected' : '' }}>
                                        {{ $section->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('section_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="academic_session_id" class="form-label">Academic Session *</label>
                            <select class="form-control @error('academic_session_id') is-invalid @enderror"
                                    id="academic_session_id" name="academic_session_id" required>
                                <option value="">-- Select --</option>
                                @foreach($academicSessions as $session)
                                    <option value="{{ $session->id }}" {{ old('academic_session_id', $schoolClass->academic_session_id) == $session->id ? 'selected' : '' }}>
                                        {{ $session->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('academic_session_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="teacher_id" class="form-label">Class Teacher</label>
                            <select class="form-control @error('teacher_id') is-invalid @enderror"
                                    id="teacher_id" name="teacher_id">
                                <option value="">-- None --</option>
                                @foreach($teachers as $teacher)
                                    <option value="{{ $teacher->id }}" {{ old('teacher_id', $schoolClass->teacher_id) == $teacher->id ? 'selected' : '' }}>
                                        {{ $teacher->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('teacher_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control @error('description') is-invalid @enderror"
                              id="description" name="description" rows="3">{{ old('description', $schoolClass->description) }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Update Class</button>
                    <a href="{{ route('admin.school-classes.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
