@extends('layouts.admin')

@section('title', 'Import Templates')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('imports.dashboard') }}">Data Management</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Import Templates</li>
                </ol>
            </nav>
            <h3 class="fw-bold text-dark"><i class="bi bi-file-earmark-spreadsheet text-primary me-2"></i> Import Templates</h3>
            <p class="text-muted">Manage versioned Excel templates schemas (CBSE, ICSE, Custom Templates).</p>
        </div>
    </div>

    <!-- Placeholder coming soon style -->
    <div class="card border-0 shadow-sm p-5 text-center">
        <div class="py-5">
            <i class="bi bi-file-earmark-lock text-muted mb-4" style="font-size: 4rem;"></i>
            <h4 class="fw-bold text-dark">Templates Versioning Engine (P1)</h4>
            <p class="text-muted mx-auto" style="max-width: 500px;">Configure schema structures, define color codes, and upload custom guidelines sheet attachments. Coming soon in Phase 4B.</p>
            <a href="{{ route('imports.dashboard') }}" class="btn btn-primary btn-sm px-4">Back to Dashboard</a>
        </div>
    </div>
</div>
@endsection
