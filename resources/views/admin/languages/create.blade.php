@extends('layouts.admin')

@section('title', 'Add New Language')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>
            <i class="bi bi-plus-circle"></i> Add New Language
        </h1>
        <a href="{{ route('admin.languages.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Languages
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-globe"></i> Language Configuration</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.languages.store') }}">
                        @csrf
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Language Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                   id="name" name="name" value="{{ old('name') }}" required>
                            <div class="form-text">Enter the full name of the language (e.g., English, Hindi)</div>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="code" class="form-label">Language Code <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('code') is-invalid @enderror" 
                                           id="code" name="code" value="{{ old('code') }}" 
                                           placeholder="en" required>
                                    <div class="form-text">ISO language code (e.g., en, hi, es, fr)</div>
                                    @error('code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="flag_icon" class="form-label">Flag Icon</label>
                                    <input type="text" class="form-control @error('flag_icon') is-invalid @enderror" 
                                           id="flag_icon" name="flag_icon" value="{{ old('flag_icon') }}" 
                                           placeholder="ðŸ‡¬ðŸ‡§">
                                    <div class="form-text">Emoji flag or icon code</div>
                                    @error('flag_icon')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="sort_order" class="form-label">Sort Order</label>
                                    <input type="number" class="form-control @error('sort_order') is-invalid @enderror" 
                                           id="sort_order" name="sort_order" value="{{ old('sort_order', 0) }}" min="0">
                                    <div class="form-text">Lower numbers appear first in language switcher</div>
                                    @error('sort_order')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Default Language</label>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" 
                                               id="is_default" name="is_default" value="1" 
                                               {{ old('is_default') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_default">
                                            Set as default language
                                        </label>
                                    </div>
                                    <div class="form-text text-warning">
                                        <i class="bi bi-exclamation-triangle"></i> 
                                        Only one language can be default. This will override the current default.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" 
                                       id="is_active" name="is_active" value="1" 
                                       {{ old('is_active', true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">
                                    Active Language
                                </label>
                            </div>
                            <div class="form-text">
                                Inactive languages won't appear in the language switcher
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.languages.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Create Language
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Common Language Codes Reference -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-info-circle"></i> Common Language Codes Reference</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>Most Common:</h6>
                    <ul class="list-unstyled">
                        <li><strong>en</strong> - English ðŸ‡¬ðŸ‡§</li>
                        <li><strong>hi</strong> - Hindi ðŸ‡®ðŸ‡³</li>
                        <li><strong>es</strong> - Spanish ðŸ‡ªðŸ‡¸</li>
                        <li><strong>fr</strong> - French ðŸ‡«ðŸ‡·</li>
                        <li><strong>de</strong> - German ðŸ‡©ðŸ‡ª</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6>Regional:</h6>
                    <ul class="list-unstyled">
                        <li><strong>ta</strong> - Tamil</li>
                        <li><strong>te</strong> - Telugu</li>
                        <li><strong>bn</strong> - Bengali</li>
                        <li><strong>mr</strong> - Marathi</li>
                        <li><strong>gu</strong> - Gujarati</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
