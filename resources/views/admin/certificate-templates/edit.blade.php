@extends('layouts.app')

@section('title', 'Edit Certificate Template - ' . $certificateTemplate->name)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Edit Certificate Template</h4>
                
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.certificate-templates.index') }}">Certificate Templates</a></li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Edit Template: {{ $certificateTemplate->name }}</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.certificate-templates.update', $certificateTemplate->id) }}">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Template Name *</label>
                                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $certificateTemplate->name) }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="type" class="form-label">Template Type *</label>
                                    <select name="type" id="type" class="form-control @error('type') is-invalid @enderror" required>
                                        <option value="">Select Type</option>
                                        <option value="tc" {{ old('type', $certificateTemplate->type) == 'tc' ? 'selected' : '' }}>Transfer Certificate (TC)</option>
                                        <option value="bonafide" {{ old('type', $certificateTemplate->type) == 'bonafide' ? 'selected' : '' }}>Bonafide Certificate</option>
                                        <option value="character" {{ old('type', $certificateTemplate->type) == 'character' ? 'selected' : '' }}>Character Certificate</option>
                                        <option value="experience" {{ old('type', $certificateTemplate->type) == 'experience' ? 'selected' : '' }}>Experience Certificate</option>
                                    </select>
                                    @error('type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="template_variables" class="form-label">Template Variables *</label>
                            <div class="border p-3 rounded bg-light">
                                <small class="text-muted">Enter the variables that can be used in the template (one per line):</small>
                                <textarea name="template_variables[]" id="template_variables" class="form-control mt-2 @error('template_variables') is-invalid @enderror" rows="5" placeholder="Enter template variables, one per line&#10;recipient.name&#10;recipient.id&#10;certificate.serial_number&#10;certificate.type&#10;date" required>{{ implode("\n", old('template_variables', $certificateTemplate->template_variables ?? [])) }}</textarea>
                                @error('template_variables')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">These variables will be available as {{'{variable_name}'}} in the template.</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="template_content" class="form-label">Template Content *</label>
                            <div class="border p-3 rounded bg-light">
                                <small class="text-muted">Use the variables defined above in the template content:</small>
                                <div class="mt-2">
                                    <label class="form-label">HTML Template</label>
                                    <textarea name="template_content" id="template_content" class="form-control @error('template_content') is-invalid @enderror" rows="15" required>{{ old('template_content', $certificateTemplate->template_content) }}</textarea>
                                    @error('template_content')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3 form-check">
                                    <input type="checkbox" name="is_default" id="is_default" class="form-check-input" value="1" {{ old('is_default', $certificateTemplate->is_default) ? 'checked' : '' }}>
                                    <label for="is_default" class="form-check-label">Set as Default Template</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3 form-check">
                                    <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1" {{ old('is_active', $certificateTemplate->is_active) ? 'checked' : '' }}>
                                    <label for="is_active" class="form-check-label">Active Status</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.certificate-templates.show', $certificateTemplate->id) }}" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Template</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection