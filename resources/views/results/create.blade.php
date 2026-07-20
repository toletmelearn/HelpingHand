@extends('layouts.app')

@section('title', 'Add New Result')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-plus-circle me-2"></i>
                        Add New CBSE Result
                    </h4>
                </div>
                
                <div class="card-body">
                    <form action="{{ route('results.store') }}" method="POST">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="student_id" class="form-label required">Student</label>
                                    <select name="student_id" id="student_id" class="form-select @error('student_id') is-invalid @enderror" required>
                                        <option value="">Select Student</option>
                                        @foreach($students as $student)
                                            <option value="{{ $student->id }}" {{ old('student_id') == $student->id ? 'selected' : '' }}>
                                                {{ $student->name }} ({{ $student->class->name }} - {{ $student->section->name ?? 'N/A' }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('student_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="exam_id" class="form-label required">Exam</label>
                                    <select name="exam_id" id="exam_id" class="form-select @error('exam_id') is-invalid @enderror" required>
                                        <option value="">Select Exam</option>
                                        @foreach($exams as $exam)
                                            <option value="{{ $exam->id }}" {{ old('exam_id') == $exam->id ? 'selected' : '' }}>
                                                {{ $exam->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('exam_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="subject_id" class="form-label required">Subject</label>
                                    <select name="subject_id" id="subject_id" class="form-select @error('subject_id') is-invalid @enderror" required>
                                        <option value="">Select Subject</option>
                                        @foreach($subjects as $subject)
                                            <option value="{{ $subject->id }}" {{ old('subject_id') == $subject->id ? 'selected' : '' }}>
                                                {{ $subject->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('subject_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="academic_year" class="form-label required">Academic Year</label>
                                    <select name="academic_year" id="academic_year" class="form-select @error('academic_year') is-invalid @enderror" required>
                                        <option value="">Select Academic Year</option>
                                        @foreach($academicYears as $year)
                                            <option value="{{ $year }}" {{ old('academic_year') == $year ? 'selected' : '' }}>
                                                {{ $year }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('academic_year')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="term" class="form-label required">Term</label>
                                    <select name="term" id="term" class="form-select @error('term') is-invalid @enderror" required>
                                        <option value="">Select Term</option>
                                        @foreach($terms as $term)
                                            <option value="{{ $term }}" {{ old('term') == $term ? 'selected' : '' }}>
                                                {{ $term }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('term')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="remarks" class="form-label">Remarks</label>
                                    <textarea name="remarks" id="remarks" class="form-control @error('remarks') is-invalid @enderror" rows="3">{{ old('remarks') }}</textarea>
                                    @error('remarks')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <h5 class="mb-4">CBSE Assessment Components</h5>
                        
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="pt_marks" class="form-label">Periodic Test Marks (Max: 25)</label>
                                    <input type="number" name="pt_marks" id="pt_marks" 
                                           class="form-control @error('pt_marks') is-invalid @enderror" 
                                           step="0.01" min="0" max="25" 
                                           value="{{ old('pt_marks') }}" 
                                           placeholder="Enter PT marks">
                                    @error('pt_marks')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="notebook_marks" class="form-label">Notebook Marks (Max: 5)</label>
                                    <input type="number" name="notebook_marks" id="notebook_marks" 
                                           class="form-control @error('notebook_marks') is-invalid @enderror" 
                                           step="0.01" min="0" max="5" 
                                           value="{{ old('notebook_marks') }}" 
                                           placeholder="Enter notebook marks">
                                    @error('notebook_marks')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="sea_marks" class="form-label">SEA Marks (Max: 5)</label>
                                    <input type="number" name="sea_marks" id="sea_marks" 
                                           class="form-control @error('sea_marks') is-invalid @enderror" 
                                           step="0.01" min="0" max="5" 
                                           value="{{ old('sea_marks') }}" 
                                           placeholder="Enter SEA marks">
                                    @error('sea_marks')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="exam_marks" class="form-label">Exam Marks (Max: 65)</label>
                                    <input type="number" name="exam_marks" id="exam_marks" 
                                           class="form-control @error('exam_marks') is-invalid @enderror" 
                                           step="0.01" min="0" max="65" 
                                           value="{{ old('exam_marks') }}" 
                                           placeholder="Enter exam marks">
                                    @error('exam_marks')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <!-- Preview Calculation -->
                        <div class="card bg-light mb-4">
                            <div class="card-body">
                                <h6 class="card-title">Auto-Calculation Preview</h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <strong>Total Marks:</strong> <span id="preview-total">0</span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Percentage:</strong> <span id="preview-percentage">0%</span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Grade:</strong> <span id="preview-grade">-</span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Status:</strong> <span id="preview-status">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('results.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Back to Results
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-1"></i> Save Result
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ptInput = document.getElementById('pt_marks');
    const notebookInput = document.getElementById('notebook_marks');
    const seaInput = document.getElementById('sea_marks');
    const examInput = document.getElementById('exam_marks');
    
    const totalSpan = document.getElementById('preview-total');
    const percentageSpan = document.getElementById('preview-percentage');
    const gradeSpan = document.getElementById('preview-grade');
    const statusSpan = document.getElementById('preview-status');
    
    function calculatePreview() {
        const pt = parseFloat(ptInput.value) || 0;
        const notebook = parseFloat(notebookInput.value) || 0;
        const sea = parseFloat(seaInput.value) || 0;
        const exam = parseFloat(examInput.value) || 0;
        
        const total = pt + notebook + sea + exam;
        const percentage = total > 0 ? ((total / 100) * 100).toFixed(2) : 0;
        
        // CBSE Grade calculation
        let grade = 'F';
        if (percentage >= 91) grade = 'A1';
        else if (percentage >= 81) grade = 'A2';
        else if (percentage >= 71) grade = 'B1';
        else if (percentage >= 61) grade = 'B2';
        else if (percentage >= 51) grade = 'C1';
        else if (percentage >= 41) grade = 'C2';
        else if (percentage >= 33) grade = 'D';
        
        const status = percentage >= 33 ? 'Pass' : 'Fail';
        
        totalSpan.textContent = total.toFixed(2);
        percentageSpan.textContent = percentage + '%';
        gradeSpan.textContent = grade;
        statusSpan.textContent = status;
        
        // Color coding
        gradeSpan.className = grade.startsWith('A') ? 'text-success' : 
                             (grade.startsWith('B') ? 'text-primary' : 
                             (grade.startsWith('C') ? 'text-warning' : 'text-danger'));
        statusSpan.className = status === 'Pass' ? 'text-success' : 'text-danger';
    }
    
    // Add event listeners
    [ptInput, notebookInput, seaInput, examInput].forEach(input => {
        input.addEventListener('input', calculatePreview);
    });
    
    // Initial calculation
    calculatePreview();
});
</script>
@endpush

@push('styles')
<style>
.required:after {
    content: " *";
    color: red;
}
.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}
.form-label {
    font-weight: 500;
}
</style>
@endpush