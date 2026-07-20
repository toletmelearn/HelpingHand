@extends('layouts.admin')

@section('title', 'Register Admission Enquiry')

@section('content')
<div class="container-fluid py-4">
    <!-- Breadcrumb / Header -->
    <div class="mb-4">
        <a href="{{ route('admin.front-office.enquiries.index') }}" class="btn btn-sm btn-outline-secondary me-2">
            <i class="bi bi-arrow-left"></i> Back to Enquiries
        </a>
        <h1 class="h3 d-inline-block align-middle mb-0 mt-2 mt-md-0">Register New Enquiry</h1>
    </div>

    <!-- Duplicate Alert Warning Container (Dynamic via JS) -->
    <div id="duplicate-warning-container" class="alert alert-warning d-none shadow-sm" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
        <span>An enquiry record with similar contact details already exists!</span>
        <div id="duplicate-details" class="small mt-2 font-monospace"></div>
    </div>

    <div class="card border-0 shadow-sm bg-white">
        <div class="card-body">
            <form action="{{ route('admin.front-office.enquiries.store') }}" method="POST" id="enquiry-form">
                @csrf

                <div class="row g-3">
                    <!-- Candidate Name -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Candidate / Student Name <span class="text-danger">*</span></label>
                        <input type="text" name="candidate_name" class="form-control" placeholder="Enter student full name" value="{{ old('candidate_name') }}" required>
                    </div>

                    <!-- Parent/Guardian Name -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Parent / Guardian Name <span class="text-danger">*</span></label>
                        <input type="text" name="parent_name" class="form-control" placeholder="Enter father or mother name" value="{{ old('parent_name') }}" required>
                    </div>

                    <!-- Phone Number -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Mobile Phone <span class="text-danger">*</span></label>
                        <input type="text" name="phone" id="phone" class="form-control" placeholder="Primary phone number" value="{{ old('phone') }}" required>
                    </div>

                    <!-- Email Address -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Email Address</label>
                        <input type="email" name="email" id="email" class="form-control" placeholder="parent@example.com" value="{{ old('email') }}">
                    </div>

                    <!-- Aadhaar Number -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Aadhaar Number (12 Digit)</label>
                        <input type="text" name="aadhaar_number" id="aadhaar_number" class="form-control" placeholder="0000 0000 0000" value="{{ old('aadhaar_number') }}">
                    </div>

                    <!-- Counsellor assignment -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Assign Counsellor</label>
                        <select name="counsellor_id" class="form-select">
                            <option value="">Select Staff Counsellor</option>
                            @foreach($counsellors as $counsellor)
                                <option value="{{ $counsellor->id }}" {{ old('counsellor_id') == $counsellor->id ? 'selected' : '' }}>
                                    {{ $counsellor->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Visit Scheduled -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">School Visit Date/Time</label>
                        <input type="datetime-local" name="visit_scheduled_at" class="form-control" value="{{ old('visit_scheduled_at') }}">
                    </div>

                    <!-- Next Follow-up Date -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">First Follow-up Date</label>
                        <input type="date" name="follow_up_date" class="form-control" min="{{ date('Y-m-d') }}" value="{{ old('follow_up_date') }}">
                    </div>

                    <!-- Status -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Enquiry Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-select" required>
                            <option value="new" {{ old('status') === 'new' || is_null(old('status')) ? 'selected' : '' }}>New Enquiry</option>
                            <option value="interested" {{ old('status') === 'interested' ? 'selected' : '' }}>Interested</option>
                            <option value="follow_up" {{ old('status') === 'follow_up' ? 'selected' : '' }}>Follow-up Required</option>
                            <option value="documents_pending" {{ old('status') === 'documents_pending' ? 'selected' : '' }}>Documents Pending</option>
                            <option value="closed" {{ old('status') === 'closed' ? 'selected' : '' }}>Closed</option>
                            <option value="rejected" {{ old('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                        </select>
                    </div>

                    <!-- Remarks -->
                    <div class="col-12">
                        <label class="form-label fw-bold">Remarks / Notes</label>
                        <textarea name="remarks" class="form-control" rows="3" placeholder="Add specific parent requirements, source of enquiry, or details...">{{ old('remarks') }}</textarea>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary btn-lg px-5">Save Enquiry</button>
                    <a href="{{ route('admin.front-office.enquiries.index') }}" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
                </div>
            </form>
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
            body: JSON.stringify({ phone, email, aadhaar_number: aadhaar })
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
