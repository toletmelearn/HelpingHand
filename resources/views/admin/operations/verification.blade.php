@extends('layouts.admin')

@section('title', 'Environment Verification')

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
    .status-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
    }
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('operations.dashboard') }}">Operations</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Verification</li>
                    </ol>
                </nav>
                <h3 class="fw-bold text-dark"><i class="bi bi-check-all text-danger me-2"></i> Environment Verification Center</h3>
                <p class="text-muted">Perform comprehensive environment check audits across services, PHP constraints, storage permissions, and active configurations.</p>
            </div>
            <div>
                <form action="{{ route('operations.verification.run') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-primary"><i class="bi bi-play-circle me-1"></i> Trigger One-Click Audit</button>
                </form>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card glass-card border-0 mb-4">
        <div class="card-header bg-light border-0 p-3">
            <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-activity me-1 text-primary"></i> Detailed Environment Readiness Report</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 25%;">Verification Check</th>
                            <th style="width: 15%;">Status</th>
                            <th style="width: 40%;">Description & Diagnostics</th>
                            <th style="width: 20%;">Execution Metadata</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($results as $key => $check)
                            <tr>
                                <td>
                                    <strong class="text-dark">{{ $check['name'] }}</strong>
                                    <code class="d-block small text-muted">{{ $key }}</code>
                                </td>
                                <td>
                                    @if($check['status'] === 'success')
                                        <span class="badge bg-success-subtle text-success border border-success px-2 py-1">
                                            <span class="status-dot bg-success me-1"></span> SUCCESS
                                        </span>
                                    @elseif($check['status'] === 'warning')
                                        <span class="badge bg-warning-subtle text-warning border border-warning px-2 py-1">
                                            <span class="status-dot bg-warning me-1"></span> WARNING
                                        </span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1">
                                            <span class="status-dot bg-danger me-1"></span> CRITICAL
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-muted small">{{ $check['message'] }}</span>
                                </td>
                                <td>
                                    @if(!empty($check['meta']))
                                        @foreach($check['meta'] as $metaKey => $metaValue)
                                            <span class="badge bg-light text-dark border me-1 small mb-1" style="font-size: 0.75rem;">
                                                {{ $metaKey }}: {{ is_array($metaValue) ? implode(', ', $metaValue) : $metaValue }}
                                            </span>
                                        @endforeach
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
