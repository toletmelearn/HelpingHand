@extends('layouts.app')

@section('title', 'Certificate Management')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Certificate Management</h4>
                
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item active">Certificates</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Certificates</h4>
                    <a href="{{ route('admin.certificates.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Certificate
                    </a>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <select name="type" class="form-control" onchange="this.form.submit()">
                                <option value="">All Types</option>
                                <option value="tc" {{ request('type') == 'tc' ? 'selected' : '' }}>Transfer Certificate</option>
                                <option value="bonafide" {{ request('type') == 'bonafide' ? 'selected' : '' }}>Bonafide Certificate</option>
                                <option value="character" {{ request('type') == 'character' ? 'selected' : '' }}>Character Certificate</option>
                                <option value="experience" {{ request('type') == 'experience' ? 'selected' : '' }}>Experience Certificate</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-control" onchange="this.form.submit()">
                                <option value="">All Statuses</option>
                                <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                                <option value="generated" {{ request('status') == 'generated' ? 'selected' : '' }}>Generated</option>
                                <option value="published" {{ request('status') == 'published' ? 'selected' : '' }}>Published</option>
                                <option value="locked" {{ request('status') == 'locked' ? 'selected' : '' }}>Locked</option>
                                <option value="revoked" {{ request('status') == 'revoked' ? 'selected' : '' }}>Revoked</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Search certificates..." value="{{ request('search') }}">
                                <button class="btn btn-outline-secondary" type="submit">Search</button>
                            </div>
                        </div>
                    </div>

                    <form method="GET">
                        <!-- Hidden inputs to preserve other query parameters -->
                        <input type="hidden" name="type" value="{{ request('type') }}">
                        <input type="hidden" name="status" value="{{ request('status') }}">
                        
                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Serial Number</th>
                                        <th>Type</th>
                                        <th>Recipient</th>
                                        <th>Status</th>
                                        <th>Created By</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($certificates as $certificate)
                                    <tr>
                                        <td>{{ $certificate->serial_number }}</td>
                                        <td>
                                            <span class="badge bg-primary">{{ strtoupper($certificate->certificate_type) }}</span>
                                        </td>
                                        <td>
                                            @if($certificate->recipient_type === 'App\Models\Student')
                                                <span class="text-primary">Student: {{ $certificate->recipient->name ?? 'N/A' }}</span>
                                            @elseif($certificate->recipient_type === 'App\Models\Teacher')
                                                <span class="text-success">Teacher: {{ $certificate->recipient->name ?? 'N/A' }}</span>
                                            @else
                                                {{ $certificate->recipient_type }}
                                            @endif
                                        </td>
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
                                        <td>{{ $certificate->creator->name ?? 'N/A' }}</td>
                                        <td>{{ $certificate->created_at->format('d/m/Y h:i A') }}</td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    Actions
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="{{ route('admin.certificates.show', $certificate->id) }}"><i class="fas fa-eye text-info"></i> View</a></li>
                                                    @if($certificate->canBeModified())
                                                        <li><a class="dropdown-item" href="{{ route('admin.certificates.edit', $certificate->id) }}"><i class="fas fa-edit text-warning"></i> Edit</a></li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <form method="POST" action="{{ route('admin.certificates.destroy', $certificate->id) }}" style="display: inline;">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Are you sure you want to delete this certificate?')">
                                                                    <i class="fas fa-trash"></i> Delete
                                                                </button>
                                                            </form>
                                                        </li>
                                                    @endif
                                                    @if($certificate->canBeApproved())
                                                        <li><a class="dropdown-item" href="{{ route('admin.certificates.approve', $certificate->id) }}"><i class="fas fa-check text-success"></i> Approve</a></li>
                                                    @endif
                                                    @if($certificate->canBePublished())
                                                        <li><a class="dropdown-item" href="{{ route('admin.certificates.publish', $certificate->id) }}"><i class="fas fa-upload text-primary"></i> Publish</a></li>
                                                    @endif
                                                    @if(in_array($certificate->status, ['published']))
                                                        <li><a class="dropdown-item" href="{{ route('admin.certificates.lock', $certificate->id) }}"><i class="fas fa-lock text-secondary"></i> Lock</a></li>
                                                    @endif
                                                    @if($certificate->canBeRevoked())
                                                        <li><a class="dropdown-item" href="{{ route('admin.certificates.preview', $certificate->id) }}"><i class="fas fa-eye text-info"></i> Preview</a></li>
                                                        <li><a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#revokeModal{{ $certificate->id }}"><i class="fas fa-ban"></i> Revoke</a></li>
                                                    @endif
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- Revoke Modal -->
                                    <div class="modal fade" id="revokeModal{{ $certificate->id }}" tabindex="-1">
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
                                                            <label for="reason_{{ $certificate->id }}" class="form-label">Reason for Revocation</label>
                                                            <textarea name="reason" id="reason_{{ $certificate->id }}" class="form-control" rows="3" required></textarea>
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
                                    @empty
                                    <tr>
                                        <td colspan="7" class="text-center">No certificates found.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </form>

                    <div class="mt-3">
                        {{ $certificates->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection