@extends('layouts.admin')

@section('title', 'Defaulter Dashboard')

@section('content')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');
    
    .dashboard-container {
        font-family: 'Outfit', sans-serif;
        background-color: #f8fafc;
        border-radius: 16px;
    }
    
    .page-title {
        font-weight: 700;
        color: #1e293b;
        letter-spacing: -0.5px;
    }
    
    .card-kpi {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        border-radius: 16px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .card-kpi:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }
    
    .kpi-title {
        font-size: 0.85rem;
        text-transform: uppercase;
        font-weight: 600;
        color: #64748b;
        letter-spacing: 0.5px;
    }
    
    .kpi-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: #0f172a;
    }
    
    .progress-bar-custom {
        height: 8px;
        border-radius: 999px;
    }
    
    .table-premium th {
        background-color: #f1f5f9;
        color: #475569;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        padding: 12px 16px;
    }
    
    .table-premium td {
        padding: 12px 16px;
        border-bottom: 1px solid #e2e8f0;
        color: #334155;
    }
    
    .ageing-card {
        border-left: 5px solid;
        border-radius: 12px;
        padding: 16px;
        background: #ffffff;
        border-top: 1px solid #e2e8f0;
        border-right: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
    }
</style>

<div class="container-fluid py-4 dashboard-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="page-title mb-1"><i class="bi bi-speedometer2 text-danger me-2"></i>Defaulter Management Dashboard</h3>
            <p class="text-muted mb-0">Overview of recovery percentages, aging brackets, top outstanding defaults, and workflow stages.</p>
        </div>
        <div>
            <a href="{{ route('admin.fees.defaulters.index') }}" class="btn btn-danger"><i class="bi bi-exclamation-triangle"></i> Defaulter Registry</a>
        </div>
    </div>

    <!-- KPIs Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card card-kpi p-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="kpi-title">Total Demanded</span>
                    <i class="bi bi-wallet2 text-primary fs-4"></i>
                </div>
                <div class="kpi-value">₹{{ number_format($totalDemand, 2) }}</div>
                <p class="text-muted small mt-2 mb-0">Aggregate fee structure items charged.</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-kpi p-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="kpi-title">Total Collected</span>
                    <i class="bi bi-cash-coin text-success fs-4"></i>
                </div>
                <div class="kpi-value">₹{{ number_format($totalCollected, 2) }}</div>
                <p class="text-muted small mt-2 mb-0">Total posted credits & receipts.</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-kpi p-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="kpi-title">Recovery Percentage</span>
                    <i class="bi bi-percent text-warning fs-4"></i>
                </div>
                <div class="kpi-value fw-bold text-success">{{ $recoveryPercentage }}%</div>
                <div class="progress mt-2 progress-bar-custom bg-light">
                    <div class="progress-bar bg-success" role="progressbar" style="width: {{ $recoveryPercentage }}%"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Ageing Buckets -->
        <div class="col-lg-6">
            <div class="card card-kpi p-4 h-100">
                <h5 class="fw-bold mb-4 border-bottom pb-2"><i class="bi bi-hourglass-split me-1 text-indigo"></i> Dues Ageing Analysis</h5>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="ageing-card" style="border-left-color: #10b981;">
                            <span class="kpi-title d-block mb-1">1 - 30 Days</span>
                            <div class="fs-4 fw-bold text-dark">{{ $ageing['1_30'] }} Students</div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="ageing-card" style="border-left-color: #f59e0b;">
                            <span class="kpi-title d-block mb-1">31 - 60 Days</span>
                            <div class="fs-4 fw-bold text-dark">{{ $ageing['31_60'] }} Students</div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="ageing-card" style="border-left-color: #ef4444;">
                            <span class="kpi-title d-block mb-1">61 - 90 Days</span>
                            <div class="fs-4 fw-bold text-dark">{{ $ageing['61_90'] }} Students</div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="ageing-card" style="border-left-color: #7c3aed;">
                            <span class="kpi-title d-block mb-1">90+ Days</span>
                            <div class="fs-4 fw-bold text-dark">{{ $ageing['90_plus'] }} Students</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Class-wise Defaulters Summary -->
        <div class="col-lg-6">
            <div class="card card-kpi p-4 h-100">
                <h5 class="fw-bold mb-4 border-bottom pb-2"><i class="bi bi-bar-chart-fill me-1 text-primary"></i> Class-wise Defaulters</h5>
                <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                    <table class="table align-middle table-premium mb-0">
                        <thead>
                            <tr>
                                <th>Class</th>
                                <th class="text-center">Count</th>
                                <th class="text-end">Total Outstanding</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($classwise as $row)
                                <tr>
                                    <td><strong>{{ $row->class_name }}</strong></td>
                                    <td class="text-center"><span class="badge bg-secondary">{{ $row->count }}</span></td>
                                    <td class="text-end fw-bold text-danger">₹{{ number_format($row->total_outstanding, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted">No outstanding dues in any class.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Top 10 Defaulters Table -->
    <div class="card card-kpi p-4 mb-4">
        <h5 class="fw-bold mb-4 border-bottom pb-2"><i class="bi bi-person-exclamation me-1 text-danger"></i> Top Defaulters</h5>
        <div class="table-responsive">
            <table class="table table-premium mb-0">
                <thead>
                    <tr>
                        <th>Admission No</th>
                        <th>Student Name</th>
                        <th>Class & Section</th>
                        <th>Current Workflow Stage</th>
                        <th class="text-end">Outstanding Amount</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($topDefaulters as $def)
                        <tr>
                            <td><strong>{{ $def->student->admission_no }}</strong></td>
                            <td>{{ $def->student->name }}</td>
                            <td>{{ $def->student->schoolClass->name ?? 'N/A' }} - {{ $def->student->section->name ?? 'N/A' }}</td>
                            <td>
                                @php
                                    $badgeMap = [
                                        'Reminder' => 'bg-info text-white',
                                        'Phone Call' => 'bg-primary text-white',
                                        'Warning' => 'bg-warning text-dark',
                                        'Principal Notice' => 'bg-dark text-white',
                                        'Exam Restriction' => 'bg-danger text-white',
                                        'Result Hold' => 'bg-danger text-white',
                                        'TC Hold' => 'bg-danger text-white',
                                    ];
                                    $badge = $badgeMap[$def->stage] ?? 'bg-secondary';
                                @endphp
                                <span class="badge {{ $badge }}">{{ $def->stage }}</span>
                            </td>
                            <td class="text-end fw-bold text-danger">₹{{ number_format($def->outstanding_amount, 2) }}</td>
                            <td class="text-center">
                                <a href="{{ route('admin.fees.defaulters.index', ['stage' => $def->stage]) }}" class="btn btn-sm btn-outline-danger">Manage</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No student defaults registered.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
