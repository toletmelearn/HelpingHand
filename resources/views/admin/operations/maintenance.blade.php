@extends('layouts.admin')

@section('title', 'Maintenance Mode Settings')

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
                    <li class="breadcrumb-item active" aria-current="page">Maintenance Mode</li>
                </ol>
            </nav>
            <h3 class="fw-bold text-dark"><i class="bi bi-shield-slash text-danger me-2"></i> Maintenance & Downtime Control</h3>
            <p class="text-muted">Take the HelpingHand platform offline for upgrades, broadcast notifications to active sessions, and configure auto-restore countdowns.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8 col-12 mx-auto">
            <div class="card glass-card border-0 p-4">
                <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold mb-1 text-dark">Platform Status</h5>
                        <p class="text-muted small mb-0">When offline, only administrators using bypass tokens can log in.</p>
                    </div>
                    <div>
                        <span class="badge bg-{{ $isEnabled ? 'danger' : 'success' }} text-uppercase px-3 py-2 fs-6">
                            {{ $isEnabled ? 'Offline (Maintenance)' : 'Online / Live' }}
                        </span>
                    </div>
                </div>

                <hr>

                <!-- Maintenance Form -->
                <form action="{{ route('operations.maintenance.toggle') }}" method="POST">
                    @csrf
                    
                    @if(!$isEnabled)
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small">Broadcast Downtime Message</label>
                            <textarea name="message" class="form-control" rows="3" required>{{ $message }}</textarea>
                            <small class="text-muted">This message will be shown on the screen to public visitors.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small">Estimated Duration (Hours)</label>
                            <select name="countdown_hours" class="form-select w-25">
                                <option value="1">1 Hour</option>
                                <option value="2" selected>2 Hours</option>
                                <option value="4">4 Hours</option>
                                <option value="8">8 Hours</option>
                                <option value="24">24 Hours</option>
                            </select>
                        </div>

                        <div class="alert alert-danger small border-0 bg-danger-subtle text-danger-emphasis mb-4">
                            <i class="bi bi-exclamation-octagon me-1"></i>
                            <strong>Important:</strong> Enabling this will log out all current parent and teacher sessions! Administrators can access the bypass URL route at: <code>{{ url('/') }}/erp-override</code>.
                        </div>
                    @else
                        <div class="alert alert-info small border-0 bg-info-subtle text-info-emphasis mb-4">
                            <i class="bi bi-info-circle me-1"></i>
                            The system is currently undergoing maintenance. Click the button below to restore normal application routes.
                        </div>
                    @endif

                    <button type="submit" class="btn btn-{{ $isEnabled ? 'success' : 'danger' }} w-100 py-2 fw-bold">
                        @if($isEnabled)
                            <i class="bi bi-play-circle me-1"></i> Disable Maintenance Mode (Go Live)
                        @else
                            <i class="bi bi-shield-exclamation me-1"></i> Enable Maintenance Mode (Go Offline)
                        @endif
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
