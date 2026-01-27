@extends('layouts.app')

@section('title', 'View Certificate - ' . $certificate->serial_number)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Certificate Details</h4>
                
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.certificates.index') }}">Certificates</a></li>
                        <li class="breadcrumb-item active">{{ $certificate->serial_number }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Certificate #{{ $certificate->serial_number }}</h4>
                    <div>
                        @if($certificate->canBeModified())
                            <a href="{{ route('admin.certificates.edit', $certificate->id) }}" class="btn btn-warning btn-sm me-1">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                        @endif
                        <a href="{{ route('admin.certificates.preview', $certificate->id) }}" class="btn btn-info btn-sm" target="_blank">
                            <i class="fas fa-eye"></i> Preview
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th>Certificate Type:</th>
                                    <td><span class="badge bg-primary">{{ strtoupper($certificate->certificate_type) }}</span></td>
                                </tr>
                                <tr>
                                    <th>Serial Number:</th>
                                    <td>{{ $certificate->serial_number }}</td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <span class="badge 
                                            @if($certificate->status == 'draft') bg-warning
                                            @elseif($certificate->status == 'generated') bg-info
                                            @elseif($certificate->status == 'published') bg-success
                                            @elseif($certificate->status == 'locked') bg-secondary
                                            @elseif($certificate->status == 'revoked') bg-danger
                                            @endif
                                        ">
                                            {{ $certificate->status_label }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Recipient:</th>
                                    <td>
                                        @if($certificate->recipient_type === 'App\Models\Student')
                                            <span class="text-primary">Student: {{ $certificate->recipient->name ?? 'N/A' }}</span>
                                        @elseif($certificate->recipient_type === 'App\Models\Teacher')
                                            <span class="text-success">Teacher: {{ $certificate->recipient->name ?? 'N/A' }}</span>
                                        @else
                                            {{ $certificate->recipient_type }}
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th>Created By:</th>
                                    <td>{{ $certificate->creator->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Created At:</th>
                                    <td>{{ $certificate->created_at->format('d/m/Y h:i A') }}</td>
                                </tr>
                                <tr>
                                    <th>Approved By:</th>
                                    <td>{{ $certificate->approver->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Approved At:</th>
                                    <td>{{ $certificate->approved_at ? $certificate->approved_at->format('d/m/Y h:i A') : 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Published At:</th>
                                    <td>{{ $certificate->published_at ? $certificate->published_at->format('d/m/Y h:i A') : 'N/A' }}</td>
                                </tr>
                                @if($certificate->status === 'revoked')
                                <tr>
                                    <th>Revocation Reason:</th>
                                    <td class="text-danger">{{ $certificate->revocation_reason ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Revoked At:</th>
                                    <td class="text-danger">{{ $certificate->revoked_at ? $certificate->revoked_at->format('d/m/Y h:i A') : 'N/A' }}</td>
                                </tr>
                                @endif
                            </table>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h5 class="mt-4">Certificate Content</h5>
                    <div class="border p-3 rounded bg-light">
                        @foreach($certificate->content_data as $key => $value)
                            <div class="row mb-2">
                                <div class="col-md-4"><strong>{{ ucwords(str_replace('_', ' ', $key)) }}:</strong></div>
                                <div class="col-md-8">{{ $value }}</div>
                            </div>
                        @endforeach
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="{{ route('admin.certificates.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Certificates
                        </a>
                        <div>
                            @if($certificate->canBeApproved())
                                <a href="{{ route('admin.certificates.approve', $certificate->id) }}" class="btn btn-success me-1" onclick="return confirm('Are you sure you want to approve this certificate?')">
                                    <i class="fas fa-check"></i> Approve
                                </a>
                            @endif
                            @if($certificate->canBePublished())
                                <a href="{{ route('admin.certificates.publish', $certificate->id) }}" class="btn btn-primary me-1" onclick="return confirm('Are you sure you want to publish this certificate?')">
                                    <i class="fas fa-upload"></i> Publish
                                </a>
                            @endif
                            @if(in_array($certificate->status, ['published']))
                                <a href="{{ route('admin.certificates.lock', $certificate->id) }}" class="btn btn-secondary me-1" onclick="return confirm('Are you sure you want to lock this certificate?')">
                                    <i class="fas fa-lock"></i> Lock
                                </a>
                            @endif
                            @if($certificate->canBeRevoked())
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#revokeModal">
                                    <i class="fas fa-ban"></i> Revoke
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Revoke Modal -->
<div class="modal fade" id="revokeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Revoke Certificate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('admin.certificates.revoke', $certificate->id) }}">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for Revocation</label>
                        <textarea name="reason" id="reason" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Revoke Certificate</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection