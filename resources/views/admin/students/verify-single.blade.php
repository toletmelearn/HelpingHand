@extends('layouts.admin')

@section('title', 'Verify Student: ' . $student->name)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Verify Student: {{ $student->name }}</h3>
                    <div class="card-tools">
                        <a href="{{ route('admin.students.verify.index') }}" class="btn btn-sm btn-secondary">Back to List</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Student Details</h5>
                            <table class="table table-bordered">
                                <tr>
                                    <td><strong>ID:</strong></td>
                                    <td>{{ $student->id }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Name:</strong></td>
                                    <td>{{ $student->name }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Father's Name:</strong></td>
                                    <td>{{ $student->father_name }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Mother's Name:</strong></td>
                                    <td>{{ $student->mother_name }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Date of Birth:</strong></td>
                                    <td>{{ $student->date_of_birth ? $student->date_of_birth->format('d/m/Y') : '-' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Class:</strong></td>
                                    <td>{{ $student->class }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Section:</strong></td>
                                    <td>{{ $student->section }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Verification Status:</strong></td>
                                    <td>
                                        @if($student->is_verified)
                                            <span class="badge badge-success">Verified</span>
                                        @else
                                            <span class="badge badge-danger">Pending</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5>Upload Documents</h5>
                            <form action="{{ route('admin.students.verify.upload', $student) }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="form-group">
                                    <label for="documents">Select Documents to Upload</label>
                                    <input type="file" name="documents[]" id="documents" class="form-control" multiple accept=".jpg,.jpeg,.png,.pdf">
                                    <small class="form-text text-muted">Allowed formats: JPG, PNG, PDF. Max size: 5MB each.</small>
                                </div>
                                <button type="submit" class="btn btn-primary">Upload Documents</button>
                            </form>
                            
                            @if($student->is_verified == false && $student->documents->count() > 0)
                                <hr>
                                <form action="{{ route('admin.students.verify.mark-verified', $student) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-success">Mark as Verified</button>
                                </form>
                            @endif
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h5>Uploaded Documents</h5>
                    @if($student->documents->count() > 0)
                        <div class="row">
                            @foreach($student->documents as $document)
                            <div class="col-md-4 mb-3">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">
                                            {{ ucfirst(str_replace('_', ' ', $document->document_type)) }}
                                            <span class="float-right">
                                                @if($document->is_verified)
                                                    <span class="badge badge-success">Verified</span>
                                                @else
                                                    <span class="badge badge-warning">Pending</span>
                                                @endif
                                            </span>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Original File:</strong> {{ $document->original_filename }}</p>
                                        <p><strong>Size:</strong> {{ $document->file_size ? number_format($document->file_size / 1024, 2) . ' KB' : 'N/A' }}</p>
                                        
                                        @if($document->is_verified)
                                            <p><strong>Verified By:</strong> {{ $document->verifier ? $document->verifier->name : 'N/A' }}</p>
                                            <p><strong>Verified At:</strong> {{ $document->verified_at ? $document->verified_at->format('d/m/Y H:i') : 'N/A' }}</p>
                                            @if($document->verification_notes)
                                                <p><strong>Notes:</strong> {{ $document->verification_notes }}</p>
                                            @endif
                                        @else
                                            <form action="{{ route('admin.students.verify.document', $document) }}" method="POST" class="mt-2">
                                                @csrf
                                                <div class="form-group">
                                                    <textarea name="verification_notes" class="form-control" placeholder="Verification notes (optional)"></textarea>
                                                </div>
                                                <div class="form-group">
                                                    <button type="submit" name="is_verified" value="1" class="btn btn-success btn-sm">Mark Verified</button>
                                                    <button type="submit" name="is_verified" value="0" class="btn btn-danger btn-sm">Mark Rejected</button>
                                                </div>
                                            </form>
                                        @endif
                                        
                                        <div class="mt-2">
                                            <a href="{{ Storage::url($document->document_path) }}" target="_blank" class="btn btn-sm btn-info">View Document</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <p>No documents uploaded yet.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection