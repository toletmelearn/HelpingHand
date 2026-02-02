@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">Edit Student Status Record</h1>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Edit Student Status Record</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.student-statuses.update', $studentStatus->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="student_id">Student *</label>
                                    <select name="student_id" id="student_id" class="form-control @error('student_id') is-invalid @enderror" required>
                                        <option value="">Select Student</option>
                                        @foreach($students as $student)
                                            <option value="{{ $student->id }}" {{ old('student_id', $studentStatus->student_id) == $student->id ? 'selected' : '' }}>
                                                {{ $student->name }} ({{ $student->roll_number ?? 'N/A' }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('student_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="status">Status *</label>
                                    <select name="status" id="status" class="form-control @error('status') is-invalid @enderror" required>
                                        <option value="">Select Status</option>
                                        <option value="active" {{ old('status', $studentStatus->status) == 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="inactive" {{ old('status', $studentStatus->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                        <option value="passed_out" {{ old('status', $studentStatus->status) == 'passed_out' ? 'selected' : '' }}>Passed Out</option>
                                        <option value="tc_issued" {{ old('status', $studentStatus->status) == 'tc_issued' ? 'selected' : '' }}>TC Issued</option>
                                        <option value="left_school" {{ old('status', $studentStatus->status) == 'left_school' ? 'selected' : '' }}>Left School</option>
                                    </select>
                                    @error('status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="status_date">Status Date *</label>
                                    <input type="date" name="status_date" id="status_date" class="form-control @error('status_date') is-invalid @enderror" value="{{ old('status_date', $studentStatus->status_date->format('Y-m-d')) }}" required>
                                    @error('status_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="reason">Reason</label>
                                    <input type="text" name="reason" id="reason" class="form-control @error('reason') is-invalid @enderror" value="{{ old('reason', $studentStatus->reason) }}" placeholder="Reason for status change">
                                    @error('reason')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="document_number">Document Number</label>
                                    <input type="text" name="document_number" id="document_number" class="form-control @error('document_number') is-invalid @enderror" value="{{ old('document_number', $studentStatus->document_number) }}" placeholder="TC number or certificate number">
                                    @error('document_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="document_issue_date">Document Issue Date</label>
                                    <input type="date" name="document_issue_date" id="document_issue_date" class="form-control @error('document_issue_date') is-invalid @enderror" value="{{ old('document_issue_date', $studentStatus->document_issue_date ? $studentStatus->document_issue_date->format('Y-m-d') : '') }}">
                                    @error('document_issue_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="issued_by">Issued By</label>
                                    <input type="text" name="issued_by" id="issued_by" class="form-control @error('issued_by') is-invalid @enderror" value="{{ old('issued_by', $studentStatus->issued_by) }}" placeholder="Authority who issued the document">
                                    @error('issued_by')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="remarks">Remarks</label>
                                    <textarea name="remarks" id="remarks" class="form-control @error('remarks') is-invalid @enderror" rows="3">{{ old('remarks', $studentStatus->remarks) }}</textarea>
                                    @error('remarks')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.student-statuses.index') }}" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Student Status Record</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
