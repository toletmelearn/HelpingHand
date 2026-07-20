@extends('layouts.admin')

@section('title', 'Guest Check-In')

@section('content')
<div class="container-fluid py-4">
    <!-- Breadcrumb / Header -->
    <div class="mb-4">
        <a href="{{ route('admin.front-office.visitors.index') }}" class="btn btn-sm btn-outline-secondary me-2">
            <i class="bi bi-arrow-left"></i> Back to Logs
        </a>
        <h1 class="h3 d-inline-block align-middle mb-0 mt-2 mt-md-0">Guest Check-In Registration</h1>
    </div>

    @if(session('blacklist_warning'))
        <div class="alert alert-danger shadow-sm mb-4" role="alert">
            <i class="bi bi-shield-slash-fill me-2 fs-5"></i>
            <strong>Blacklisted Phone Number Alert!</strong> {{ session('blacklist_warning') }}
        </div>
    @endif

    <div class="row g-4">
        <!-- Form Column -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm bg-white">
                <div class="card-body">
                    <form action="{{ route('admin.front-office.visitors.store') }}" method="POST" id="checkin-form">
                        @csrf
                        <input type="hidden" name="photo" id="photo_data">

                        <div class="row g-3">
                            <!-- Visitor Name -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Visitor Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="visitor_name" class="form-control" placeholder="Enter guest name" value="{{ old('visitor_name') }}" required>
                            </div>

                            <!-- Mobile Phone -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Mobile Phone <span class="text-danger">*</span></label>
                                <input type="text" name="phone" class="form-control" placeholder="Mobile number" value="{{ old('phone') }}" required>
                            </div>

                            <!-- Purpose -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Purpose of Visit <span class="text-danger">*</span></label>
                                <input type="text" name="purpose" class="form-control" placeholder="e.g., Parent Teacher Meet, Vendor Inquiry" value="{{ old('purpose') }}" required>
                            </div>

                            <!-- Department -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Department</label>
                                <input type="text" name="department" class="form-control" placeholder="e.g., Principal Office, Admin Office" value="{{ old('department') }}">
                            </div>

                            <!-- Host Staff -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Staff Member to Meet</label>
                                <select name="host_user_id" class="form-select">
                                    <option value="">Select School Employee</option>
                                    @foreach($hosts as $host)
                                        <option value="{{ $host->id }}" {{ old('host_user_id') == $host->id ? 'selected' : '' }}>
                                            {{ $host->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Vehicle No -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Vehicle Number (if any)</label>
                                <input type="text" name="vehicle_no" class="form-control" placeholder="e.g., MH-12-AB-1234" value="{{ old('vehicle_no') }}">
                            </div>

                            <!-- ID Proof Type -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">ID Proof Type</label>
                                <select name="id_proof_type" class="form-select">
                                    <option value="">Select ID Proof</option>
                                    <option value="aadhaar" {{ old('id_proof_type') === 'aadhaar' ? 'selected' : '' }}>Aadhaar Card</option>
                                    <option value="pan" {{ old('id_proof_type') === 'pan' ? 'selected' : '' }}>PAN Card</option>
                                    <option value="dl" {{ old('id_proof_type') === 'dl' ? 'selected' : '' }}>Driving License</option>
                                    <option value="voter" {{ old('id_proof_type') === 'voter' ? 'selected' : '' }}>Voter ID Card</option>
                                </select>
                            </div>

                            <!-- ID Proof Number -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">ID Number</label>
                                <input type="text" name="id_proof_number" class="form-control" placeholder="Enter ID serial number" value="{{ old('id_proof_number') }}">
                            </div>

                            <!-- Security Checklist -->
                            <div class="col-12">
                                <div class="form-check form-switch p-3 bg-light rounded border">
                                    <input type="checkbox" name="is_emergency" value="1" class="form-check-input" id="is_emergency" {{ old('is_emergency') ? 'checked' : '' }}>
                                    <label class="form-check-label fw-bold text-warning-emphasis" for="is_emergency">Mark as EMERGENCY Visitor Entry (High Priority Alert)</label>
                                </div>
                            </div>

                            <!-- Remarks -->
                            <div class="col-12">
                                <label class="form-label fw-bold">Remarks / Baggage Details</label>
                                <textarea name="remarks" class="form-control" rows="3" placeholder="List items carried (laptops, cameras) or notes...">{{ old('remarks') }}</textarea>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-primary btn-lg px-5">Check-In Guest</button>
                            <a href="{{ route('admin.front-office.visitors.index') }}" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Camera Capture Column -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-white h-100">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h5 class="fw-bold mb-0">Visitor Photo Capture</h5>
                </div>
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <!-- Live video container -->
                    <div id="video-container" class="w-100 border rounded bg-dark position-relative overflow-hidden mb-3" style="aspect-ratio: 4/3;">
                        <video id="webcam" class="w-100 h-100" autoplay playsinline></video>
                        <canvas id="photo-preview-canvas" class="w-100 h-100 position-absolute top-0 start-0 d-none"></canvas>
                        
                        <!-- Video Placeholder -->
                        <div id="camera-placeholder" class="position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center text-white">
                            <i class="bi bi-camera fs-1 mb-2 text-muted"></i>
                            <button type="button" class="btn btn-sm btn-outline-light px-3" id="start-camera">Enable Camera</button>
                        </div>
                    </div>

                    <!-- Capture controls -->
                    <div class="w-100 text-center">
                        <button type="button" class="btn btn-primary w-100 mb-2 d-none" id="capture-btn">
                            <i class="bi bi-camera-fill me-1"></i> Capture Photo
                        </button>
                        <button type="button" class="btn btn-outline-secondary w-100 mb-2 d-none" id="retake-btn">
                            <i class="bi bi-arrow-clockwise me-1"></i> Retake Photo
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const video = document.getElementById('webcam');
    const canvas = document.getElementById('photo-preview-canvas');
    const placeholder = document.getElementById('camera-placeholder');
    const startCamBtn = document.getElementById('start-camera');
    const captureBtn = document.getElementById('capture-btn');
    const retakeBtn = document.getElementById('retake-btn');
    const photoDataField = document.getElementById('photo_data');

    let stream = null;

    startCamBtn.addEventListener('click', async function() {
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            video.srcObject = stream;
            placeholder.classList.add('d-none');
            captureBtn.classList.remove('d-none');
        } catch (err) {
            alert('Camera could not be accessed. Ensure permissions are granted.');
            console.error(err);
        }
    });

    captureBtn.addEventListener('click', function() {
        const context = canvas.getContext('2d');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        
        // Draw current video frame to canvas
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        // Save image string to hidden input
        const base64Image = canvas.toDataURL('image/jpeg');
        photoDataField.value = base64Image;

        // UI toggles
        canvas.classList.remove('d-none');
        captureBtn.classList.add('d-none');
        retakeBtn.classList.remove('d-none');
    });

    retakeBtn.addEventListener('click', function() {
        canvas.classList.add('d-none');
        photoDataField.value = '';
        captureBtn.classList.remove('d-none');
        retakeBtn.classList.add('d-none');
    });
});
</script>
@endsection
