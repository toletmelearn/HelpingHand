@extends('layouts.admin')

@section('title', 'Edit Fee Structure')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4>Edit Fee Structure</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.fee-structures.update', $feeStructure) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" 
                                           value="{{ old('name', $feeStructure->name) }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="class_name" class="form-label">Class <span class="text-danger">*</span></label>
                                    <select name="class_name" id="class_name" class="form-control @error('class_name') is-invalid @enderror" required>
                                        <option value="">Select Class</option>
                                        @foreach($classes as $class)
                                            <option value="{{ $class->name }}" {{ old('class_name', $feeStructure->class_name) == $class->name ? 'selected' : '' }}>
                                                {{ $class->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('class_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="term" class="form-label">Term <span class="text-danger">*</span></label>
                                    <input type="text" name="term" id="term" class="form-control @error('term') is-invalid @enderror" 
                                           value="{{ old('term', $feeStructure->term) }}" required>
                                    @error('term')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="frequency" class="form-label">Frequency <span class="text-danger">*</span></label>
                                    <select name="frequency" id="frequency" class="form-control @error('frequency') is-invalid @enderror" required>
                                        <option value="">Select Frequency</option>
                                        <option value="Annual" {{ old('frequency', $feeStructure->frequency) == 'Annual' ? 'selected' : '' }}>Annual</option>
                                        <option value="Semester" {{ old('frequency', $feeStructure->frequency) == 'Semester' ? 'selected' : '' }}>Semester</option>
                                        <option value="Quarterly" {{ old('frequency', $feeStructure->frequency) == 'Quarterly' ? 'selected' : '' }}>Quarterly</option>
                                        <option value="Monthly" {{ old('frequency', $feeStructure->frequency) == 'Monthly' ? 'selected' : '' }}>Monthly</option>
                                    </select>
                                    @error('frequency')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="amount" class="form-label">Amount <span class="text-danger">*</span></label>
                                    <input type="number" name="amount" id="amount" class="form-control @error('amount') is-invalid @enderror" 
                                           value="{{ old('amount', $feeStructure->amount) }}" step="0.01" required>
                                    @error('amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="is_active" class="form-label">Status <span class="text-danger">*</span></label>
                                    <select name="is_active" id="is_active" class="form-control @error('is_active') is-invalid @enderror" required>
                                        <option value="1" {{ old('is_active', $feeStructure->is_active) ? 'selected' : '' }}>Active</option>
                                        <option value="0" {{ old('is_active', !$feeStructure->is_active) ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                    @error('is_active')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="valid_from" class="form-label">Valid From <span class="text-danger">*</span></label>
                                    <input type="date" name="valid_from" id="valid_from" class="form-control @error('valid_from') is-invalid @enderror" 
                                           value="{{ old('valid_from', $feeStructure->valid_from->format('Y-m-d')) }}" required>
                                    @error('valid_from')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="valid_until" class="form-label">Valid Until <span class="text-danger">*</span></label>
                                    <input type="date" name="valid_until" id="valid_until" class="form-control @error('valid_until') is-invalid @enderror" 
                                           value="{{ old('valid_until', $feeStructure->valid_until->format('Y-m-d')) }}" required>
                                    @error('valid_until')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea name="description" id="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description', $feeStructure->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Update Fee Structure</button>
                            <a href="{{ route('admin.fee-structures.index') }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
