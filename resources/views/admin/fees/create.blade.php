@extends('layouts.admin')

@section('title', 'Add Fee Record')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4>Add New Fee Record</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.fees.store') }}" method="POST">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="student_id" class="form-label">Student <span class="text-danger">*</span></label>
                                    <select name="student_id" id="student_id" class="form-control @error('student_id') is-invalid @enderror" required>
                                        <option value="">Select Student</option>
                                        @foreach($students as $student)
                                            <option value="{{ $student->id }}" {{ old('student_id') == $student->id ? 'selected' : '' }}>
                                                {{ $student->name }} ({{ $student->roll_number }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('student_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="fee_structure_id" class="form-label">Fee Structure <span class="text-danger">*</span></label>
                                    <select name="fee_structure_id" id="fee_structure_id" class="form-control @error('fee_structure_id') is-invalid @enderror" required>
                                        <option value="">Select Fee Structure</option>
                                        @foreach($feeStructures as $feeStructure)
                                            <option value="{{ $feeStructure->id }}" {{ old('fee_structure_id') == $feeStructure->id ? 'selected' : '' }}>
                                                {{ $feeStructure->name }} ({{ $feeStructure->class_name }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('fee_structure_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="academic_year" class="form-label">Academic Year <span class="text-danger">*</span></label>
                                    <input type="text" name="academic_year" id="academic_year" class="form-control @error('academic_year') is-invalid @enderror" 
                                           value="{{ old('academic_year', \Carbon\Carbon::now()->year . '-' . (\Carbon\Carbon::now()->year + 1)) }}" required>
                                    @error('academic_year')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="term" class="form-label">Term <span class="text-danger">*</span></label>
                                    <input type="text" name="term" id="term" class="form-control @error('term') is-invalid @enderror" 
                                           value="{{ old('term', 'Annual') }}" required>
                                    @error('term')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="amount" class="form-label">Total Amount <span class="text-danger">*</span></label>
                                    <input type="number" name="amount" id="amount" class="form-control @error('amount') is-invalid @enderror" 
                                           value="{{ old('amount') }}" step="0.01" required>
                                    @error('amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="paid_amount" class="form-label">Paid Amount <span class="text-danger">*</span></label>
                                    <input type="number" name="paid_amount" id="paid_amount" class="form-control @error('paid_amount') is-invalid @enderror" 
                                           value="{{ old('paid_amount', 0) }}" step="0.01" required>
                                    @error('paid_amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="due_date" class="form-label">Due Date <span class="text-danger">*</span></label>
                                    <input type="date" name="due_date" id="due_date" class="form-control @error('due_date') is-invalid @enderror" 
                                           value="{{ old('due_date', \Carbon\Carbon::now()->addMonth()->format('Y-m-d')) }}" required>
                                    @error('due_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                    <select name="status" id="status" class="form-control @error('status') is-invalid @enderror" required>
                                        <option value="pending" {{ old('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="paid" {{ old('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                                        <option value="partial" {{ old('status') == 'partial' ? 'selected' : '' }}>Partial</option>
                                    </select>
                                    @error('status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea name="notes" id="notes" class="form-control @error('notes') is-invalid @enderror" rows="3">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save Fee Record</button>
                            <a href="{{ route('admin.fees.index') }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
