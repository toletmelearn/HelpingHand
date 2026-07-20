@extends('layouts.admin')

@section('title', 'License & Subscription')

@section('content')
<style>
    .glass-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 12px;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.06);
    }
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('operations.dashboard') }}">Operations</a></li>
                    <li class="breadcrumb-item active" aria-current="page">License & SaaS</li>
                </ol>
            </nav>
            <h3 class="fw-bold text-dark"><i class="bi bi-card-list text-success me-2"></i> SaaS Subscription Center</h3>
            <p class="text-muted">Manage HelpingHand ERP software licensing agreements, monitor school storage quotas, student capacities, and API requests.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row g-4">
        <!-- Details Card -->
        <div class="col-lg-8 col-12">
            <div class="card glass-card border-0 p-4 mb-4">
                <h5 class="fw-bold text-dark mb-4"><i class="bi bi-info-circle text-primary me-1"></i> Active License Status</h5>
                
                <div class="row g-3 mb-4">
                    <div class="col-md-6 col-12">
                        <div class="p-3 border rounded bg-light-subtle">
                            <span class="text-muted small d-block">PLAN LEVEL</span>
                            <strong class="text-dark fs-5">{{ $plan }}</strong>
                        </div>
                    </div>
                    <div class="col-md-6 col-12">
                        <div class="p-3 border rounded bg-light-subtle">
                            <span class="text-muted small d-block">EXPIRATION DATE</span>
                            <strong class="text-dark fs-5">{{ $expiry }}</strong>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold text-dark mb-3">Resource Allocation Audit</h6>
                <div class="row g-3">
                    <!-- Student Capacity -->
                    <div class="col-md-4 col-12">
                        <div class="p-3 border rounded">
                            <span class="text-muted small d-block">STUDENT CAPACITY</span>
                            <strong class="text-dark fs-5">{{ \App\Models\Student::count() }} / {{ $studentCapacity }}</strong>
                            <div class="progress mt-2" style="height: 6px;">
                                @php
                                    $studentPercent = $studentCapacity > 0 ? (\App\Models\Student::count() / (int)$studentCapacity) * 100 : 0;
                                @endphp
                                <div class="progress-bar bg-success" style="width: {{ $studentPercent }}%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Storage allocation -->
                    <div class="col-md-4 col-12">
                        <div class="p-3 border rounded">
                            <span class="text-muted small d-block">DISK QUOTA STORAGE</span>
                            <strong class="text-dark fs-5">{{ $storageUsed }} GB / {{ $storageLimit }} GB</strong>
                            <div class="progress mt-2" style="height: 6px;">
                                @php
                                    $storagePercent = $storageLimit > 0 ? ($storageUsed / (float)$storageLimit) * 100 : 0;
                                @endphp
                                <div class="progress-bar bg-primary" style="width: {{ $storagePercent }}%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- API counts -->
                    <div class="col-md-4 col-12">
                        <div class="p-3 border rounded">
                            <span class="text-muted small d-block">API USAGE (MONTHLY)</span>
                            <strong class="text-dark fs-5">{{ $apiUsage }} / 50,000</strong>
                            <div class="progress mt-2" style="height: 6px;">
                                <div class="progress-bar bg-info" style="width: {{ ($apiUsage / 50000) * 100 }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activate license key -->
        <div class="col-lg-4 col-12">
            <div class="card glass-card border-0 p-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-key text-primary me-1"></i> Update License Key</h5>
                <form action="{{ route('operations.license.activate') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small">Subscription License Key</label>
                        <input type="text" name="license_key" class="form-control text-center font-monospace" placeholder="XXXX-XXXX-XXXX-XXXX" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-shield-check"></i> Activate License</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
