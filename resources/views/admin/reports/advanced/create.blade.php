@extends('layouts.admin')

@section('title', 'Create Advanced Report')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>
            <i class="bi bi-plus-circle"></i> Create Advanced Report
        </h1>
        <a href="{{ route('admin.advanced-reports.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Reports
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-plus"></i> Report Configuration</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.advanced-reports.store') }}">
                        @csrf
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Report Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                   id="name" name="name" value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" name="description" rows="3">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="module" class="form-label">Module <span class="text-danger">*</span></label>
                                    <select class="form-select @error('module') is-invalid @enderror" 
                                            id="module" name="module" required>
                                        <option value="">Select Module</option>
                                        @foreach($modules as $module)
                                            <option value="{{ $module }}" {{ old('module') == $module ? 'selected' : '' }}>
                                                {{ ucfirst($module) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('module')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="type" class="form-label">Report Type <span class="text-danger">*</span></label>
                                    <select class="form-select @error('type') is-invalid @enderror" 
                                            id="type" name="type" required>
                                        <option value="">Select Type</option>
                                        @foreach($types as $type)
                                            <option value="{{ $type }}" {{ old('type') == $type ? 'selected' : '' }}>
                                                {{ ucfirst($type) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="chart_type" class="form-label">Chart Type</label>
                            <select class="form-select @error('chart_type') is-invalid @enderror" 
                                    id="chart_type" name="chart_type">
                                <option value="">None (Table/Summary)</option>
                                @foreach($chartTypes as $chartType)
                                    <option value="{{ $chartType }}" {{ old('chart_type') == $chartType ? 'selected' : '' }}>
                                        {{ ucfirst($chartType) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('chart_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                   value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">
                                Active Report
                            </label>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.advanced-reports.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Create Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
