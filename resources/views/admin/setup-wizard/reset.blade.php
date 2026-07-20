@extends('layouts.setup')

@section('title', 'Reset School Setup')

@section('content')

    <!-- Flash Messages -->
    @if(session('error'))
        <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <div>{{ session('error') }}</div>
        </div>
    @endif
    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <div>{{ session('success') }}</div>
        </div>
    @endif

    <div>
        <div class="text-center mb-4 text-danger">
            <i class="bi bi-exclamation-octagon-fill text-danger" style="font-size: 3rem;"></i>
            <h3 class="fw-bold mt-2">Reset School Setup</h3>
            <span class="badge bg-danger px-3 py-2 mt-1">Super Admin Access Only</span>
        </div>

        <div class="alert alert-warning border-0 shadow-sm mb-4" role="alert" style="border-radius: 12px;">
            <h5 class="fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-1"></i> Critical Warning</h5>
            <p class="mb-0" style="font-size: 0.9rem;">
                This action will completely clear all school profiles, current academic sessions, grades/classes, active sections, and core curriculum subjects. 
                You will be redirected to Step 1 of the Setup Wizard to re-configure the school.
            </p>
        </div>

        <p class="text-muted mb-4" style="font-size: 0.9rem;">
            To protect your database, the system will check for existing teacher directories, student rosters, or financial transactions. If any are found, the database will block the reset to prevent data corruption.
        </p>

        <form action="{{ route('admin.setup-wizard.reset.perform') }}" method="POST">
            @csrf
            
            <div class="form-check mb-4 p-0">
                <div class="class-grid-card shadow-sm d-flex align-items-center @error('confirm_reset') border-danger @enderror" style="border-radius: 12px;">
                    <input class="form-check-input ms-1 @error('confirm_reset') is-invalid @enderror" type="checkbox" name="confirm_reset" id="confirm_reset" value="1">
                    <label class="form-check-label fw-bold text-dark ms-3" for="confirm_reset" style="font-size: 0.9rem; cursor: pointer;">
                        I understand this is destructive and want to reset the school configurations.
                    </label>
                </div>
                @error('confirm_reset') <div class="invalid-feedback d-block mt-2 ms-2">{{ $message }}</div> @enderror
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary px-4 py-2" style="border-radius: 10px;">
                    <i class="bi bi-x-lg me-1"></i> Cancel & Return
                </a>
                <button type="submit" class="btn btn-danger px-4 py-2 fw-bold" style="border-radius: 10px; background-color: #dc3545;">
                    Reset Configuration <i class="bi bi-trash-fill ms-1"></i>
                </button>
            </div>
        </form>
    </div>

@endsection
