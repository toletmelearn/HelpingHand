@extends('layouts.admin')

@section('title', 'ERP Configuration Center')

@section('content')
<style>
    .glass-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 12px;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.08);
    }
    .glass-header {
        background: linear-gradient(135deg, #1e3a8a, #0f172a);
        color: white;
        border-top-left-radius: 12px;
        border-top-right-radius: 12px;
    }
    .nav-tabs .nav-link {
        border: none;
        color: #64748b;
        font-weight: 600;
        padding: 1rem 1.25rem;
    }
    .nav-tabs .nav-link.active {
        color: #0f172a;
        border-bottom: 3px solid #3b82f6;
        background: transparent;
    }
</style>

<div class="container-fluid py-4">
    <!-- Breadcrumbs -->
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">ERP Configuration</li>
                </ol>
            </nav>
            <h3 class="fw-bold text-dark"><i class="bi bi-sliders text-primary me-2"></i> ERP Configuration Center</h3>
            <p class="text-muted">Centralized operations control panel to manage school profiles, payment gateways, and security attributes.</p>
        </div>
    </div>

    <!-- Flash Messages -->
    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <div>{{ session('success') }}</div>
        </div>
    @endif

    <form action="{{ route('operations.settings.update') }}" method="POST">
        @csrf
        
        <div class="card glass-card border-0 mb-4">
            <!-- Settings Tabs Header -->
            <div class="card-header bg-light border-0 p-0">
                <ul class="nav nav-tabs border-0" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="school-tab" data-bs-toggle="tab" data-bs-target="#school" type="button" role="tab" aria-controls="school" aria-selected="true">
                            <i class="bi bi-building me-1"></i> School Profile
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="gateways-tab" data-bs-toggle="tab" data-bs-target="#gateways" type="button" role="tab" aria-controls="gateways" aria-selected="false">
                            <i class="bi bi-credit-card me-1"></i> Billing & Payments
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab" aria-controls="security" aria-selected="false">
                            <i class="bi bi-shield-lock me-1"></i> Security & Session
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Settings Content -->
            <div class="card-body p-4">
                <div class="tab-content" id="settingsTabsContent">
                    <!-- Tab 1: School Profile -->
                    <div class="tab-pane fade show active" id="school" role="tabpanel" aria-labelledby="school-tab">
                        <h5 class="fw-bold mb-3 text-dark">School Profile</h5>
                        <p class="text-muted small mb-4">Update contact information, physical addresses, and communication email channels.</p>

                        <div class="mb-3">
                            <label for="school_name" class="form-label fw-bold">School Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('school_name') is-invalid @enderror" id="school_name" name="school_name" value="{{ old('school_name', $config['school_name']) }}">
                            @error('school_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6 col-12 mb-3">
                                <label for="school_email" class="form-label fw-bold">School Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control @error('school_email') is-invalid @enderror" id="school_email" name="school_email" value="{{ old('school_email', $config['school_email']) }}">
                                @error('school_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 col-12 mb-3">
                                <label for="school_phone" class="form-label fw-bold">School Phone <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('school_phone') is-invalid @enderror" id="school_phone" name="school_phone" value="{{ old('school_phone', $config['school_phone']) }}">
                                @error('school_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="school_address" class="form-label fw-bold">School Address <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('school_address') is-invalid @enderror" id="school_address" name="school_address" rows="3">{{ old('school_address', $config['school_address']) }}</textarea>
                            @error('school_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <!-- Tab 2: Billing & Payments -->
                    <div class="tab-pane fade" id="gateways" role="tabpanel" aria-labelledby="gateways-tab">
                        <h5 class="fw-bold mb-3 text-dark">Stripe Integration Settings</h5>
                        <p class="text-muted small mb-4">Set up Stripe parameters to collect student online tuition fee structure payments.</p>

                        <div class="mb-3">
                            <label for="stripe_mode" class="form-label fw-bold">Stripe Processing Mode</label>
                            <select class="form-select @error('stripe_mode') is-invalid @enderror" id="stripe_mode" name="stripe_mode">
                                <option value="sandbox" {{ old('stripe_mode', $config['stripe_mode']) === 'sandbox' ? 'selected' : '' }}>Sandbox / Test Mode</option>
                                <option value="live" {{ old('stripe_mode', $config['stripe_mode']) === 'live' ? 'selected' : '' }}>Live Mode (Real Transactions)</option>
                            </select>
                            @error('stripe_mode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="stripe_publishable_key" class="form-label fw-bold">Stripe Publishable Key</label>
                            <input type="text" class="form-control @error('stripe_publishable_key') is-invalid @enderror" id="stripe_publishable_key" name="stripe_publishable_key" value="{{ old('stripe_publishable_key', $config['stripe_publishable_key']) }}" placeholder="pk_test_...">
                            @error('stripe_publishable_key') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="stripe_secret_key" class="form-label fw-bold">Stripe Secret Key</label>
                            <input type="password" class="form-control @error('stripe_secret_key') is-invalid @enderror" id="stripe_secret_key" name="stripe_secret_key" value="{{ old('stripe_secret_key', $config['stripe_secret_key']) }}" placeholder="sk_test_...">
                            @error('stripe_secret_key') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <!-- Tab 3: Security & Session -->
                    <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
                        <h5 class="fw-bold mb-3 text-dark">Security Attributes</h5>
                        <p class="text-muted small mb-4">Enforce password policy bounds and web session inactivity timeouts.</p>

                        <div class="mb-3">
                            <label for="password_min_length" class="form-label fw-bold">Minimum Password Length <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('password_min_length') is-invalid @enderror" id="password_min_length" name="password_min_length" value="{{ old('password_min_length', $config['password_min_length']) }}" min="6" max="32">
                            @error('password_min_length') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <small class="text-muted mt-1 d-block">Required length when creating or updating user passwords.</small>
                        </div>

                        <div class="mb-3">
                            <label for="session_timeout_minutes" class="form-label fw-bold">Session Inactivity Timeout (minutes) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('session_timeout_minutes') is-invalid @enderror" id="session_timeout_minutes" name="session_timeout_minutes" value="{{ old('session_timeout_minutes', $config['session_timeout_minutes']) }}" min="15" max="1440">
                            @error('session_timeout_minutes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <small class="text-muted mt-1 d-block">Log out idle administrators automatically after this duration.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Save Actions -->
            <div class="card-footer bg-light p-3 d-flex justify-content-end gap-2 border-0">
                <button type="reset" class="btn btn-outline-secondary">Reset Changes</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold">Save Configuration <i class="bi bi-save ms-1"></i></button>
            </div>
        </div>
    </form>
</div>
@endsection
