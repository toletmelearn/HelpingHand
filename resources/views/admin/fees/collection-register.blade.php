@extends('layouts.admin')

@section('title', 'Daily Collection Register')

@section('content')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');
    
    .register-container {
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
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
        border-radius: 16px;
    }
    
    .filter-label {
        font-size: 0.8rem;
        text-transform: uppercase;
        font-weight: 600;
        color: #64748b;
        letter-spacing: 0.5px;
    }
    
    .form-select-custom, .form-control-custom {
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        font-weight: 500;
        color: #334155;
    }
    
    .btn-group-toggle .btn {
        font-weight: 600;
        border-radius: 8px;
        padding: 8px 16px;
        transition: all 0.2s ease;
    }
    
    .table-premium th {
        background-color: #f1f5f9;
        color: #475569;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        padding: 14px 16px;
        border-bottom: 2px solid #e2e8f0;
    }
    
    .table-premium td {
        padding: 14px 16px;
        border-bottom: 1px solid #e2e8f0;
        color: #334155;
        font-size: 0.9rem;
    }
    
    .totals-row {
        background-color: #f8fafc;
        font-weight: 600;
    }
    
    .grand-totals-row {
        background-color: #eff6ff;
        font-weight: 700;
        color: #1e3a8a;
    }
    
    .badge-cancelled {
        background-color: #fee2e2;
        color: #991b1b;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 4px 8px;
        border-radius: 9999px;
    }
    
    .badge-active {
        background-color: #d1fae5;
        color: #065f46;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 4px 8px;
        border-radius: 9999px;
    }
</style>

<div class="container-fluid py-4 register-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="page-title mb-1"><i class="bi bi-cash-stack text-success me-2"></i>Daily Collection Register</h3>
            <p class="text-muted mb-0">Monitor cash, UPI, bank, cheque and online income streams with cashier closing session integration.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.fees.collection-register.export', array_merge(request()->all(), ['format' => 'excel'])) }}" class="btn btn-success"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
            <a href="{{ route('admin.fees.collection-register.export', array_merge(request()->all(), ['format' => 'pdf'])) }}" class="btn btn-danger"><i class="bi bi-file-earmark-pdf"></i> Export PDF</a>
            <a href="{{ route('admin.fees.collection-register.export', array_merge(request()->all(), ['format' => 'print'])) }}" target="_blank" class="btn btn-indigo text-white" style="background-color: #6366f1;"><i class="bi bi-printer"></i> Print Preview</a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card card-premium p-4 mb-4">
        <form action="{{ route('admin.fees.collection-register') }}" method="GET" class="row g-3">
            <input type="hidden" name="group_by" value="{{ $groupBy }}">
            
            <div class="col-md-3">
                <label for="start_date" class="filter-label mb-1">Start Date</label>
                <input type="date" name="start_date" id="start_date" value="{{ request('start_date', now()->format('Y-m-d')) }}" class="form-control form-control-custom">
            </div>
            
            <div class="col-md-3">
                <label for="end_date" class="filter-label mb-1">End Date</label>
                <input type="date" name="end_date" id="end_date" value="{{ request('end_date', now()->format('Y-m-d')) }}" class="form-control form-control-custom">
            </div>

            <div class="col-md-3">
                <label for="cashier_id" class="filter-label mb-1">Cashier</label>
                <select name="cashier_id" id="cashier_id" class="form-select form-select-custom">
                    <option value="">All Cashiers</option>
                    @foreach($cashiers as $user)
                        <option value="{{ $user->id }}" {{ request('cashier_id') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3 d-flex align-items-end gap-2">
                <a href="{{ route('admin.fees.collection-register') }}" class="btn btn-outline-secondary w-50">Reset</a>
                <button type="submit" class="btn btn-primary w-50">Apply</button>
            </div>
        </form>
    </div>

    <!-- Group By Toggle -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="btn-group btn-group-toggle" role="group">
            <a href="{{ route('admin.fees.collection-register', array_merge(request()->all(), ['group_by' => 'date'])) }}" class="btn {{ $groupBy === 'date' ? 'btn-primary' : 'btn-outline-primary' }}">Group by Date</a>
            <a href="{{ route('admin.fees.collection-register', array_merge(request()->all(), ['group_by' => 'cashier'])) }}" class="btn {{ $groupBy === 'cashier' ? 'btn-primary' : 'btn-outline-primary' }}">Group by Cashier</a>
            <a href="{{ route('admin.fees.collection-register', array_merge(request()->all(), ['group_by' => 'payment_mode'])) }}" class="btn {{ $groupBy === 'payment_mode' ? 'btn-primary' : 'btn-outline-primary' }}">Group by Mode</a>
            <a href="{{ route('admin.fees.collection-register', array_merge(request()->all(), ['group_by' => 'receipt'])) }}" class="btn {{ $groupBy === 'receipt' ? 'btn-primary' : 'btn-outline-primary' }}">Receipt Range/List</a>
        </div>
    </div>

    <!-- Main Register Data -->
    <div class="card card-premium overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table table-premium mb-0">
                <thead>
                    @if($groupBy === 'cashier')
                        <tr>
                            <th>Cashier</th>
                            <th class="text-end">Cash</th>
                            <th class="text-end">UPI</th>
                            <th class="text-end">Bank</th>
                            <th class="text-end">Cheque</th>
                            <th class="text-end">Online</th>
                            <th class="text-end">Refund</th>
                            <th class="text-end">Cancelled</th>
                            <th class="text-end">Net Collection</th>
                        </tr>
                    @elseif($groupBy === 'date')
                        <tr>
                            <th>Date</th>
                            <th class="text-end">Cash</th>
                            <th class="text-end">UPI</th>
                            <th class="text-end">Bank</th>
                            <th class="text-end">Cheque</th>
                            <th class="text-end">Online</th>
                            <th class="text-end">Refund</th>
                            <th class="text-end">Cancelled</th>
                            <th class="text-end">Net Collection</th>
                        </tr>
                    @elseif($groupBy === 'payment_mode')
                        <tr>
                            <th>Payment Mode</th>
                            <th class="text-center">Transaction Count</th>
                            <th class="text-end">Total Collection</th>
                        </tr>
                    @else
                        <tr>
                            <th>Receipt No</th>
                            <th>Date</th>
                            <th>Student</th>
                            <th>Cashier</th>
                            <th>Payment Mode</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Status</th>
                        </tr>
                    @endif
                </thead>
                <tbody>
                    @forelse($records as $row)
                        @if($groupBy === 'cashier')
                            <tr>
                                <td><strong>{{ $row->cashier_name }}</strong></td>
                                <td class="text-end">₹{{ number_format($row->cash, 2) }}</td>
                                <td class="text-end">₹{{ number_format($row->upi, 2) }}</td>
                                <td class="text-end">₹{{ number_format($row->bank, 2) }}</td>
                                <td class="text-end">₹{{ number_format($row->cheque, 2) }}</td>
                                <td class="text-end">₹{{ number_format($row->online, 2) }}</td>
                                <td class="text-end text-danger">₹{{ number_format($row->refund, 2) }}</td>
                                <td class="text-end text-muted">₹{{ number_format($row->cancelled, 2) }}</td>
                                <td class="text-end fw-bold text-success">₹{{ number_format($row->net_collection, 2) }}</td>
                            </tr>
                        @elseif($groupBy === 'date')
                            <tr>
                                <td><strong>{{ $row->group_date }}</strong></td>
                                <td class="text-end">₹{{ number_format($row->cash, 2) }}</td>
                                <td class="text-end">₹{{ number_format($row->upi, 2) }}</td>
                                <td class="text-end">₹{{ number_format($row->bank, 2) }}</td>
                                <td class="text-end">₹{{ number_format($row->cheque, 2) }}</td>
                                <td class="text-end">₹{{ number_format($row->online, 2) }}</td>
                                <td class="text-end text-danger">₹{{ number_format($row->refund, 2) }}</td>
                                <td class="text-end text-muted">₹{{ number_format($row->cancelled, 2) }}</td>
                                <td class="text-end fw-bold text-success">₹{{ number_format($row->net_collection, 2) }}</td>
                            </tr>
                        @elseif($groupBy === 'payment_mode')
                            <tr>
                                <td><strong>{{ $row->payment_mode }}</strong></td>
                                <td class="text-center">{{ $row->tx_count }}</td>
                                <td class="text-end fw-bold text-success">₹{{ number_format($row->cash_total, 2) }}</td>
                            </tr>
                        @else
                            <tr>
                                <td><strong>{{ $row->receipt_no }}</strong></td>
                                <td>{{ $row->payment_date }}</td>
                                <td>{{ $row->student_name }}</td>
                                <td>{{ $row->cashier_name }}</td>
                                <td><span class="badge bg-secondary">{{ $row->payment_mode }}</span></td>
                                <td class="text-end">₹{{ number_format($row->final_amount, 2) }}</td>
                                <td class="text-center">
                                    @if($row->deleted_at)
                                        <span class="badge badge-cancelled">Cancelled</span>
                                    @else
                                        <span class="badge badge-active">Active</span>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-folder-x fs-2"></i>
                                <p class="mt-2 mb-0">No records found for the selected filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if(($groupBy === 'cashier' || $groupBy === 'date') && $totals)
                    <tfoot>
                        <tr class="grand-totals-row">
                            <td>GRAND TOTALS</td>
                            <td class="text-end">₹{{ number_format($totals->total_cash, 2) }}</td>
                            <td class="text-end">₹{{ number_format($totals->total_upi, 2) }}</td>
                            <td class="text-end">₹{{ number_format($totals->total_bank, 2) }}</td>
                            <td class="text-end">₹{{ number_format($totals->total_cheque, 2) }}</td>
                            <td class="text-end">₹{{ number_format($totals->total_online, 2) }}</td>
                            <td class="text-end text-danger">₹{{ number_format($totals->total_refund, 2) }}</td>
                            <td class="text-end text-muted">₹{{ number_format($totals->total_cancelled, 2) }}</td>
                            <td class="text-end fw-bold">₹{{ number_format($totals->net_collection, 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-between align-items-center">
        <p class="text-muted small mb-0">
            Showing {{ $records->firstItem() ?? 0 }} to {{ $records->lastItem() ?? 0 }} of {{ $records->total() }} records
        </p>
        <div>
            {{ $records->links() }}
        </div>
    </div>
</div>
@endsection
