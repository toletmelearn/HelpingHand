@extends('layouts.admin')

@section('title', 'Financial Year-End Closing')

@section('content')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');
    
    .closing-container {
        font-family: 'Outfit', sans-serif;
        background-color: #f8fafc;
        border-radius: 16px;
    }
    
    .page-title {
        font-weight: 700;
        color: #1e293b;
        letter-spacing: -0.5px;
    }
    
    .card-premium {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    
    .stat-badge {
        font-size: 1.1rem;
        font-weight: 700;
        padding: 8px 16px;
        border-radius: 10px;
    }

    .table-premium th {
        background-color: #f1f5f9;
        color: #475569;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 14px 16px;
    }
    
    .table-premium td {
        padding: 14px 16px;
        border-bottom: 1px solid #e2e8f0;
        color: #334155;
    }
</style>

<div class="container-fluid py-4 closing-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="page-title mb-1"><i class="bi bi-calendar-check-fill text-danger me-2"></i>Financial Year Closing</h3>
            <p class="text-muted mb-0">Freeze previous academic years, archive student ledgers, carry forward arrears/advances as opening balances.</p>
        </div>
    </div>

    <!-- Alerts -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="border-radius: 10px;">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="border-radius: 10px;">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row g-4">
        <!-- Main Actions Panel -->
        <div class="col-lg-5">
            @if($stagedClosing)
                <!-- Active Staging Alert -->
                <div class="card card-premium p-4 border border-warning" style="background-color: #fffbeb;">
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-exclamation-triangle-fill text-warning fs-3 me-3"></i>
                        <div>
                            <h5 class="fw-bold text-warning-emphasis mb-0">Staged Closing In Progress</h5>
                            <span class="text-muted small">Staged from session <strong>{{ $stagedClosing->from_session_code }}</strong> to <strong>{{ $stagedClosing->to_session_code }}</strong></span>
                        </div>
                    </div>
                    
                    <p class="text-dark small mb-3">A staged closing run computes all student outstanding dues and advance payments, and sets them as temporary opening balances. **Please verify the balances before final closure.**</p>
                    
                    <div class="row g-2 mb-4">
                        <div class="col-6">
                            <div class="p-3 bg-white border rounded text-center shadow-sm">
                                <span class="text-muted text-xs d-block text-uppercase">Arrears Carried</span>
                                <strong class="text-danger">₹{{ number_format($stagedClosing->total_balance_carried, 2) }}</strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 bg-white border rounded text-center shadow-sm">
                                <span class="text-muted text-xs d-block text-uppercase">Advances Carried</span>
                                <strong class="text-success">₹{{ number_format($stagedClosing->total_advance_carried, 2) }}</strong>
                            </div>
                        </div>
                        <div class="col-6 mt-2">
                            <div class="p-3 bg-white border rounded text-center shadow-sm">
                                <span class="text-muted text-xs d-block text-uppercase">Scholarships Carried</span>
                                <strong class="text-info">₹{{ number_format($stagedClosing->total_scholarship_carried ?? 0.00, 2) }}</strong>
                            </div>
                        </div>
                        <div class="col-6 mt-2">
                            <div class="p-3 bg-white border rounded text-center shadow-sm">
                                <span class="text-muted text-xs d-block text-uppercase">Refunds Carried</span>
                                <strong class="text-warning">₹{{ number_format($stagedClosing->total_refund_carried ?? 0.00, 2) }}</strong>
                            </div>
                        </div>
                        <div class="col-12 mt-2">
                            <div class="p-2 bg-white border rounded text-center shadow-sm">
                                <span class="text-muted text-xs d-block text-uppercase">Total Students Processed</span>
                                <strong class="text-dark">{{ $stagedClosing->total_students_processed }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <!-- Rollback Form -->
                        <form action="{{ route('admin.fees.year-closing.rollback') }}" method="POST" class="w-50" onsubmit="return confirm('Are you sure you want to rollback this staged closing? This will remove all staged opening balances.');">
                            @csrf
                            <input type="hidden" name="closing_id" value="{{ $stagedClosing->id }}">
                            <button type="submit" class="btn btn-outline-secondary w-100 py-2.5 small"><i class="bi bi-arrow-counterclockwise me-1"></i> Rollback</button>
                        </form>
                        
                        <!-- Confirm Form -->
                        <form action="{{ route('admin.fees.year-closing.confirm') }}" method="POST" class="w-50" onsubmit="return confirm('CAUTION: Finalizing year closing will freeze the previous session permanently. Edits will be disabled. Proceed?');">
                            @csrf
                            <input type="hidden" name="closing_id" value="{{ $stagedClosing->id }}">
                            <button type="submit" class="btn btn-danger w-100 py-2.5 fw-bold"><i class="bi bi-check-lg me-1"></i> Final Confirm</button>
                        </form>
                    </div>
                </div>
            @else
                <!-- Initialize Staging Form -->
                <div class="card card-premium p-4">
                    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-play-circle text-danger me-2"></i>Stage Year Closing</h5>
                    <form action="{{ route('admin.fees.year-closing.stage') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="from_session_code" class="form-label small fw-bold text-muted">From Session (To Close)</label>
                            <select name="from_session_code" id="from_session_code" class="form-select" required>
                                <option value="">Select Session...</option>
                                @foreach($activeSessions as $ses)
                                    <option value="{{ $ses->code }}">{{ $ses->name }} (Active)</option>
                                @endforeach
                            </select>
                            <span class="text-muted text-xs">Only active, non-frozen sessions can be closed.</span>
                        </div>

                        <div class="mb-3">
                            <label for="to_session_code" class="form-label small fw-bold text-muted">To Session (New Year)</label>
                            <select name="to_session_code" id="to_session_code" class="form-select" required>
                                <option value="">Select Session...</option>
                                @foreach($allSessions as $ses)
                                    <option value="{{ $ses->code }}">{{ $ses->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="remarks" class="form-label small fw-bold text-muted">Remarks</label>
                            <textarea name="remarks" id="remarks" rows="3" class="form-control" placeholder="Add closure notes..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-danger w-100 py-2.5 fw-bold"><i class="bi bi-rocket-takeoff me-2"></i>Initialize Dry Run (Stage)</button>
                    </form>
                </div>
            @endif
        </div>

        <!-- History Log Panel -->
        <div class="col-lg-7">
            <div class="card card-premium p-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-clock-history text-secondary me-2"></i>Closure Audit Trail</h5>
                <div class="table-responsive">
                    <table class="table table-premium mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>From / To</th>
                                <th>Status</th>
                                <th class="text-end">Arrears</th>
                                <th class="text-end">Advances</th>
                                <th class="text-end">Scholarships</th>
                                <th class="text-end">Refunds</th>
                                <th>Date Confirmed</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($history as $item)
                                <tr>
                                    <td>
                                        <strong>{{ $item->from_session_code }}</strong> <i class="bi bi-arrow-right text-muted mx-1"></i> <strong>{{ $item->to_session_code }}</strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $item->status === 'confirmed' ? 'success' : 'warning' }}">
                                            {{ strtoupper($item->status) }}
                                        </span>
                                    </td>
                                    <td class="text-end fw-bold text-danger">₹{{ number_format($item->total_balance_carried, 2) }}</td>
                                    <td class="text-end fw-bold text-success">₹{{ number_format($item->total_advance_carried, 2) }}</td>
                                    <td class="text-end fw-bold text-info">₹{{ number_format($item->total_scholarship_carried ?? 0.00, 2) }}</td>
                                    <td class="text-end fw-bold text-warning">₹{{ number_format($item->total_refund_carried ?? 0.00, 2) }}</td>
                                    <td class="small text-muted">
                                        {{ $item->confirmed_at ? \Carbon\Carbon::parse($item->confirmed_at)->format('Y-m-d H:i') : '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-archive fs-2"></i>
                                        <p class="mt-2 mb-0">No financial closures have been run yet.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
