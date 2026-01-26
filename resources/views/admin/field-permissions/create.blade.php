@extends('layouts.app')

@section('title', 'Create Field Permission')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-plus-circle"></i> Create Field Permission</h4>
                </div>
                
                <div class="card-body">
                    <form action="{{ route('admin.field-permissions.store') }}" method="POST">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="model_type" class="form-label">Model Type <span class="text-danger">*</span></label>
                                    <select name="model_type" id="model_type" class="form-control @error('model_type') is-invalid @enderror" required>
                                        <option value="">Select Model Type</option>
                                        <option value="student" {{ old('model_type') == 'student' ? 'selected' : '' }}>Student</option>
                                        <option value="teacher" {{ old('model_type') == 'teacher' ? 'selected' : '' }}>Teacher</option>
                                    </select>
                                    @error('model_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="field_name" class="form-label">Field Name <span class="text-danger">*</span></label>
                                    <input type="text" name="field_name" id="field_name" 
                                           class="form-control @error('field_name') is-invalid @enderror" 
                                           value="{{ old('field_name') }}" required>
                                    @error('field_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">Enter the exact field name as it appears in the database/model (e.g., 'name', 'email', 'phone')</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                    <select name="role" id="role" class="form-control @error('role') is-invalid @enderror" required>
                                        <option value="">Select Role</option>
                                        <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>Admin</option>
                                        <option value="class_teacher" {{ old('role') == 'class_teacher' ? 'selected' : '' }}>Class Teacher</option>
                                        <option value="teacher" {{ old('role') == 'teacher' ? 'selected' : '' }}>Teacher</option>
                                        <option value="student" {{ old('role') == 'student' ? 'selected' : '' }}>Student</option>
                                        <option value="parent" {{ old('role') == 'parent' ? 'selected' : '' }}>Parent</option>
                                    </select>
                                    @error('role')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="permission_level" class="form-label">Permission Level <span class="text-danger">*</span></label>
                                    <select name="permission_level" id="permission_level" class="form-control @error('permission_level') is-invalid @enderror" required>
                                        <option value="">Select Permission Level</option>
                                        <option value="editable" {{ old('permission_level') == 'editable' ? 'selected' : '' }}>Editable</option>
                                        <option value="read_only" {{ old('permission_level') == 'read_only' ? 'selected' : '' }}>Read-Only</option>
                                        <option value="hidden" {{ old('permission_level') == 'hidden' ? 'selected' : '' }}>Hidden</option>
                                    </select>
                                    @error('permission_level')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1" 
                                       {{ old('is_active', true) ? 'checked' : '' }}>
                                <label for="is_active" class="form-check-label">Active</label>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="{{ route('admin.field-permissions.index') }}" class="btn btn-secondary me-md-2">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Permission
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection