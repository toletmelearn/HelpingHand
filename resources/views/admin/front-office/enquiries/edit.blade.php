@extends('layouts.admin')

@section('title', 'Edit Admission Enquiry')

@section('content')
<div class="container-fluid py-4">
    <!-- Breadcrumb / Header -->
    <div class="mb-4">
        <a href="{{ route('admin.front-office.enquiries.index') }}" class="btn btn-sm btn-outline-secondary me-2">
            <i class="bi bi-arrow-left"></i> Back to Enquiries
        </a>
        <h1 class="h3 d-inline-block align-middle mb-0 mt-2 mt-md-0">Edit Enquiry Details</h1>
    </div>

    <!-- Duplicate Alert Warning Container (Dynamic via JS) -->
    <div id="duplicate-warning-container" class="alert alert-warning d-none shadow-sm" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
        <span>An enquiry record with similar contact details already exists!</span>
        <div id="duplicate-details" class="small mt-2 font-monospace"></div>
    </div>

    <div class="card border-0 shadow-sm bg-white">
        <div class="card-body">
            <form action="{{ route('admin.front-office.enquiries.update', $enquiry->id) }}" method="POST" id="enquiry-form">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <!-- Candidate Name -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Candidate / Student Name <span class="text-danger">*</span></label>
                        <input type="text" name="candidate_name" class="form-control" value="{{ old('candidate_name', $enquiry->candidate_name) }}" required>
                    </div>

                    <!-- Parent/Guardian Name -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Parent / Guardian Name <span class="text-danger">*</span></label>
                        <input type="text" name="parent_name" class="form-control" value="{{ old('parent_name', $enquiry->parent_name) }}" required>
                    </div>

                    <!-- Phone Number -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Mobile Phone <span class="text-danger">*</span></label>
                        <input type="text" name="phone" id="phone" class="form-control" value="{{ old('phone', $enquiry->phone) }}" required>
                    </div>

                    <!-- Email Address -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Email Address</label>
                        <input type="email" name="email" id="email" class="form-control" value="{{ old('email', $enquiry->email) }}">
                    </div>

                    <!-- Aadhaar Number -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Aadhaar Number (12 Digit)</label>
                        <input type="text" name="aadhaar_number" id="aadhaar_number" class="form-control" value="{{ old('aadhaar_number', $enquiry->aadhaar_number) }}">
                    </div>

                    <!-- Counsellor assignment -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Assign Counsellor</label>
                        <select name="counsellor_id" class="form-select">
                            <option value="">Select Staff Counsellor</option>
                            @foreach($counsellors as $counsellor)
                                <option value="{{ $counsellor->id }}" {{ old('counsellor_id', $enquiry->counsellor_id) == $counsellor->id ? 'selected' : '' }}>
                                    {{ $counsellor->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Visit Scheduled -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">School Visit Date/Time</label>
                        <input type="datetime-local" name="visit_scheduled_at" class="form-control" value="{{ old('visit_scheduled_at', $enquiry->visit_scheduled_at ? $enquiry->visit_scheduled_at->format('Y-m-d\TH:i') : '') }}">
                    </div>

                    <!-- Next Follow-up Date -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Next Follow-up Date</label>
                        <input type="date" name="follow_up_date" class="form-control" value="{{ old('follow_up_date', $enquiry->follow_up_date ? $enquiry->follow_up_date->format('Y-m-d') : '') }}">
                    </div>

                    <!-- Status -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Enquiry Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-select" required>
                            <option value="new" {{ old('status', $enquiry->status) === 'new' ? 'selected' : '' }}>New Enquiry</option>
                            <option value="interested" {{ old('status', $enquiry->status) === 'interested' ? 'selected' : '' }}>Interested</option>
                            <option value="follow_up" {{ old('status', $enquiry->status) === 'follow_up' ? 'selected' : '' }}>Follow-up Required</option>
                            <option value="documents_pending" {{ old('status', $enquiry->status) === 'documents_pending' ? 'selected' : '' }}>Documents Pending</option>
                            <option value="selected" {{ old('status', $enquiry->status) === 'selected' ? 'selected' : '' }}>Selected (Evaluated)</option>
                            <option value="confirmed" {{ old('status', $enquiry->status) === 'confirmed' ? 'selected' : '' }}>Confirmed (Ready to Admit)</option>
                            <option value="closed" {{ old('status', $enquiry->status) === 'closed' ? 'selected' : '' }}>Closed</option>
                            <option value="rejected" {{ old('status', $enquiry->status) === 'rejected' ? 'selected' : '' }}>Rejected</option>
                            <option value="admitted" {{ old('status', $enquiry->status) === 'admitted' ? 'selected' : '' }}>Admitted</option>
                        </select>
                    </div>

                    <!-- Interview / Evaluation -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Interview Date</label>
                        <input type="date" name="interview_date" class="form-control" value="{{ old('interview_date', optional($enquiry->interview_date)->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Interview Score (0-100)</label>
                        <input type="number" step="0.01" min="0" max="100" name="interview_score" class="form-control" value="{{ old('interview_score', $enquiry->interview_score) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Entrance Score (0-100)</label>
                        <input type="number" step="0.01" min="0" max="100" name="entrance_score" class="form-control" value="{{ old('entrance_score', $enquiry->entrance_score) }}">
                    </div>

                    <!-- Remarks -->
                    <div class="col-12">
                        <label class="form-label fw-bold">Remarks / Notes</label>
                        <textarea name="remarks" class="form-control" rows="3">{{ old('remarks', $enquiry->remarks) }}</textarea>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary btn-lg px-5">Update Enquiry</button>
                    <a href="{{ route('admin.front-office.enquiries.index') }}" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
                    @if(in_array($enquiry->status, ['selected', 'confirmed']))
                        <a href="{{ route('admin.admissions.confirm-form', $enquiry->id) }}" class="btn btn-success btn-lg ms-2">
                            <i class="bi bi-check2-circle"></i> Admit Student
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm bg-white mt-4">
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
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const phoneInput = document.getElementById('phone');
    const emailInput = document.getElementById('email');
    const aadhaarInput = document.getElementById('aadhaar_number');
    const warningContainer = document.getElementById('duplicate-warning-container');
    const warningDetails = document.getElementById('duplicate-details');

    function checkDuplicates() {
        const phone = phoneInput.value;
        const email = emailInput.value;
        const aadhaar = aadhaarInput.value;

        if (phone.length < 5 && email.length < 5 && aadhaar.length < 5) {
            warningContainer.classList.add('d-none');
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        fetch("{{ route('admin.front-office.enquiries.check-duplicate') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ phone, email, aadhaar_number: aadhaar, id: "{{ $enquiry->id }}" })
        })
        .then(response => response.json())
        .then(data => {
            if (data.duplicate) {
                warningContainer.classList.remove('d-none');
                warningDetails.innerHTML = `Found existing match: candidate: "${data.record.candidate_name}" (Parent: ${data.record.parent_name}). Link: <a href="/admin/front-office/enquiries/${data.record.id}">View Enquiry #${data.record.id}</a>`;
            } else {
                warningContainer.classList.add('d-none');
            }
        })
        .catch(err => console.error("Error checking duplicates", err));
    }

    // Debounced triggers
    let timeout = null;
    [phoneInput, emailInput, aadhaarInput].forEach(element => {
        if(element) {
            element.addEventListener('keyup', function() {
                clearTimeout(timeout);
                timeout = setTimeout(checkDuplicates, 600);
            });
        }
    });
});
</script>
@endsection
