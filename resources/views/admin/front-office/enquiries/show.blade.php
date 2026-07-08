@extends('layouts.admin')

@section('title', 'Admission Enquiry Details')

@section('content')
<div class="container-fluid py-4">
    <!-- Breadcrumb / Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="{{ route('admin.front-office.enquiries.index') }}" class="btn btn-sm btn-outline-secondary me-2">
                <i class="bi bi-arrow-left"></i> Back to Enquiries
            </a>
            <h1 class="h3 d-inline-block align-middle mb-0 mt-2 mt-md-0">{{ $enquiry->candidate_name }}</h1>
        </div>
        <div>
            <a href="{{ route('admin.front-office.enquiries.edit', $enquiry->id) }}" class="btn btn-outline-secondary">
                <i class="bi bi-pencil"></i> Edit
            </a>
            @if(in_array($enquiry->status, ['selected', 'confirmed']))
                <a href="{{ route('admin.admissions.confirm-form', $enquiry->id) }}" class="btn btn-success">
                    <i class="bi bi-check2-circle"></i> Admit Student
                </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm bg-white mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-bold">Candidate & Guardian Details</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="small text-muted">Candidate / Student Name</div>
                            <div class="fw-medium">{{ $enquiry->candidate_name }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Parent / Guardian Name</div>
                            <div class="fw-medium">{{ $enquiry->parent_name }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted">Mobile Phone</div>
                            <div class="fw-medium">{{ $enquiry->phone }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted">Email Address</div>
                            <div class="fw-medium">{{ $enquiry->email ?: 'Not provided' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted">Aadhaar Number</div>
                            <div class="fw-medium">{{ $enquiry->aadhaar_number ?: 'Not provided' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm bg-white mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-bold">Interview & Evaluation</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="small text-muted">Interview Date</div>
                            <div class="fw-medium">{{ $enquiry->interview_date ? $enquiry->interview_date->format('M d, Y') : 'Not scheduled' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted">Interview Score</div>
                            <div class="fw-medium">{{ $enquiry->interview_score ?? '—' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted">Entrance Score</div>
                            <div class="fw-medium">{{ $enquiry->entrance_score ?? '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm bg-white mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-bold">Documents</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.front-office.enquiries.documents.upload', $enquiry->id) }}" method="POST" enctype="multipart/form-data" class="mb-3">
                        @csrf
                        <div class="input-group">
                            <input type="file" name="documents[]" class="form-control" multiple required accept=".jpg,.jpeg,.png,.pdf">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="bi bi-upload"></i> Upload
                            </button>
                        </div>
                        <div class="form-text">JPG, PNG or PDF, up to 5MB each. Birth certificate, Aadhaar card, transfer certificate, photo, etc.</div>
                    </form>

                    @if($enquiry->documents->isEmpty())
                        <p class="text-muted small mb-0">No documents uploaded yet.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr class="text-uppercase small text-muted">
                                        <th>File</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($enquiry->documents as $document)
                                        <tr>
                                            <td>
                                                <a href="{{ Storage::url($document->document_path) }}" target="_blank">{{ $document->original_filename }}</a>
                                            </td>
                                            <td class="text-capitalize">{{ str_replace('_', ' ', $document->document_type) }}</td>
                                            <td>
                                                @if($document->is_verified)
                                                    <span class="badge bg-success">Verified</span>
                                                @else
                                                    <span class="badge bg-warning text-dark">Pending</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#verifyDocModal-{{ $document->id }}">
                                                    Review
                                                </button>
                                            </td>
                                        </tr>

                                        <div class="modal fade" id="verifyDocModal-{{ $document->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <form action="{{ route('admin.front-office.enquiries.documents.verify', $document->id) }}" method="POST">
                                                        @csrf
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Review Document: {{ $document->original_filename }}</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body text-start">
                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold">Verification Status</label>
                                                                <select name="is_verified" class="form-select" required>
                                                                    <option value="1" {{ $document->is_verified ? 'selected' : '' }}>Verified</option>
                                                                    <option value="0" {{ !$document->is_verified ? 'selected' : '' }}>Not Verified / Pending</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold">Notes</label>
                                                                <textarea name="verification_notes" class="form-control" rows="3">{{ $document->verification_notes }}</textarea>
                                                            </div>
                                                            @if($document->verifier)
                                                                <p class="small text-muted mb-0">Last reviewed by {{ $document->verifier->name }} on {{ $document->verified_at?->format('M d, Y H:i') }}</p>
                                                            @endif
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-primary">Save</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm bg-white mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-bold">Payments</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.front-office.enquiries.payments.record', $enquiry->id) }}" method="POST" class="row g-2 mb-3">
                        @csrf
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Fee Type</label>
                            <select name="fee_type_id" class="form-select form-select-sm">
                                <option value="">Admission Fee (default)</option>
                                @foreach($feeTypes as $feeType)
                                    <option value="{{ $feeType->id }}">{{ $feeType->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Amount</label>
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Mode</label>
                            <select name="payment_mode" class="form-select form-select-sm" required>
                                @foreach(\App\Models\AdmissionEnquiryPayment::PAYMENT_MODES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Paid On</label>
                            <input type="date" name="paid_at" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Receipt No.</label>
                            <input type="text" name="receipt_no" class="form-control form-control-sm" placeholder="Auto-generated">
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-sm btn-outline-primary w-100">Add</button>
                        </div>
                    </form>

                    @if($enquiry->payments->isEmpty())
                        <p class="text-muted small mb-0">No payments recorded yet.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr class="text-uppercase small text-muted">
                                        <th>Receipt No.</th>
                                        <th>Fee Type</th>
                                        <th>Amount</th>
                                        <th>Mode</th>
                                        <th>Paid On</th>
                                        <th>Collected By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($enquiry->payments as $payment)
                                        <tr>
                                            <td>{{ $payment->receipt_no }}</td>
                                            <td>{{ $payment->feeType->name ?? 'Admission Fee' }}</td>
                                            <td>{{ number_format($payment->amount, 2) }}</td>
                                            <td>{{ \App\Models\AdmissionEnquiryPayment::PAYMENT_MODES[$payment->payment_mode] ?? $payment->payment_mode }}</td>
                                            <td>{{ $payment->paid_at->format('M d, Y') }}</td>
                                            <td>{{ $payment->collector->name ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm bg-white">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-bold">Follow-up & Remarks</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="small text-muted">Next Follow-up Date</div>
                            <div class="fw-medium">{{ $enquiry->follow_up_date ? $enquiry->follow_up_date->format('M d, Y') : 'None scheduled' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">School Visit Date/Time</div>
                            <div class="fw-medium">{{ $enquiry->visit_scheduled_at ? $enquiry->visit_scheduled_at->format('M d, Y H:i') : 'Not scheduled' }}</div>
                        </div>
                        <div class="col-12">
                            <div class="small text-muted">Follow-up Notes</div>
                            <div class="fw-medium">{{ $enquiry->follow_up_notes ?: 'No notes recorded' }}</div>
                        </div>
                        <div class="col-12">
                            <div class="small text-muted">Remarks</div>
                            <div class="fw-medium">{{ $enquiry->remarks ?: 'No remarks recorded' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm bg-white mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-bold">Status</h6>
                </div>
                <div class="card-body">
                    <span class="badge bg-{{ $enquiry->status === 'admitted' || $enquiry->status === 'confirmed' || $enquiry->status === 'selected' ? 'success' : ($enquiry->status === 'rejected' || $enquiry->status === 'closed' ? 'danger' : 'warning') }} rounded-pill text-capitalize fs-6">
                        {{ str_replace('_', ' ', $enquiry->status) }}
                    </span>
                </div>
            </div>

            <div class="card border-0 shadow-sm bg-white mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-bold">Assigned Counsellor</h6>
                </div>
                <div class="card-body">
                    @if($enquiry->counsellor)
                        <span class="badge bg-light text-dark border"><i class="bi bi-person me-1"></i>{{ $enquiry->counsellor->name }}</span>
                    @else
                        <span class="text-muted small">Unassigned</span>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm bg-white">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-bold">Record Info</h6>
                </div>
                <div class="card-body">
                    <div class="small text-muted">Enquiry Created</div>
                    <div class="fw-medium mb-2">{{ $enquiry->created_at->format('M d, Y H:i') }}</div>
                    <div class="small text-muted">Last Updated</div>
                    <div class="fw-medium">{{ $enquiry->updated_at->format('M d, Y H:i') }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
