@extends('layouts.admin')

@section('title', 'Edit Gate Pass')

@section('content')
<div class="container-fluid py-4">
    <!-- Breadcrumb / Header -->
    <div class="mb-4">
        <a href="{{ route('admin.front-office.gate-passes.index') }}" class="btn btn-sm btn-outline-secondary me-2">
            <i class="bi bi-arrow-left"></i> Back to Pass List
        </a>
        <h1 class="h3 d-inline-block align-middle mb-0 mt-2 mt-md-0">Edit Gate Pass details</h1>
    </div>

    @if($errors->any())
        <div class="alert alert-danger shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i>
            <strong>Error!</strong> Please resolve validation errors below.
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm bg-white">
        <div class="card-body">
            <form action="{{ route('admin.front-office.gate-passes.update', $pass->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <!-- Pass Type -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Gate Pass Type <span class="text-danger">*</span></label>
                        <select name="pass_type" id="pass_type" class="form-select" required>
                            <option value="student" {{ old('pass_type', $pass->pass_type) === 'student' ? 'selected' : '' }}>Student Exit Pass</option>
                            <option value="staff" {{ old('pass_type', $pass->pass_type) === 'staff' ? 'selected' : '' }}>Staff Exit Pass</option>
                            <option value="visitor" {{ old('pass_type', $pass->pass_type) === 'visitor' ? 'selected' : '' }}>Visitor/Visitor Vehicle Exit Pass</option>
                            <option value="vehicle" {{ old('pass_type', $pass->pass_type) === 'vehicle' ? 'selected' : '' }}>General School Vehicle Pass</option>
                        </select>
                    </div>

                    <!-- Holder Name -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Pass Holder Name <span class="text-danger">*</span></label>
                        <input type="text" name="holder_name" id="holder_name" class="form-control" value="{{ old('holder_name', $pass->holder_name) }}" required>
                    </div>

                    <!-- Student Select (Shown if student type) -->
                    <div class="col-md-6 {{ old('pass_type', $pass->pass_type) === 'student' ? '' : 'd-none' }}" id="student_select_container">
                        <label class="form-label fw-bold">Select Student Profile <span class="text-danger">*</span></label>
                        <select name="student_id" id="student_id" class="form-control select2">
                            @if(isset($selectedStudent) && $selectedStudent)
                                <option value="{{ $selectedStudent->id }}" selected>
                                    {{ $selectedStudent->name }} (Adm: {{ $selectedStudent->admission_no }}, Class: {{ $selectedStudent->schoolClass ? $selectedStudent->schoolClass->name : 'N/A' }}, Father: {{ $selectedStudent->father_name }})
                                </option>
                            @else
                                <option value="">Search Student...</option>
                            @endif
                        </select>
                    </div>

                    <!-- Staff Select (Shown if staff type) -->
                    <div class="col-md-6 {{ old('pass_type', $pass->pass_type) === 'staff' ? '' : 'd-none' }}" id="staff_select_container">
                        <label class="form-label fw-bold">Select Staff Member <span class="text-danger">*</span></label>
                        <select name="user_id" id="user_id" class="form-control select2">
                            @if(isset($selectedStaff) && $selectedStaff)
                                <option value="{{ $selectedStaff->id }}" selected>
                                    {{ $selectedStaff->name }} (Email: {{ $selectedStaff->email }})
                                </option>
                            @else
                                <option value="">Search Staff...</option>
                            @endif
                        </select>
                    </div>

                    <!-- Vehicle No -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Vehicle Registration Number (if applicable)</label>
                        <input type="text" name="vehicle_no" class="form-control" value="{{ old('vehicle_no', $pass->vehicle_no) }}">
                    </div>

                    <!-- Designated Exit Gate -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Designated Exit Gate <span class="text-danger">*</span></label>
                        <select name="exit_gate" class="form-select" required>
                            <option value="Gate 1" {{ old('exit_gate', $pass->exit_gate) === 'Gate 1' ? 'selected' : '' }}>Gate 1 (Primary Wing)</option>
                            <option value="Gate 2" {{ old('exit_gate', $pass->exit_gate) === 'Gate 2' ? 'selected' : '' }}>Gate 2 (Senior Wing)</option>
                            <option value="Main Gate" {{ old('exit_gate', $pass->exit_gate) === 'Main Gate' || is_null($pass->exit_gate) ? 'selected' : '' }}>Main Gate</option>
                            <option value="Hostel Gate" {{ old('exit_gate', $pass->exit_gate) === 'Hostel Gate' ? 'selected' : '' }}>Hostel Gate</option>
                        </select>
                    </div>

                    <!-- Request Date -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Release Date <span class="text-danger">*</span></label>
                        <input type="date" name="request_date" class="form-control" value="{{ old('request_date', $pass->request_date ? $pass->request_date->format('Y-m-d') : '') }}" required>
                    </div>

                    <!-- Departure Time -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Departure Time <span class="text-danger">*</span></label>
                        <input type="time" name="departure_time" class="form-control" value="{{ old('departure_time', $pass->departure_time) }}" required>
                    </div>

                    <!-- Status -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Pass Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-select" required>
                            <option value="pending" {{ old('status', $pass->status) === 'pending' ? 'selected' : '' }}>Pending Approval</option>
                            <option value="approved" {{ old('status', $pass->status) === 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="active" {{ old('status', $pass->status) === 'active' ? 'selected' : '' }}>Active (Checked Out)</option>
                            <option value="completed" {{ old('status', $pass->status) === 'completed' ? 'selected' : '' }}>Completed (Returned)</option>
                        </select>
                    </div>

                    <!-- Purpose -->
                    <div class="col-12">
                        <label class="form-label fw-bold">Purpose / Exit Reason <span class="text-danger">*</span></label>
                        <textarea name="purpose" class="form-control" rows="3" required>{{ old('purpose', $pass->purpose) }}</textarea>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary btn-lg px-5">Update Gate Pass</button>
                    <a href="{{ route('admin.front-office.gate-passes.index') }}" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

<!-- Include Select2 CSS and Bootstrap 5 theme -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passTypeSelect = document.getElementById('pass_type');
    const holderNameInput = document.getElementById('holder_name');
    const studentContainer = document.getElementById('student_select_container');
    const studentSelect = $('#student_id');
    const staffContainer = document.getElementById('staff_select_container');
    const staffSelect = $('#user_id');

    function toggleFields(isInitialLoad = false) {
        const val = passTypeSelect.value;
        if (val === 'student') {
            studentContainer.classList.remove('d-none');
            studentSelect.attr('required', 'required');
            staffContainer.classList.add('d-none');
            staffSelect.removeAttr('required');
            if (!isInitialLoad) {
                staffSelect.val(null).trigger('change');
            }
        } else if (val === 'staff') {
            staffContainer.classList.remove('d-none');
            staffSelect.attr('required', 'required');
            studentContainer.classList.add('d-none');
            studentSelect.removeAttr('required');
            if (!isInitialLoad) {
                studentSelect.val(null).trigger('change');
            }
        } else {
            studentContainer.classList.add('d-none');
            studentSelect.removeAttr('required');
            staffContainer.classList.add('d-none');
            staffSelect.removeAttr('required');
            if (!isInitialLoad) {
                studentSelect.val(null).trigger('change');
                staffSelect.val(null).trigger('change');
            }
        }
    }

    // Initialize Select2 with AJAX
    studentSelect.select2({
        theme: 'bootstrap-5',
        placeholder: 'Search Student by name, admission number, or Aadhaar...',
        allowClear: true,
        ajax: {
            url: "{{ route('admin.front-office.students.ajax-search') }}",
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
        holderNameInput.value = e.params.data.name;
    });

    staffSelect.select2({
        theme: 'bootstrap-5',
        placeholder: 'Search Staff member...',
        allowClear: true,
        ajax: {
            url: "{{ route('admin.front-office.staff.ajax-search') }}",
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
        minimumInputLength: 1
    }).on('select2:select', function(e) {
        holderNameInput.value = e.params.data.name;
    });

    passTypeSelect.addEventListener('change', function() {
        toggleFields();
    });

    // Run on load with parameter to prevent clearing active selections
    toggleFields(true);
});
</script>
@endsection
