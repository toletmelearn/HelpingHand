@extends('layouts.admin')

@section('title', 'Student Statement - ' . $student->name)

@section('content')
<div class="container-fluid">
    <!-- Header Page Title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Financial Statement: {{ $student->name }}</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.financial-accounts.index') }}">Financial Accounts</a></li>
                        <li class="breadcrumb-item active">Statement</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Section 1: Student Summary & Statement Actions -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title mb-3"><i class="fas fa-user-graduate text-primary me-1"></i> Student Summary</h5>
                    <div class="row">
                        <div class="col-sm-6 mb-2">
                            <span class="text-muted d-block">Student Name</span>
                            <strong>{{ $student->name }}</strong>
                        </div>
                        <div class="col-sm-6 mb-2">
                            <span class="text-muted d-block">Admission No</span>
                            <strong>{{ $student->admission_no ?: 'N/A' }}</strong>
                        </div>
                        <div class="col-sm-6 mb-2">
                            <span class="text-muted d-block">Class / Section</span>
                            <strong>{{ $student->schoolClass->name ?? $student->class }} (Section: {{ $student->section ?: 'N/A' }})</strong>
                        </div>
                        <div class="col-sm-6 mb-2">
                            <span class="text-muted d-block">Financial Account No</span>
                            <span class="badge bg-soft-info text-info font-size-13 fw-bold">{{ $account->account_no }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <h5 class="card-title mb-3"><i class="fas fa-cog text-primary me-1"></i> Statement Actions</h5>
                        <p class="text-muted small">Export statement or post manual credits/debits directly to this student's account ledger.</p>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-warning text-dark" data-bs-toggle="modal" data-bs-target="#adjustmentModal">
                            <i class="fas fa-plus-minus me-1"></i> Post Adjustment
                        </button>
                        <div class="btn-group w-100">
                            <a href="{{ route('admin.financial-accounts.export.pdf', array_merge(['id' => $student->id], $filters)) }}" class="btn btn-outline-danger">
                                <i class="fas fa-file-pdf me-1"></i> PDF
                            </a>
                            <a href="{{ route('admin.financial-accounts.export.excel', array_merge(['id' => $student->id], $filters)) }}" class="btn btn-outline-success">
                                <i class="fas fa-file-csv me-1"></i> Excel
                            </a>
                            <button type="button" onclick="window.print()" class="btn btn-outline-info">
                                <i class="fas fa-print me-1"></i> Print
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 2: Financial Summary Cards -->
    <div class="row mb-4">
        <!-- Card 1: Opening Balance -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-body bg-light h-100 p-3">
                <span class="text-muted small text-uppercase fw-bold">Opening Balance</span>
                <h4 class="mt-2 mb-0">₹{{ number_format($cards['opening_balance'], 2) }}</h4>
            </div>
        </div>
        <!-- Card 2: Total Charges -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-body bg-light h-100 p-3">
                <span class="text-muted small text-uppercase fw-bold">Total Charges</span>
                <h4 class="mt-2 mb-0 text-primary">₹{{ number_format($cards['total_charges'], 2) }}</h4>
            </div>
        </div>
        <!-- Card 3: Total Discounts -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-body bg-light h-100 p-3">
                <span class="text-muted small text-uppercase fw-bold">Total Discounts</span>
                <h4 class="mt-2 mb-0 text-success">₹{{ number_format($cards['total_discounts'], 2) }}</h4>
            </div>
        </div>
        <!-- Card 4: Total Scholarships -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-body bg-light h-100 p-3">
                <span class="text-muted small text-uppercase fw-bold">Scholarships</span>
                <h4 class="mt-2 mb-0 text-info">₹{{ number_format($cards['total_scholarships'], 2) }}</h4>
            </div>
        </div>
        <!-- Card 5: Late Fees -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-body bg-light h-100 p-3">
                <span class="text-muted small text-uppercase fw-bold">Total Late Fees</span>
                <h4 class="mt-2 mb-0 text-warning">₹{{ number_format($cards['total_late_fees'], 2) }}</h4>
            </div>
        </div>
        <!-- Card 6: Payments -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-body bg-light h-100 p-3">
                <span class="text-muted small text-uppercase fw-bold">Total Payments</span>
                <h4 class="mt-2 mb-0 text-success">₹{{ number_format($cards['total_payments'], 2) }}</h4>
            </div>
        </div>
        <!-- Card 7: Refunds -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-body bg-light h-100 p-3">
                <span class="text-muted small text-uppercase fw-bold">Total Refunds</span>
                <h4 class="mt-2 mb-0 text-danger">₹{{ number_format($cards['total_refunds'], 2) }}</h4>
            </div>
        </div>
        <!-- Card 8: Outstanding Balance -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-body h-100 p-3 {{ $cards['outstanding_balance'] > 0 ? 'bg-soft-danger text-danger border-danger' : 'bg-soft-success text-success border-success' }}">
                <span class="small text-uppercase fw-bold">Outstanding Balance</span>
                <h4 class="mt-2 mb-0 fw-bold">₹{{ number_format($cards['outstanding_balance'], 2) }}</h4>
            </div>
        </div>
    </div>

    <!-- Section 3: Filters Panel -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3"><i class="fas fa-filter text-primary me-1"></i> Filter Statement</h5>
            <form method="GET" action="{{ route('admin.financial-accounts.show', $student->id) }}" class="row g-3">
                <div class="col-md-2">
                    <label for="academic_session" class="form-label">Academic Session</label>
                    <select name="academic_session" id="academic_session" class="form-select">
                        <option value="">All Sessions</option>
                        @foreach($sessions as $session)
                            <option value="{{ $session }}" {{ request('academic_session') == $session ? 'selected' : '' }}>
                                {{ $session }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2.5">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" value="{{ request('start_date') }}">
                </div>
                <div class="col-md-2.5">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" name="end_date" id="end_date" class="form-control" value="{{ request('end_date') }}">
                </div>
                <div class="col-md-2.5">
                    <label for="voucher_type" class="form-label">Voucher Type</label>
                    <select name="voucher_type" id="voucher_type" class="form-select">
                        <option value="">All Types</option>
                        @foreach($voucherTypes as $vt)
                            <option value="{{ $vt }}" {{ request('voucher_type') == $vt ? 'selected' : '' }}>
                                {{ ucwords(str_replace('_', ' ', $vt)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2.5">
                    <label for="fee_head" class="form-label">Fee Head</label>
                    <select name="fee_head" id="fee_head" class="form-select">
                        <option value="">All Heads</option>
                        @foreach($feeHeads as $head)
                            <option value="{{ $head->id }}" {{ request('fee_head') == $head->id ? 'selected' : '' }}>
                                {{ $head->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 d-flex justify-content-end mt-3">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i> Apply Filters
                    </button>
                    <a href="{{ route('admin.financial-accounts.show', $student->id) }}" class="btn btn-outline-secondary">
                        <i class="fas fa-undo me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Section 4: Ledger Timeline Table -->
    <div class="card">
        <div class="card-body">
            <h5 class="card-title mb-3"><i class="fas fa-list text-primary me-1"></i> Ledger Timeline</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-light text-center">
                        <tr>
                            <th>Date</th>
                            <th>Voucher Type</th>
                            <th>Reference No</th>
                            <th>Description</th>
                            <th>Debit (₹)</th>
                            <th>Credit (₹)</th>
                            <th>Running Balance (₹)</th>
                            <th>Created By</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ledger as $entry)
                            @php
                                $rBal = $runningBalances[$entry->id] ?? 0.00;
                            @endphp
                            <tr>
                                <td class="text-center">{{ $entry->date ? $entry->date->format('Y-m-d') : 'N/A' }}</td>
                                <td class="text-center">
                                    <span class="badge bg-soft-secondary text-secondary">
                                        {{ ucwords(str_replace('_', ' ', $entry->reference_type)) }}
                                    </span>
                                </td>
                                <td class="text-center font-size-13">{{ $entry->reference_id }}</td>
                                <td>{{ $entry->description }}</td>
                                <td class="text-end text-danger fw-semibold">
                                    {{ $entry->debit > 0 ? number_format($entry->debit, 2) : '-' }}
                                </td>
                                <td class="text-end text-success fw-semibold">
                                    {{ $entry->credit > 0 ? number_format($entry->credit, 2) : '-' }}
                                </td>
                                <td class="text-end fw-bold {{ $rBal > 0 ? 'text-danger' : ($rBal < 0 ? 'text-success' : '') }}">
                                    ₹{{ number_format($rBal, 2) }}
                                </td>
                                <td class="text-center font-size-12">
                                    {{ $entry->auditLogs->first()->user->name ?? 'System' }}
                                </td>
                                <td class="small">{{ $entry->remarks ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center">No ledger entries match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end mt-3">
                {{ $ledger->links() }}
            </div>
        </div>
    </div>
</div>

<!-- Section 5: Manual Adjustment Modal Form -->
<div class="modal fade" id="adjustmentModal" tabindex="-1" aria-labelledby="adjustmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.financial-accounts.adjustment', $student->id) }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="adjustmentModalLabel">Post Ledger Adjustment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Adjustment Type</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="type" id="type_debit" value="debit" checked>
                                <label class="form-check-label text-danger fw-bold" for="type_debit">Debit (Increases Due)</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="type" id="type_credit" value="credit">
                                <label class="form-check-label text-success fw-bold" for="type_credit">Credit (Decreases Due)</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="amount" class="form-label fw-bold">Amount (₹) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" class="form-control" name="amount" id="amount" placeholder="0.00" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label fw-bold">Description / Purpose <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="description" id="description" placeholder="e.g. Library fine, Opening balance carry forward correction" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark"><i class="fas fa-paper-plane me-1"></i> Post Transaction</button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
    @media print {
        body * {
            visibility: hidden;
        }
        .container-fluid, .container-fluid * {
            visibility: visible;
        }
        .container-fluid {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }
        .btn, .btn-group, .modal, .breadcrumb, .page-title-right, form {
            display: none !important;
        }
    }
</style>
@endsection
