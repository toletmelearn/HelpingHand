@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">Create Grading System</h1>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Add New Grading System</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.grading-systems.store') }}" method="POST">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="name">Name *</label>
                                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="grade">Grade *</label>
                                    <input type="text" name="grade" id="grade" class="form-control @error('grade') is-invalid @enderror" value="{{ old('grade') }}" required placeholder="e.g., A+, A, B+, B, etc.">
                                    @error('grade')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="min_percentage">Minimum Percentage *</label>
                                    <input type="number" name="min_percentage" id="min_percentage" class="form-control @error('min_percentage') is-invalid @enderror" value="{{ old('min_percentage', 0) }}" min="0" max="100" step="0.01" required>
                                    @error('min_percentage')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="max_percentage">Maximum Percentage</label>
                                    <input type="number" name="max_percentage" id="max_percentage" class="form-control @error('max_percentage') is-invalid @enderror" value="{{ old('max_percentage') }}" min="0" max="100" step="0.01">
                                    <small class="form-text text-muted">Leave blank for no upper limit</small>
                                    @error('max_percentage')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="grade_points">Grade Points</label>
                                    <input type="number" name="grade_points" id="grade_points" class="form-control @error('grade_points') is-invalid @enderror" value="{{ old('grade_points') }}" min="0" max="10" step="0.01">
                                    @error('grade_points')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="order">Display Order</label>
                                    <input type="number" name="order" id="order" class="form-control @error('order') is-invalid @enderror" value="{{ old('order', 0) }}" min="0">
                                    @error('order')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="form-group mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                        <label for="is_active" class="form-check-label">Active</label>
                                    </div>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="description">Description</label>
                                    <textarea name="description" id="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description') }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.grading-systems.index') }}" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Create Grading System</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
