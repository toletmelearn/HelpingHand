@extends('layouts.admin')

@section('title', 'Edit Student - Class Teacher Control')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-user-edit"></i> Edit Student - {{ $student->name }}
                    </h4>
                    <span class="badge badge-light">Admission No: {{ $student->admission_no }}</span>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.class-teacher-control.update-student', $student->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="name" class="form-label">Full Name *</label>
                                    @if(isset($fieldPermissions['name']) && $fieldPermissions['name'] === 'editable')
                                        <input type="text" name="name" id="name" 
                                               class="form-control @error('name') is-invalid @enderror" 
                                               value="{{ old('name', $student->name) }}" required>
                                        @error('name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    @elseif(isset($fieldPermissions['name']) && $fieldPermissions['name'] === 'readonly')
                                        <input type="text" name="name" id="name" 
                                               class="form-control" 
                                               value="{{ old('name', $student->name) }}" readonly>
                                        <small class="form-text text-muted">Field is read-only</small>
                                    @else
                                        <input type="text" class="form-control" value="{{ $student->name }}" disabled>
                                        <small class="form-text text-muted">Field is hidden</small>
                                    @endif
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="admission_no" class="form-label">Admission Number</label>
                                    <input type="text" class="form-control" value="{{ $student->admission_no }}" disabled>
                                    <small class="form-text text-muted">Field is admin-only</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="father_name" class="form-label">Father's Name</label>
                                    @if(isset($fieldPermissions['father_name']) && $fieldPermissions['father_name'] === 'editable')
                                        <input type="text" name="father_name" id="father_name" 
                                               class="form-control @error('father_name') is-invalid @enderror" 
                                               value="{{ old('father_name', $student->father_name) }}">
                                        @error('father_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    @elseif(isset($fieldPermissions['father_name']) && $fieldPermissions['father_name'] === 'readonly')
                                        <input type="text" name="father_name" id="father_name" 
                                               class="form-control" 
                                               value="{{ old('father_name', $student->father_name) }}" readonly>
                                        <small class="form-text text-muted">Field is read-only</small>
                                    @else
                                        <input type="text" class="form-control" value="{{ $student->father_name }}" disabled>
                                        <small class="form-text text-muted">Field is hidden</small>
                                    @endif
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="mother_name" class="form-label">Mother's Name</label>
                                    @if(isset($fieldPermissions['mother_name']) && $fieldPermissions['mother_name'] === 'editable')
                                        <input type="text" name="mother_name" id="mother_name" 
                                               class="form-control @error('mother_name') is-invalid @enderror" 
                                               value="{{ old('mother_name', $student->mother_name) }}">
                                        @error('mother_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    @elseif(isset($fieldPermissions['mother_name']) && $fieldPermissions['mother_name'] === 'readonly')
                                        <input type="text" name="mother_name" id="mother_name" 
                                               class="form-control" 
                                               value="{{ old('mother_name', $student->mother_name) }}" readonly>
                                        <small class="form-text text-muted">Field is read-only</small>
                                    @else
                                        <input type="text" class="form-control" value="{{ $student->mother_name }}" disabled>
                                        <small class="form-text text-muted">Field is hidden</small>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    @if(isset($fieldPermissions['phone']) && $fieldPermissions['phone'] === 'editable')
                                        <input type="text" name="phone" id="phone" 
                                               class="form-control @error('phone') is-invalid @enderror" 
                                               value="{{ old('phone', $student->phone) }}">
                                        @error('phone')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    @elseif(isset($fieldPermissions['phone']) && $fieldPermissions['phone'] === 'readonly')
                                        <input type="text" name="phone" id="phone" 
                                               class="form-control" 
                                               value="{{ old('phone', $student->phone) }}" readonly>
                                        <small class="form-text text-muted">Field is read-only</small>
                                    @else
                                        <input type="text" class="form-control" value="{{ $student->phone }}" disabled>
                                        <small class="form-text text-muted">Field is hidden</small>
                                    @endif
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    @if(isset($fieldPermissions['email']) && $fieldPermissions['email'] === 'editable')
                                        <input type="email" name="email" id="email" 
                                               class="form-control @error('email') is-invalid @enderror" 
                                               value="{{ old('email', $student->email) }}">
                                        @error('email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    @elseif(isset($fieldPermissions['email']) && $fieldPermissions['email'] === 'readonly')
                                        <input type="email" name="email" id="email" 
                                               class="form-control" 
                                               value="{{ old('email', $student->email) }}" readonly>
                                        <small class="form-text text-muted">Field is read-only</small>
                                    @else
                                        <input type="email" class="form-control" value="{{ $student->email }}" disabled>
                                        <small class="form-text text-muted">Field is hidden</small>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label for="address" class="form-label">Address</label>
                            @if(isset($fieldPermissions['address']) && $fieldPermissions['address'] === 'editable')
                                <textarea name="address" id="address" 
                                          class="form-control @error('address') is-invalid @enderror" 
                                          rows="3">{{ old('address', $student->address) }}</textarea>
                                @error('address')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            @elseif(isset($fieldPermissions['address']) && $fieldPermissions['address'] === 'readonly')
                                <textarea name="address" id="address" 
                                          class="form-control" 
                                          rows="3" readonly>{{ old('address', $student->address) }}</textarea>
                                <small class="form-text text-muted">Field is read-only</small>
                            @else
                                <textarea class="form-control" rows="3" disabled>{{ $student->address }}</textarea>
                                <small class="form-text text-muted">Field is hidden</small>
                            @endif
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="class_id" class="form-label">Class *</label>
                                    <select name="class_id" id="class_id" 
                                            class="form-control @error('class_id') is-invalid @enderror" required disabled>
                                        <option value="">Select Class</option>
                                        @foreach($classes as $class)
                                            <option value="{{ $class->id }}" 
                                                    {{ old('class_id', $student->class_id) == $class->id ? 'selected' : '' }}>
                                                {{ $class->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="form-text text-muted">Class cannot be changed by class teacher</small>
                                    @error('class_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="section_id" class="form-label">Section *</label>
                                    <select name="section_id" id="section_id" 
                                            class="form-control @error('section_id') is-invalid @enderror" required disabled>
                                        <option value="">Select Section</option>
                                        @foreach($sections as $section)
                                            <option value="{{ $section->id }}" 
                                                    {{ old('section_id', $student->section_id) == $section->id ? 'selected' : '' }}>
                                                {{ $section->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="form-text text-muted">Section cannot be changed by class teacher</small>
                                    @error('section_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.class-teacher-control.student-records') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Records
                            </a>
                            @if(count(array_filter($fieldPermissions, function($perm) { return $perm === 'editable'; })) > 0)
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Student
                                </button>
                            @else
                                <button type="submit" class="btn btn-primary" disabled>
                                    <i class="fas fa-save"></i> No Editable Fields
                                </button>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
