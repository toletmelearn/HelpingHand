@extends('layouts.admin')

@section('title', 'Mapping Profiles')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('imports.dashboard') }}">Data Management</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Mapping Profiles</li>
                </ol>
            </nav>
            <h3 class="fw-bold text-dark"><i class="bi bi-file-earmark-diff text-primary me-2"></i> Mapping Profiles</h3>
            <p class="text-muted">Manage pre-saved mapping schemas for various school boards and formats.</p>
        </div>
    </div>

    <!-- Placeholder coming soon style -->
    <div class="card border-0 shadow-sm p-5 text-center">
        <div class="py-5">
            <i class="bi bi-gear-wide-connected text-muted mb-4" style="font-size: 4rem;"></i>
            <h4 class="fw-bold text-dark">Mapping Profiles Engine (P1)</h4>
            <p class="text-muted mx-auto" style="max-width: 500px;">Save customized columns mappings (e.g. CBSE Schema, Delhi branch template) to skip manual assignments on future ingestion files. Coming soon in Phase 4B.</p>
            <a href="{{ route('imports.dashboard') }}" class="btn btn-primary btn-sm px-4">Back to Dashboard</a>
        </div>
    </div>
</div>
@endsection
