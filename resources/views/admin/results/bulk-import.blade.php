@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Bulk Import Results - {{ $exam->name }}</h4>
                    <a href="{{ route('admin.results.index') }}" class="btn btn-secondary btn-sm">Back to Results</a>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5>Exam Details</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Name:</strong></td>
                                    <td>{{ $exam->name }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Subject:</strong></td>
                                    <td>{{ $exam->subject }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Total Marks:</strong></td>
                                    <td>{{ $exam->total_marks }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5>Instructions</h5>
                            <ul>
                                <li>Download the sample template first</li>
                                <li>Fill in student roll numbers, subjects, and marks</li>
                                <li>Save as Excel file (.xlsx)</li>
                                <li>Upload the completed file below</li>
                                <li>Marks cannot exceed {{ $exam->total_marks }}</li>
                            </ul>
                            <a href="{{ route('admin.results.download-template') }}" class="btn btn-info btn-sm">
                                <i class="bi bi-download"></i> Download Sample Template
                            </a>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Upload Excel File</h5>
                                </div>
                                <div class="card-body">
                                    <form action="{{ route('admin.results.process-bulk-import', $exam->id) }}" method="POST" enctype="multipart/form-data">
                                        @csrf
                                        
                                        <div class="form-group mb-3">
                                            <label for="excel_file">Excel File *</label>
                                            <input type="file" name="excel_file" id="excel_file" 
                                                   class="form-control @error('excel_file') is-invalid @enderror" 
                                                   accept=".xlsx,.xls,.csv" required>
                                            <small class="form-text text-muted">Supported formats: XLSX, XLS, CSV (Max 10MB)</small>
                                            @error('excel_file')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        
                                        <div class="form-check mb-3">
                                            <input type="checkbox" name="overwrite_existing" id="overwrite_existing" class="form-check-input">
                                            <label class="form-check-label" for="overwrite_existing">
                                                Overwrite existing results for same students/subjects
                                            </label>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-upload"></i> Import Results
                                        </button>
                                        <a href="{{ route('admin.results.index') }}" class="btn btn-secondary">Cancel</a>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Expected Format</h5>
                                </div>
                                <div class="card-body">
                                    <p><strong>Columns Required:</strong></p>
                                    <ol>
                                        <li><strong>Student Roll Number</strong> - Exact roll number</li>
                                        <li><strong>Subject</strong> - Subject name</li>
                                        <li><strong>Marks Obtained</strong> - Numeric marks</li>
                                    </ol>
                                    
                                    <p><strong>Example:</strong></p>
                                    <pre class="bg-light p-2 small">
STU001  Mathematics  85
STU002  Science      78
STU003  English      92
                                    </pre>
                                    
                                    <div class="alert alert-info">
                                        <strong>Note:</strong> Headers will be ignored. Only data rows will be processed.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection