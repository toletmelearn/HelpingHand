@extends('layouts.admin')

@section('title', 'Add Language')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Add Language</h4>
                
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.language-settings.index') }}">Languages</a></li>
                        <li class="breadcrumb-item active">Add</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">New Language</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.language-settings.store') }}">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="locale" class="form-label">Locale Code *</label>
                                    <input type="text" name="locale" id="locale" class="form-control @error('locale') is-invalid @enderror" value="{{ old('locale') }}" required>
                                    <div class="form-text">e.g., en, es, fr, hi, etc.</div>
                                    @error('locale')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Language Name *</label>
                                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                                    <div class="form-text">e.g., English, Spanish, French, Hindi, etc.</div>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="flag" class="form-label">Flag Code</label>
                                    <input type="text" name="flag" id="flag" class="form-control @error('flag') is-invalid @enderror" value="{{ old('flag') }}">
                                    <div class="form-text">e.g., us, es, fr, in, etc. (optional)</div>
                                    @error('flag')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="is_default" id="is_default" class="form-check-input" value="1" {{ old('is_default') ? 'checked' : '' }}>
                                        <label for="is_default" class="form-check-label">Set as Default Language</label>
                                    </div>
                                    <div class="form-check mt-2">
                                        <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                        <label for="is_active" class="form-check-label">Active Language</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.language-settings.index') }}" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Add Language</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
