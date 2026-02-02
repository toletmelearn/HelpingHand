@extends('layouts.admin')

@section('title', 'Create Admit Card Format')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">
                    <h4>Create Admit Card Format</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.admit-card-formats.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label for="name" class="form-label">Format Name *</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                   id="name" name="name" value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="header_html" class="form-label">Header HTML</label>
                            <textarea class="form-control @error('header_html') is-invalid @enderror" 
                                      id="header_html" name="header_html" rows="5">{{ old('header_html') }}</textarea>
                            <div class="form-text">HTML content for the header section of the admit card.</div>
                            @error('header_html')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="body_html" class="form-label">Body HTML</label>
                            <textarea class="form-control @error('body_html') is-invalid @enderror" 
                                      id="body_html" name="body_html" rows="10">{{ old('body_html') }}</textarea>
                            <div class="form-text">HTML content for the main body of the admit card. You can use placeholders like {student_name}, {exam_name}, etc.</div>
                            @error('body_html')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="footer_html" class="form-label">Footer HTML</label>
                            <textarea class="form-control @error('footer_html') is-invalid @enderror" 
                                      id="footer_html" name="footer_html" rows="5">{{ old('footer_html') }}</textarea>
                            <div class="form-text">HTML content for the footer section of the admit card.</div>
                            @error('footer_html')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="background_image" class="form-label">Background Image URL</label>
                            <input type="text" class="form-control @error('background_image') is-invalid @enderror" 
                                   id="background_image" name="background_image" value="{{ old('background_image') }}">
                            <div class="form-text">URL to background image for the admit card.</div>
                            @error('background_image')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input @error('is_active') is-invalid @enderror" 
                                   id="is_active" name="is_active" value="1" {{ old('is_active') ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">Active</label>
                            @error('is_active')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="{{ route('admin.admit-card-formats.index') }}" class="btn btn-secondary me-md-2">Cancel</a>
                            <button type="submit" class="btn btn-primary">Create Format</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
