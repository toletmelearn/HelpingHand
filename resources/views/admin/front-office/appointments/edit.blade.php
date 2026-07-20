@extends('layouts.admin')

@section('title', 'Edit Appointment')

@section('content')
<div class="container-fluid py-4">
    <!-- Breadcrumb / Header -->
    <div class="mb-4">
        <a href="{{ route('admin.front-office.appointments.index') }}" class="btn btn-sm btn-outline-secondary me-2">
            <i class="bi bi-arrow-left"></i> Back to Schedule
        </a>
        <h1 class="h3 d-inline-block align-middle mb-0 mt-2 mt-md-0">Edit Appointment Details</h1>
    </div>

    @if($errors->any())
        <div class="alert alert-danger shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i>
            <strong>Conflict / Validation Error!</strong> Please review the schedule and adjust timestamps.
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm bg-white">
        <div class="card-body">
            <form action="{{ route('admin.front-office.appointments.update', $appointment->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <!-- Visitor/Attendee Name -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Visitor / Attendee Name <span class="text-danger">*</span></label>
                        <input type="text" name="visitor_name" class="form-control" value="{{ old('visitor_name', $appointment->visitor_name) }}" required>
                    </div>

                    <!-- Guardian ID Connection -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Connected Guardian (optional)</label>
                        <select name="guardian_id" id="guardian_id" class="form-control select2">
                            @if(isset($selectedGuardian) && $selectedGuardian)
                                <option value="{{ $selectedGuardian->id }}" selected>
                                    {{ $selectedGuardian->name }} (Phone: {{ $selectedGuardian->phone }}, Email: {{ $selectedGuardian->email ?? 'N/A' }})
                                </option>
                            @else
                                <option value="">Search Connected Guardian...</option>
                            @endif
                        </select>
                    </div>

                    <!-- Staff Member to meet -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Staff Member to Meet <span class="text-danger">*</span></label>
                        <select name="teacher_id" id="teacher_id" class="form-control select2" required>
                            @if(isset($selectedTeacher) && $selectedTeacher)
                                <option value="{{ $selectedTeacher->id }}" selected>
                                    {{ $selectedTeacher->name }} (Email: {{ $selectedTeacher->email }})
                                </option>
                            @else
                                <option value="">Search Employee...</option>
                            @endif
                        </select>
                    </div>

                    <!-- Date -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Scheduled Date <span class="text-danger">*</span></label>
                        <input type="date" name="scheduled_date" class="form-control" value="{{ old('scheduled_date', $appointment->scheduled_date ? $appointment->scheduled_date->format('Y-m-d') : '') }}" required>
                    </div>

                    <!-- Start Time -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Start Time <span class="text-danger">*</span></label>
                        <input type="time" name="start_time" class="form-control" value="{{ old('start_time', \Carbon\Carbon::parse($appointment->start_time)->format('H:i')) }}" required>
                    </div>

                    <!-- End Time -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">End Time <span class="text-danger">*</span></label>
                        <input type="time" name="end_time" class="form-control" value="{{ old('end_time', \Carbon\Carbon::parse($appointment->end_time)->format('H:i')) }}" required>
                    </div>

                    <!-- Status -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Initial Appointment Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-select" required>
                            <option value="pending" {{ old('status', $appointment->status) === 'pending' ? 'selected' : '' }}>Pending Approval</option>
                            <option value="approved" {{ old('status', $appointment->status) === 'approved' ? 'selected' : '' }}>Approved / Confirmed</option>
                            <option value="rejected" {{ old('status', $appointment->status) === 'rejected' ? 'selected' : '' }}>Rejected</option>
                            <option value="completed" {{ old('status', $appointment->status) === 'completed' ? 'selected' : '' }}>Completed</option>
                            <option value="no_show" {{ old('status', $appointment->status) === 'no_show' ? 'selected' : '' }}>No Show</option>
                        </select>
                    </div>

                    <!-- Purpose -->
                    <div class="col-12">
                        <label class="form-label fw-bold">Purpose of Meeting <span class="text-danger">*</span></label>
                        <input type="text" name="purpose" class="form-control" value="{{ old('purpose', $appointment->purpose) }}" required>
                    </div>

                    <!-- Discussion Brief / Feedback -->
                    <div class="col-12">
                        <label class="form-label fw-bold">Internal Receptionist Notes</label>
                        <textarea name="feedback" class="form-control" rows="3">{{ old('feedback', $appointment->feedback) }}</textarea>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary btn-lg px-5">Update Appointment</button>
                    <a href="{{ route('admin.front-office.appointments.index') }}" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<!-- Include Select2 CSS and JS with Bootstrap 5 Theme -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    const visitorNameInput = document.getElementsByName('visitor_name')[0];
    const guardianSelect = $('#guardian_id');
    const teacherSelect = $('#teacher_id');

    // Guardian Search AJAX
    guardianSelect.select2({
        theme: 'bootstrap-5',
        placeholder: 'Search Guardian by name, phone, or email...',
        allowClear: true,
        ajax: {
            url: "{{ route('admin.front-office.appointments.search-guardians') }}",
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term
                };
            },
            processResults: function (data) {
                return {
                    results: data
                };
            },
            cache: true
        },
        minimumInputLength: 2
    }).on('select2:select', function(e) {
        // Auto populate visitor name from guardian name
        const fullText = e.params.data.text;
        const namePart = fullText.split('(')[0].trim();
        if (namePart) {
            visitorNameInput.value = namePart;
        }
    });

    // Teacher Search AJAX
    teacherSelect.select2({
        theme: 'bootstrap-5',
        placeholder: 'Search Employee...',
        allowClear: true,
        ajax: {
            url: "{{ route('admin.front-office.appointments.search-teachers') }}",
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term
                };
            },
            processResults: function (data) {
                return {
                    results: data
                };
            },
            cache: true
        },
        minimumInputLength: 2
    });
});
</script>
@endsection
