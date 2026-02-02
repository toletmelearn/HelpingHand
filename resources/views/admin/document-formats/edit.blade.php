@extends('layouts.admin')

@section('title', 'Edit Document Format')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Edit Document Format: {{ $documentFormat->name }}</h3>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.document-formats.update', $documentFormat) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">Name *</label>
                                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" 
                                           value="{{ old('name', $documentFormat->name) }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="type">Type</label>
                                    <select name="type" id="type" class="form-control @error('type') is-invalid @enderror">
                                        <option value="">Select Type</option>
                                        <option value="certificate" {{ old('type', $documentFormat->type) == 'certificate' ? 'selected' : '' }}>Certificate</option>
                                        <option value="admit-card" {{ old('type', $documentFormat->type) == 'admit-card' ? 'selected' : '' }}>Admit Card</option>
                                        <option value="report" {{ old('type', $documentFormat->type) == 'report' ? 'selected' : '' }}>Report</option>
                                        <option value="letterhead" {{ old('type', $documentFormat->type) == 'letterhead' ? 'selected' : '' }}>Letterhead</option>
                                        <option value="transcript" {{ old('type', $documentFormat->type) == 'transcript' ? 'selected' : '' }}>Transcript</option>
                                        <option value="other" {{ old('type', $documentFormat->type) == 'other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                    @error('type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea name="description" id="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description', $documentFormat->description) }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="header_content">Header Content</label>
                                    <textarea name="header_content" id="header_content" class="form-control @error('header_content') is-invalid @enderror" rows="3">{{ old('header_content', $documentFormat->header_content) }}</textarea>
                                    @error('header_content')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="footer_content">Footer Content</label>
                                    <textarea name="footer_content" id="footer_content" class="form-control @error('footer_content') is-invalid @enderror" rows="3">{{ old('footer_content', $documentFormat->footer_content) }}</textarea>
                                    @error('footer_content')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="page_size">Page Size</label>
                                    <select name="page_size" id="page_size" class="form-control @error('page_size') is-invalid @enderror">
                                        <option value="A4" {{ old('page_size', $documentFormat->page_size) == 'A4' ? 'selected' : '' }}>A4</option>
                                        <option value="A3" {{ old('page_size', $documentFormat->page_size) == 'A3' ? 'selected' : '' }}>A3</option>
                                        <option value="A5" {{ old('page_size', $documentFormat->page_size) == 'A5' ? 'selected' : '' }}>A5</option>
                                        <option value="Letter" {{ old('page_size', $documentFormat->page_size) == 'Letter' ? 'selected' : '' }}>Letter</option>
                                        <option value="Legal" {{ old('page_size', $documentFormat->page_size) == 'Legal' ? 'selected' : '' }}>Legal</option>
                                        <option value="Tabloid" {{ old('page_size', $documentFormat->page_size) == 'Tabloid' ? 'selected' : '' }}>Tabloid</option>
                                    </select>
                                    @error('page_size')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="orientation">Orientation</label>
                                    <select name="orientation" id="orientation" class="form-control @error('orientation') is-invalid @enderror">
                                        <option value="portrait" {{ old('orientation', $documentFormat->orientation) == 'portrait' ? 'selected' : '' }}>Portrait</option>
                                        <option value="landscape" {{ old('orientation', $documentFormat->orientation) == 'landscape' ? 'selected' : '' }}>Landscape</option>
                                    </select>
                                    @error('orientation')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="margin_top">Top Margin (mm)</label>
                                            <input type="number" name="margin_top" id="margin_top" class="form-control @error('margin_top') is-invalid @enderror" 
                                                   value="{{ old('margin_top', $documentFormat->margin_top) }}" min="0" max="100" step="0.1">
                                            @error('margin_top')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="margin_bottom">Bottom Margin (mm)</label>
                                            <input type="number" name="margin_bottom" id="margin_bottom" class="form-control @error('margin_bottom') is-invalid @enderror" 
                                                   value="{{ old('margin_bottom', $documentFormat->margin_bottom) }}" min="0" max="100" step="0.1">
                                            @error('margin_bottom')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="margin_left">Left Margin (mm)</label>
                                            <input type="number" name="margin_left" id="margin_left" class="form-control @error('margin_left') is-invalid @enderror" 
                                                   value="{{ old('margin_left', $documentFormat->margin_left) }}" min="0" max="100" step="0.1">
                                            @error('margin_left')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="margin_right">Right Margin (mm)</label>
                                            <input type="number" name="margin_right" id="margin_right" class="form-control @error('margin_right') is-invalid @enderror" 
                                                   value="{{ old('margin_right', $documentFormat->margin_right) }}" min="0" max="100" step="0.1">
                                            @error('margin_right')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="form-check mb-3">
                                    <input type="checkbox" name="is_active" id="is_active" class="form-check-input @error('is_active') is-invalid @enderror" 
                                           value="1" {{ old('is_active', $documentFormat->is_active) ? 'checked' : '' }}>
                                    <label for="is_active" class="form-check-label">Active</label>
                                    @error('is_active')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-check mb-3">
                                    <input type="checkbox" name="is_default" id="is_default" class="form-check-input @error('is_default') is-invalid @enderror" 
                                           value="1" {{ old('is_default', $documentFormat->is_default) ? 'checked' : '' }}>
                                    <label for="is_default" class="form-check-label">Set as Default</label>
                                    <small class="form-text text-muted">Setting this as default will unset the current default format.</small>
                                    @error('is_default')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Update Document Format</button>
                            <a href="{{ route('admin.document-formats.index') }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection