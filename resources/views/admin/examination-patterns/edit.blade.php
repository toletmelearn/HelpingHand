@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">Edit Examination Pattern</h1>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Edit Examination Pattern: {{ $examinationPattern->name }}</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.examination-patterns.update', $examinationPattern->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="name">Name *</label>
                                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $examinationPattern->name) }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="code">Code *</label>
                                    <input type="text" name="code" id="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $examinationPattern->code) }}" required placeholder="Unique identifier (e.g., cbse_pattern)">
                                    @error('code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="sort_order">Sort Order</label>
                                    <input type="number" name="sort_order" id="sort_order" class="form-control @error('sort_order') is-invalid @enderror" value="{{ old('sort_order', $examinationPattern->sort_order) }}" min="0">
                                    @error('sort_order')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="form-group mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="is_default" id="is_default" class="form-check-input" value="1" {{ old('is_default', $examinationPattern->is_default) ? 'checked' : '' }}>
                                        <label for="is_default" class="form-check-label">Set as Default</label>
                                    </div>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1" {{ old('is_active', $examinationPattern->is_active) ? 'checked' : '' }}>
                                        <label for="is_active" class="form-check-label">Active</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="description">Description</label>
                                    <textarea name="description" id="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description', $examinationPattern->description) }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="pattern_config">Pattern Configuration *</label>
                                    <textarea name="pattern_config" id="pattern_config" class="form-control @error('pattern_config') is-invalid @enderror" rows="10">{{ old('pattern_config', json_encode($examinationPattern->pattern_config, JSON_PRETTY_PRINT)) }}</textarea>
                                    <small class="form-text text-muted">JSON configuration for the examination pattern. Define exam types, subjects, marking schemes, etc.</small>
                                    @error('pattern_config')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.examination-patterns.index') }}" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Examination Pattern</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
