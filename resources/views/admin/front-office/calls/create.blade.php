@extends('layouts.admin')

@section('title', 'Log Phone Call')

@section('content')
<div class="container-fluid py-4">
    <!-- Breadcrumb / Header -->
    <div class="mb-4">
        <a href="{{ route('admin.front-office.calls.index') }}" class="btn btn-sm btn-outline-secondary me-2">
            <i class="bi bi-arrow-left"></i> Back to Logs
        </a>
        <h1 class="h3 d-inline-block align-middle mb-0 mt-2 mt-md-0">Log Telephone Call</h1>
    </div>

    <div class="card border-0 shadow-sm bg-white">
        <div class="card-body">
            <form action="{{ route('admin.front-office.calls.store') }}" method="POST">
                @csrf

                <div class="row g-3">
                    <!-- Caller Name -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Caller / Contact Name <span class="text-danger">*</span></label>
                        <input type="text" name="caller_name" class="form-control" placeholder="Enter contact full name" value="{{ old('caller_name') }}" required>
                    </div>

                    <!-- Phone Number -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Phone Number <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control" placeholder="Contact mobile or telephone" value="{{ old('phone') }}" required>
                    </div>

                    <!-- Call Type -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Call Direction <span class="text-danger">*</span></label>
                        <select name="call_type" class="form-select" required>
                            <option value="incoming" {{ old('call_type') === 'incoming' ? 'selected' : '' }}>Incoming Call</option>
                            <option value="outgoing" {{ old('call_type') === 'outgoing' ? 'selected' : '' }}>Outgoing Call</option>
                        </select>
                    </div>

                    <!-- Purpose -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Purpose <span class="text-danger">*</span></label>
                        <select name="purpose" class="form-select" required>
                            <option value="admission" {{ old('purpose') === 'admission' ? 'selected' : '' }}>Admission Enquiry</option>
                            <option value="fee_reminder" {{ old('purpose') === 'fee_reminder' ? 'selected' : '' }}>Fee Reminder</option>
                            <option value="emergency" {{ old('purpose') === 'emergency' ? 'selected' : '' }}>Emergency Contact</option>
                            <option value="general" {{ old('purpose') === 'general' ? 'selected' : '' }}>General Query</option>
                        </select>
                    </div>

                    <!-- Duration (Seconds) -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Call Duration (Seconds) <span class="text-danger">*</span></label>
                        <input type="number" name="duration" class="form-control" placeholder="Duration in seconds" value="{{ old('duration', 0) }}" min="0" required>
                    </div>

                    <!-- Status -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Call Outcome Status <span class="text-danger">*</span></label>
                        <select name="status" id="call_status" class="form-select" required>
                            <option value="completed" {{ old('status') === 'completed' ? 'selected' : '' }}>Completed / Resolved</option>
                            <option value="missed" {{ old('status') === 'missed' ? 'selected' : '' }}>Missed Call</option>
                            <option value="follow_up_required" {{ old('status') === 'follow_up_required' ? 'selected' : '' }}>Follow-up Required</option>
                        </select>
                    </div>

                    <!-- Follow up date -->
                    <div class="col-md-6 {{ old('status') === 'follow_up_required' ? '' : 'd-none' }}" id="follow_up_date_container">
                        <label class="form-label fw-bold">Scheduled Follow-up Date <span class="text-danger">*</span></label>
                        <input type="date" name="follow_up_date" class="form-control" min="{{ date('Y-m-d') }}" value="{{ old('follow_up_date') }}">
                    </div>

                    <!-- Assign user -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Assign to Staff Member (optional)</label>
                        <select name="assigned_user_id" class="form-select">
                            <option value="">Select Employee</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ old('assigned_user_id') == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Outcome Details -->
                    <div class="col-12">
                        <label class="form-label fw-bold">Outcome Discussion Notes</label>
                        <textarea name="outcome" class="form-control" rows="4" placeholder="Brief summary of phone conversation details...">{{ old('outcome') }}</textarea>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary btn-lg px-5">Save Log</button>
                    <a href="{{ route('admin.front-office.calls.index') }}" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusSelect = document.getElementById('call_status');
    const followUpContainer = document.getElementById('follow_up_date_container');
    const followUpInput = followUpContainer.querySelector('input');

    function toggleFollowUpDate() {
        if (statusSelect.value === 'follow_up_required') {
            followUpContainer.classList.remove('d-none');
            followUpInput.setAttribute('required', 'required');
        } else {
            followUpContainer.classList.add('d-none');
            followUpInput.removeAttribute('required');
            followUpInput.value = '';
        }
    }

    statusSelect.addEventListener('change', toggleFollowUpDate);
    // run on load in case of validation back
    toggleFollowUpDate();
});
</script>
@endsection
