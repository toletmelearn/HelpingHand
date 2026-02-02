@extends('layouts.admin')

@section('title', 'Bulk Upload Teachers - Admin Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-upload"></i> Bulk Upload Teachers
                    </h4>
                    <a href="{{ route('admin.teachers.index') }}" class="btn btn-light">
                        <i class="fas fa-arrow-left"></i> Back to Teachers
                    </a>
                </div>
                
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    
                    <!-- Import Summary -->
                    @if(session('import_summary'))
                        @php $summary = session('import_summary'); @endphp
                        <div class="alert alert-info">
                            <h5><i class="fas fa-chart-bar"></i> Import Summary</h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <strong>Total Rows:</strong> {{ $summary['total_rows'] }}
                                </div>
                                <div class="col-md-4">
                                    <strong class="text-success">Successfully Imported:</strong> {{ $summary['imported'] }}
                                </div>
                                <div class="col-md-4">
                                    <strong class="text-danger">Failed Rows:</strong> {{ $summary['failed'] }}
                                </div>
                            </div>
                        </div>
                    @endif
                    
                    <!-- Error Details -->
                    @if(session('import_errors'))
                        <div class="alert alert-warning">
                            <h5><i class="fas fa-exclamation-circle"></i> Import Errors</h5>
                            <ul class="mb-0">
                                @foreach(session('import_errors') as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    
                    <div class="row">
                        <!-- Upload Form -->
                        <div class="col-md-6">
                            <div class="card border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="fas fa-file-csv"></i> Upload CSV File</h5>
                                </div>
                                <div class="card-body">
                                    <form action="{{ route('admin.teachers.bulk-upload.store') }}" method="POST" enctype="multipart/form-data">
                                        @csrf
                                        
                                        <div class="mb-3">
                                            <label for="csv_file" class="form-label">
                                                <i class="fas fa-file-upload"></i> Select CSV File
                                            </label>
                                            <input type="file" class="form-control @error('csv_file') is-invalid @enderror" 
                                                   id="csv_file" name="csv_file" accept=".csv,.txt" required>
                                            @error('csv_file')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <div class="form-text">
                                                <i class="fas fa-info-circle"></i> Only CSV files are accepted (Max 2MB)
                                            </div>
                                        </div>
                                        
                                        <div class="d-grid gap-2">
                                            <button type="submit" class="btn btn-primary btn-lg">
                                                <i class="fas fa-cloud-upload-alt"></i> Upload and Process
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Instructions -->
                        <div class="col-md-6">
                            <div class="card border-info">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> Instructions</h5>
                                </div>
                                <div class="card-body">
                                    <h6><i class="fas fa-list-ol"></i> Steps:</h6>
                                    <ol>
                                        <li>Download the sample CSV file</li>
                                        <li>Fill in your teacher data following the format</li>
                                        <li>Save the file as CSV</li>
                                        <li>Upload using the form on the left</li>
                                    </ol>
                                    
                                    <h6 class="mt-3"><i class="fas fa-exclamation-triangle"></i> Important:</h6>
                                    <ul>
                                        <li>Keep the header row exactly as provided</li>
                                        <li><strong>full_name, email, phone</strong> are required fields</li>
                                        <li>email must be unique</li>
                                        <li>aadhar must be 12 digits (if provided)</li>
                                        <li>Phone must be 10 digits</li>
                                        <li>Date formats: YYYY-MM-DD or DD/MM/YYYY</li>
                                    </ul>
                                    
                                    <div class="mt-3">
                                        <a href="{{ route('admin.teachers.bulk-upload.sample') }}" 
                                           class="btn btn-info">
                                            <i class="fas fa-download"></i> Download Sample CSV
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Required Format -->
                            <div class="card border-secondary mt-3">
                                <div class="card-header bg-secondary text-white">
                                    <h5 class="mb-0"><i class="fas fa-table"></i> CSV Format</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th>Column</th>
                                                    <th>Required</th>
                                                    <th>Format</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr><td>full_name</td><td>✅</td><td>Text</td></tr>
                                                <tr><td>email</td><td>✅</td><td>Valid email</td></tr>
                                                <tr><td>phone</td><td>✅</td><td>10 digits</td></tr>
                                                <tr><td>aadhar</td><td>❌</td><td>12 digits</td></tr>
                                                <tr><td>gender</td><td>❌</td><td>male/female/other</td></tr>
                                                <tr><td>date_of_birth</td><td>❌</td><td>YYYY-MM-DD</td></tr>
                                                <tr><td>qualification</td><td>❌</td><td>Text</td></tr>
                                                <tr><td>subjects</td><td>❌</td><td>Comma-separated</td></tr>
                                                <tr><td>designation</td><td>❌</td><td>Text</td></tr>
                                                <tr><td>employee_id</td><td>❌</td><td>Text</td></tr>
                                                <tr><td>employment_type</td><td>❌</td><td>permanent/contractual</td></tr>
                                                <tr><td>wing</td><td>❌</td><td>primary/secondary/senior</td></tr>
                                                <tr><td>teacher_type</td><td>❌</td><td>teaching/non-teaching</td></tr>
                                                <tr><td>date_of_joining</td><td>❌</td><td>YYYY-MM-DD</td></tr>
                                                <tr><td>monthly_salary</td><td>❌</td><td>Number</td></tr>
                                                <tr><td>status</td><td>❌</td><td>active/inactive</td></tr>
                                                <tr><td>address</td><td>❌</td><td>Text</td></tr>
                                                <tr><td>bank_account</td><td>❌</td><td>Text</td></tr>
                                                <tr><td>ifsc</td><td>❌</td><td>Text</td></tr>
                                                <tr><td>experience</td><td>❌</td><td>Number (years)</td></tr>
                                            </tbody>
                                        </table>
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