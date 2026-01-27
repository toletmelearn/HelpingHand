@extends('layouts.app')

@section('title', 'Edit Certificate - ' . $certificate->serial_number)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Edit Certificate</h4>
                
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.certificates.index') }}">Certificates</a></li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Edit Certificate #{{ $certificate->serial_number }}</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.certificates.update', $certificate->id) }}">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="certificate_type" class="form-label">Certificate Type</label>
                                    <input type="text" class="form-control" value="{{ strtoupper($certificate->certificate_type) }}" readonly>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="recipient_type" class="form-label">Recipient Type</label>
                                    <input type="text" class="form-control" value="{{ $certificate->recipient_type }}" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="recipient_info" class="form-label">Recipient</label>
                                    <input type="text" class="form-control" value="{{ $certificate->recipient->name ?? 'N/A' }}" readonly>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="current_status" class="form-label">Current Status</label>
                                    <input type="text" class="form-control" value="{{ $certificate->status_label }}" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="content_data" class="form-label">Certificate Content *</label>
                            <div class="border p-3 rounded bg-light">
                                <small class="text-muted">Update the certificate content with appropriate fields:</small>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <div class="mb-2">
                                            <label class="form-label">Recipient Name</label>
                                            <input type="text" name="content_data[recipient_name]" class="form-control" value="{{ old('content_data.recipient_name', $certificate->content_data['recipient_name'] ?? '') }}" placeholder="Enter recipient name">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">Date</label>
                                            <input type="text" name="content_data[date]" class="form-control" value="{{ old('content_data.date', $certificate->content_data['date'] ?? date('d/m/Y')) }}" placeholder="Enter date">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">Class</label>
                                            <input type="text" name="content_data[class]" class="form-control" value="{{ old('content_data.class', $certificate->content_data['class'] ?? '') }}" placeholder="Enter class">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-2">
                                            <label class="form-label">Course/Position</label>
                                            <input type="text" name="content_data[course_position]" class="form-control" value="{{ old('content_data.course_position', $certificate->content_data['course_position'] ?? '') }}" placeholder="Enter course or position">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">Duration</label>
                                            <input type="text" name="content_data[duration]" class="form-control" value="{{ old('content_data.duration', $certificate->content_data['duration'] ?? '') }}" placeholder="Enter duration">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">Additional Details</label>
                                            <input type="text" name="content_data[details]" class="form-control" value="{{ old('content_data.details', $certificate->content_data['details'] ?? '') }}" placeholder="Enter additional details">
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Certificate Body</label>
                                    <textarea name="content_data[body]" class="form-control" rows="4" placeholder="Enter the main body of the certificate">{{ old('content_data.body', $certificate->content_data['body'] ?? '') }}</textarea>
                                </div>
                            </div>
                            @error('content_data')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.certificates.show', $certificate->id) }}" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Certificate</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection