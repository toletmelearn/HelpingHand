@extends('layouts.admin')

@section('title', 'Lost & Found Item Details')

@section('content')
<div class="container-fluid py-4">
    <!-- Breadcrumb / Header -->
    <div class="mb-4">
        <a href="{{ route('admin.front-office.lost-found.index') }}" class="btn btn-sm btn-outline-secondary me-2">
            <i class="bi bi-arrow-left"></i> Back to List
        </a>
        <h1 class="h3 d-inline-block align-middle mb-0 mt-2 mt-md-0">Lost/Found Item Details</h1>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm bg-white">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h5 class="fw-bold mb-0">Item Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped table-bordered align-middle">
                        <tbody>
                            <tr>
                                <th class="w-40 text-muted small text-uppercase">Article Name</th>
                                <td><strong>{{ $item->item_name }}</strong></td>
                            </tr>
                            <tr>
                                <th class="text-muted small text-uppercase">Report Type</th>
                                <td>
                                    <span class="badge bg-{{ $item->item_type === 'found' ? 'success' : 'warning' }} text-capitalize">
                                        {{ $item->item_type }} Report
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted small text-uppercase">Location Found / Turned In</th>
                                <td>{{ $item->location_found }}</td>
                            </tr>
                            @if($item->location_lost)
                                <tr>
                                    <th class="text-muted small text-uppercase">Expected Location Lost</th>
                                    <td>{{ $item->location_lost }}</td>
                                </tr>
                            @endif
                            <tr>
                                <th class="text-muted small text-uppercase">Reported Date</th>
                                <td>{{ $item->date_reported->format('M d, Y') }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted small text-uppercase">Reported By</th>
                                <td>
                                    @if($item->reportedByUser)
                                        {{ $item->reportedByUser->name }} (Staff)
                                    @else
                                        {{ $item->reported_by_name ?: 'General' }}
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="mt-3">
                        <label class="d-block text-uppercase small text-muted mb-1">Item Description</label>
                        <div class="p-3 bg-light rounded border border-light font-monospace small" style="white-space: pre-wrap;">{{ $item->description ?: 'No description provided.' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 shadow-sm bg-white h-100">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h5 class="fw-bold mb-0">Status & Claim Resolution</h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <label class="d-block text-uppercase small text-muted mb-1">Current Status</label>
                        <span class="badge bg-{{ $item->status === 'returned' ? 'success' : ($item->status === 'found' ? 'info' : 'danger') }} rounded-pill text-capitalize fs-6 px-3">
                            {{ $item->status }}
                        </span>
                    </div>

                    @if($item->status === 'returned')
                        <div class="bg-success bg-opacity-10 border border-success p-3 rounded mb-3">
                            <label class="d-block text-uppercase small text-success-emphasis mb-1 fw-bold"><i class="bi bi-patch-check-fill me-1"></i>Returned Claim Information</label>
                            <table class="table table-sm table-borderless mb-0 small">
                                <tbody>
                                    <tr>
                                        <th class="w-30 text-success-emphasis">Claimant Name:</th>
                                        <td class="fw-semibold text-success-emphasis">{{ $item->claimant_name }}</td>
                                    </tr>
                                    <tr>
                                        <th class="text-success-emphasis">Mobile Phone:</th>
                                        <td class="font-monospace text-success-emphasis">{{ $item->claimant_phone }}</td>
                                    </tr>
                                    <tr>
                                        <th class="text-success-emphasis">Claim Date:</th>
                                        <td class="text-success-emphasis">{{ $item->returned_at ? $item->returned_at->format('M d, Y h:i A') : 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="mt-2 text-success-emphasis">
                                <strong>Verification Notes:</strong>
                                <p class="mb-0 font-monospace small">{{ $item->verification_details }}</p>
                            </div>
                        </div>
                    @endif

                    @if($item->photo_path)
                        <div class="mb-3">
                            <label class="d-block text-uppercase small text-muted mb-2">Item Photo</label>
                            <div class="text-center p-3 bg-light rounded border">
                                <img src="{{ asset('storage/' . $item->photo_path) }}" alt="Item Photo" class="img-fluid rounded border shadow-sm" style="max-height: 250px;">
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
