@extends('layouts.admin')

@section('title', 'Courier Register Details')

@section('content')
<div class="container-fluid py-4">
    <!-- Breadcrumb / Header -->
    <div class="mb-4">
        <a href="{{ route('admin.front-office.couriers.index') }}" class="btn btn-sm btn-outline-secondary me-2">
            <i class="bi bi-arrow-left"></i> Back to Register
        </a>
        <h1 class="h3 d-inline-block align-middle mb-0 mt-2 mt-md-0">Courier Entry Details</h1>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm bg-white">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h5 class="fw-bold mb-0">Parcel Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped table-bordered align-middle">
                        <tbody>
                            <tr>
                                <th class="w-40 text-muted small text-uppercase">Tracking Number</th>
                                <td><strong class="font-monospace text-primary">{{ $courier->tracking_number }}</strong></td>
                            </tr>
                            <tr>
                                <th class="text-muted small text-uppercase">Courier Company</th>
                                <td>{{ $courier->courier_company }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted small text-uppercase">Direction</th>
                                <td>
                                    <span class="badge bg-{{ $courier->courier_type === 'incoming' ? 'info' : 'primary' }} text-capitalize">
                                        {{ $courier->courier_type }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted small text-uppercase">Parcel Type</th>
                                <td><span class="text-capitalize">{{ str_replace('_', ' ', $courier->parcel_type) }}</span></td>
                            </tr>
                            <tr>
                                <th class="text-muted small text-uppercase">Sender</th>
                                <td>{{ $courier->sender }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted small text-uppercase">Receiver</th>
                                <td>{{ $courier->receiver }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted small text-uppercase">Staff Recipient</th>
                                <td>{{ $courier->recipient ? $courier->recipient->name : 'Unassigned' }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted small text-uppercase">Delivery / Handover Date</th>
                                <td>{{ $courier->delivery_date ? $courier->delivery_date->format('F d, Y') : 'Pending Handover' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 shadow-sm bg-white h-100">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h5 class="fw-bold mb-0">Delivery Status & Attachments</h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <label class="d-block text-uppercase small text-muted mb-1">Status</label>
                        <span class="badge bg-{{ $courier->status === 'delivered' ? 'success' : ($courier->status === 'pending' ? 'warning' : 'danger') }} rounded-pill text-capitalize fs-6 px-3">
                            {{ $courier->status }}
                        </span>
                    </div>

                    @if($courier->attachment_path)
                        <div class="mb-3">
                            <label class="d-block text-uppercase small text-muted mb-2">Receipt Attachment</label>
                            <div class="p-3 bg-light rounded border border-light text-center">
                                @if(pathinfo($courier->attachment_path, PATHINFO_EXTENSION) === 'pdf')
                                    <i class="bi bi-file-earmark-pdf fs-1 text-danger"></i>
                                    <div class="mt-2 small font-monospace text-truncate">{{ basename($courier->attachment_path) }}</div>
                                    <a href="{{ asset('storage/' . $courier->attachment_path) }}" target="_blank" class="btn btn-sm btn-primary mt-2">Open PDF</a>
                                @else
                                    <img src="{{ asset('storage/' . $courier->attachment_path) }}" alt="Receipt" class="img-fluid rounded border shadow-sm mb-2" style="max-height: 200px;">
                                    <div><a href="{{ asset('storage/' . $courier->attachment_path) }}" target="_blank" class="btn btn-sm btn-primary mt-2">Open Photo</a></div>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="alert alert-secondary small"><i class="bi bi-info-circle me-1"></i>No receipt attachment uploaded for this courier entry.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
