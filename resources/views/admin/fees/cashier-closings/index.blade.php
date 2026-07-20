@extends('layouts.admin')

@section('title', 'Cashier Closing Registry')

@section('content')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');
    
    .closings-container {
        font-family: 'Outfit', sans-serif;
        background-color: #f8fafc;
        border-radius: 16px;
    }
    
    .page-title {
        font-weight: 700;
        color: #1e293b;
    }
    
    .card-premium {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    
    .table-premium th {
        background-color: #f1f5f9;
        color: #475569;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        padding: 14px 16px;
    }
    
    .table-premium td {
        padding: 14px 16px;
        border-bottom: 1px solid #e2e8f0;
        color: #334155;
    }
</style>

<div class="container-fluid py-4 closings-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="page-title mb-1"><i class="bi bi-lock text-danger me-2"></i>Cashier Closings</h3>
            <p class="text-muted mb-0">Review shift closure submissions and expected vs actual payment handovers.</p>
        </div>
        <div>
            <a href="{{ route('admin.fees.cashier-closings.create') }}" class="btn btn-danger"><i class="bi bi-shield-lock-fill"></i> Close My Shift</a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card card-premium p-4 mb-4">
        <form action="{{ route('admin.fees.cashier-closings.index') }}" method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="start_date" class="filter-label mb-1">Start Date</label>
                <input type="date" name="start_date" id="start_date" value="{{ request('start_date') }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label for="end_date" class="filter-label mb-1">End Date</label>
                <input type="date" name="end_date" id="end_date" value="{{ request('end_date') }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label for="cashier_id" class="filter-label mb-1">Cashier</label>
                <select name="cashier_id" id="cashier_id" class="form-select">
                    <option value="">All Cashiers</option>
                    @foreach($cashiers as $user)
                        <option value="{{ $user->id }}" {{ request('cashier_id') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <a href="{{ route('admin.fees.cashier-closings.index') }}" class="btn btn-outline-secondary w-50">Reset</a>
                <button type="submit" class="btn btn-primary w-50">Apply</button>
            </div>
        </form>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Data Table -->
    <div class="card card-premium overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table table-premium mb-0">
                <thead>
                    <tr>
                        <th>Closing Date</th>
                        <th>Cashier</th>
                        <th class="text-end">Opening Bal</th>
                        <th class="text-end">Expected Cash</th>
                        <th class="text-end">Actual Cash</th>
                        <th class="text-end">Total Expected</th>
                        <th class="text-end">Total Actual</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($closings as $row)
                        @php
                            $expectedTotal = (float)($row->expected_cash + $row->expected_upi + $row->expected_bank + $row->expected_cheque + $row->expected_online);
                            $actualTotal = (float)($row->actual_cash + $row->actual_upi + $row->actual_bank + $row->actual_cheque + $row->actual_online);
                            $diff = $actualTotal - $expectedTotal;
                        @endphp
                        <tr>
                            <td><strong>{{ $row->closing_date->format('Y-m-d') }}</strong></td>
                            <td>{{ $row->cashier->name ?? 'N/A' }}</td>
                            <td class="text-end">₹{{ number_format($row->opening_balance, 2) }}</td>
                            <td class="text-end text-muted">₹{{ number_format($row->expected_cash, 2) }}</td>
                            <td class="text-end fw-bold">₹{{ number_format($row->actual_cash, 2) }}</td>
                            <td class="text-end text-muted">₹{{ number_format($expectedTotal, 2) }}</td>
                            <td class="text-end fw-bold {{ $diff < 0 ? 'text-danger' : ($diff > 0 ? 'text-success' : '') }}">
                                ₹{{ number_format($actualTotal, 2) }}
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $row->status === 'verified' ? 'bg-success' : 'bg-warning text-dark' }}">
                                    {{ ucfirst($row->status) }}
                                </span>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.fees.cashier-closings.show', $row->id) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-lock-fill fs-2"></i>
                                <p class="mt-2 mb-0">No cashier closing reports submitted yet.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
