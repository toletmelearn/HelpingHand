@extends('layouts.admin')

@section('title', 'Reconciliation Center - Unresolved Records')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between mb-4">
                <h4 class="mb-0">Finance Reconciliation Center</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Reconciliation</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="row mb-3">
        <div class="col-12">
            <ul class="nav nav-pills card-header-pills bg-light p-2 rounded shadow-sm">
                <li class="nav-item">
                    <a class="nav-link active fw-semibold" href="{{ route('admin.finance.reconciliation.unresolved') }}">
                        <i class="bi bi-question-circle-fill me-2"></i>Unresolved Records
                        <span class="badge bg-danger ms-1">{{ $records->total() }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark fw-semibold" href="{{ route('admin.finance.reconciliation.overpayments') }}">
                        <i class="bi bi-wallet2 me-2"></i>Overpayments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark fw-semibold" href="{{ route('admin.finance.reconciliation.refunds') }}">
                        <i class="bi bi-arrow-left-right me-2"></i>Refunds & Reversals
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark fw-semibold" href="{{ route('admin.finance.reconciliation.orphans') }}">
                        <i class="bi bi-node-minus me-2"></i>Orphan References
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark fw-semibold" href="{{ route('admin.finance.reconciliation.mismatches') }}">
                        <i class="bi bi-exclamation-triangle me-2"></i>Ledger Mismatches
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-octagon-fill me-2"></i><strong>Validation Errors:</strong>
            <ul class="mb-0 mt-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="alert alert-warning shadow-sm" role="alert">
        <i class="bi bi-shield-lock-fill me-2"></i><strong>Audit Notice:</strong> Setting the class and academic year tags on historical records is a sensitive operation. All modifications performed here will be permanent and logged to the central audit history with before/after snapshots.
    </div>

    <!-- Filter Card -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.finance.reconciliation.unresolved') }}" class="row g-3">
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Search Student/Description</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by name, admission number, description..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Reference Type</label>
                    <select name="reference_type" class="form-select">
                        <option value="">All References</option>
                        <option value="fee_structure_item" {{ request('reference_type') == 'fee_structure_item' ? 'selected' : '' }}>Fee Structure Item</option>
                        <option value="fee_collection" {{ request('reference_type') == 'fee_collection' ? 'selected' : '' }}>Fee Collection</option>
                        <option value="fee_refund" {{ request('reference_type') == 'fee_refund' ? 'selected' : '' }}>Fee Refund</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel-fill me-2"></i>Filter Records</button>
                    @if(request()->filled('search') || request()->filled('reference_type'))
                        <a href="{{ route('admin.finance.reconciliation.unresolved') }}" class="btn btn-outline-secondary ms-2"><i class="bi bi-x-circle"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Main Content Table & Bulk Action Form -->
    <form method="POST" action="{{ route('admin.finance.reconciliation.bulk-assign') }}" id="bulk-assign-form">
        @csrf
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
                <h5 class="card-title mb-0 text-primary fw-bold"><i class="bi bi-list-task me-2"></i>Unresolved Ledger Records</h5>
                
                <div class="d-flex align-items-center gap-2">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-dark fw-semibold">Class</span>
                        <select name="class_id" class="form-select">
                            <option value="">Keep / Skip</option>
                            @foreach($classes as $class)
                                <option value="{{ $class->id }}">{{ $class->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-dark fw-semibold">Session</span>
                        <select name="academic_year" class="form-select">
                            <option value="">Keep / Skip</option>
                            @foreach($academicYears as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="btn btn-sm btn-success px-3" id="bulk-assign-btn" disabled>
                        <i class="bi bi-check-all me-1"></i>Apply to Selected
                    </button>
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;" class="text-center">
                                    <input type="checkbox" id="check-all" class="form-check-input">
                                </th>
                                <th>Student</th>
                                <th>Class (Current)</th>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Reference</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Credit</th>
                                <th class="text-center">Missing Tag</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($records as $record)
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" name="ledger_ids[]" value="{{ $record->id }}" class="form-check-input ledger-checkbox">
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $record->student->name ?? 'N/A' }}</div>
                                        <small class="text-muted">Adm No: {{ $record->student->admission_no ?? 'N/A' }}</small>
                                    </td>
                                    <td>
                                        {{ $record->student->schoolClass->name ?? 'N/A' }}
                                    </td>
                                    <td>{{ \Carbon\Carbon::parse($record->date)->format('Y-m-d') }}</td>
                                    <td>
                                        <span class="d-inline-block text-truncate" style="max-width: 250px;" title="{{ $record->description }}">
                                            {{ $record->description }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary text-uppercase font-size-11">{{ str_replace('_', ' ', $record->reference_type) }}</span>
                                        <small class="d-block text-muted">ID: {{ $record->reference_id }}</small>
                                    </td>
                                    <td class="text-end text-danger fw-semibold">
                                        {{ $record->debit > 0 ? '₹' . number_format($record->debit, 2) : '-' }}
                                    </td>
                                    <td class="text-end text-success fw-semibold">
                                        {{ $record->credit > 0 ? '₹' . number_format($record->credit, 2) : '-' }}
                                    </td>
                                    <td class="text-center">
                                        @if(empty($record->academic_year) && empty($record->class_id))
                                            <span class="badge bg-danger">Both</span>
                                        @elseif(empty($record->academic_year))
                                            <span class="badge bg-warning text-dark">Session</span>
                                        @else
                                            <span class="badge bg-info">Class</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <i class="bi bi-check-circle text-success display-4 d-block mb-3"></i>
                                        <h5 class="text-muted">No unresolved ledger records found!</h5>
                                        <p class="text-muted mb-0">All ledger entries have valid academic session and class tags.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($records->hasPages())
                <div class="card-footer bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            Showing {{ $records->firstItem() }} to {{ $records->lastItem() }} of {{ $records->total() }} records
                        </div>
                        <div>
                            {{ $records->links() }}
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkAll = document.getElementById('check-all');
    const checkboxes = document.querySelectorAll('.ledger-checkbox');
    const bulkBtn = document.getElementById('bulk-assign-btn');

    function toggleBulkButton() {
        const checkedCount = document.querySelectorAll('.ledger-checkbox:checked').length;
        bulkBtn.disabled = checkedCount === 0;
    }

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            checkboxes.forEach(cb => cb.checked = checkAll.checked);
            toggleBulkButton();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', function () {
            const allChecked = document.querySelectorAll('.ledger-checkbox:checked').length === checkboxes.length;
            if (checkAll) checkAll.checked = allChecked;
            toggleBulkButton();
        });
    });
});
</script>
@endsection
