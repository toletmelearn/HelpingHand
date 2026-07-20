@extends('layouts.admin')

@section('title', 'Payment Settings')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Payment Settings</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Payment Settings</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">School Payment Configuration</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.settings.payment.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="school_upi_id" class="form-label">School UPI ID</label>
                                    <input type="text" class="form-control" id="school_upi_id" name="school_upi_id" 
                                           value="{{ old('school_upi_id', $settings['school_upi_id']) }}" 
                                           placeholder="schoolname@upi">
                                    <div class="form-text">Enter your school's UPI ID for digital payments</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="qr_image" class="form-label">School QR Code Image</label>
                                    <input type="file" class="form-control" id="qr_image" name="qr_image" accept="image/*">
                                    <div class="form-text">Upload your school's payment QR code image (JPG, PNG, GIF)</div>
                                    
                                    @if($settings['qr_image_path'])
                                        <div class="mt-2">
                                            <label class="form-label">Current QR Code:</label><br>
                                            <img src="{{ asset('storage/' . $settings['qr_image_path']) }}" 
                                                 alt="Current QR Code" style="max-width: 150px; max-height: 150px;">
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Save Settings
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection