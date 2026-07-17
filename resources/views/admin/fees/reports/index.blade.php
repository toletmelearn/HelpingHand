@extends('layouts.admin')

@section('title', 'Finance Reports Portal')

@section('content')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');
    
    .reports-container {
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
    
    .sidebar-report-btn {
        display: block;
        width: 100%;
        text-align: left;
        padding: 12px 16px;
        border-radius: 10px;
        border: none;
        background: transparent;
        color: #475569;
        font-weight: 500;
        font-size: 0.9rem;
        transition: all 0.2s ease;
    }
    
    .sidebar-report-btn:hover {
        background-color: #f1f5f9;
        color: #0f172a;
    }
    
    .sidebar-report-btn.active {
        background-color: #ef4444;
        color: #ffffff;
        font-weight: 600;
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
    
    .badge-mode {
        font-weight: 600;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.75rem;
    }
</style>

<div class="container-fluid py-4 reports-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="page-title mb-1"><i class="bi bi-file-earmark-bar-graph-fill text-danger me-2"></i>Finance Reports Portal</h3>
            <p class="text-muted mb-0">Unified dashboard to query, filter, audit, print, and export 17 enterprise finance registers.</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- Reports Selector Sidebar -->
        <div class="col-lg-3">
            <div class="card card-premium p-3">
                <h6 class="fw-bold text-muted small text-uppercase mb-3 px-2">Select Register / Report</h6>
                <div class="d-flex flex-column gap-1" style="max-height: 550px; overflow-y: auto;">
                    @foreach(\App\Http\Controllers\Admin\FinanceReportController::$reportsList as $key => $label)
                        <a href="{{ route('admin.fees.reports.index', array_merge(request()->all(), ['type' => $key])) }}" 
                           class="sidebar-report-btn decoration-none {{ $activeReport === $key ? 'active' : '' }}">
                            <i class="bi bi-journal-text me-2"></i>{{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Filter and Results Area -->
        <div class="col-lg-9">
            <!-- Filter Bar -->
            <div class="card card-premium p-4 mb-4">
                <form action="{{ route('admin.fees.reports.index') }}" method="GET" class="row g-3">
                    <input type="hidden" name="type" value="{{ $activeReport }}">
                    
                    <div class="col-md-3">
                        <label for="start_date" class="form-label small fw-bold text-muted">Start Date</label>
                        <input type="date" name="start_date" id="start_date" value="{{ request('start_date') }}" class="form-control form-control-sm">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="end_date" class="form-label small fw-bold text-muted">End Date</label>
                        <input type="date" name="end_date" id="end_date" value="{{ request('end_date') }}" class="form-control form-control-sm">
                    </div>

                    @if(in_array($activeReport, ['collection_register', 'cash_book', 'day_book', 'outstanding_register', 'demand_register', 'refund_register', 'discount_register', 'late_fee_register', 'cancelled_receipts', 'receipt_register']))
                        <div class="col-md-3">
                            <label for="class_id" class="form-label small fw-bold text-muted">Class</label>
                            <select name="class_id" id="class_id" class="form-select form-select-sm">
                                <option value="">All Classes</option>
                                @foreach($classes as $cls)
                                    <option value="{{ $cls->id }}" {{ request('class_id') == $cls->id ? 'selected' : '' }}>{{ $cls->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if(in_array($activeReport, ['outstanding_register', 'demand_register']))
                        <div class="col-md-3">
                            <label for="section_id" class="form-label small fw-bold text-muted">Section</label>
                            <select name="section_id" id="section_id" class="form-select form-select-sm">
                                <option value="">All Sections</option>
                                @foreach($sections as $sec)
                                    <option value="{{ $sec->id }}" {{ request('section_id') == $sec->id ? 'selected' : '' }}>{{ $sec->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if(in_array($activeReport, ['collection_register', 'cash_book', 'day_book', 'ledger_book', 'outstanding_register', 'demand_register', 'refund_register', 'discount_register', 'late_fee_register', 'cancelled_receipts', 'receipt_register']))
                        <div class="col-md-3">
                            <label for="student_id" class="form-label small fw-bold text-muted">Student</label>
                            <select name="student_id" id="student_id" class="form-select form-select-sm">
                                <option value="">Select Student...</option>
                                @foreach($students as $st)
                                    <option value="{{ $st->id }}" {{ request('student_id') == $st->id ? 'selected' : '' }}>{{ $st->name }} ({{ $st->admission_no }})</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if(in_array($activeReport, ['collection_register', 'refund_register', 'receipt_register']))
                        <div class="col-md-3">
                            <label for="payment_mode" class="form-label small fw-bold text-muted">Payment Mode</label>
                            <select name="payment_mode" id="payment_mode" class="form-select form-select-sm">
                                <option value="">All Modes</option>
                                <option value="cash" {{ request('payment_mode') === 'cash' ? 'selected' : '' }}>Cash</option>
                                <option value="upi" {{ request('payment_mode') === 'upi' ? 'selected' : '' }}>UPI</option>
                                <option value="bank" {{ request('payment_mode') === 'bank' ? 'selected' : '' }}>Bank Transfer</option>
                                <option value="online" {{ request('payment_mode') === 'online' ? 'selected' : '' }}>Online</option>
                            </select>
                        </div>
                    @endif

                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <a href="{{ route('admin.fees.reports.index', ['type' => $activeReport]) }}" class="btn btn-sm btn-outline-secondary w-50">Reset</a>
                        <button type="submit" class="btn btn-sm btn-danger w-50">Filter</button>
                    </div>
                </form>
            </div>

            <!-- Report Header & Actions -->
            <div class="card card-premium p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0">{{ $reportTitle }}</h5>
                    <div class="d-flex gap-2">
                        <a href="{{ route('admin.fees.reports.export', array_merge(request()->all(), ['format' => 'excel'])) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Excel</a>
                        <a href="{{ route('admin.fees.reports.export', array_merge(request()->all(), ['format' => 'csv'])) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-filetype-csv"></i> CSV</a>
                        <a href="{{ route('admin.fees.reports.export', array_merge(request()->all(), ['format' => 'print'])) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> Print / PDF</a>
                    </div>
                </div>

                <!-- Report Content Table -->
                <div class="table-responsive">
                    <table class="table table-premium mb-0 align-middle">
                        <thead>
                            <tr>
                                @if($activeReport === 'collection_register')
                                    <th>Receipt No</th>
                                    <th>Admission No</th>
                                    <th>Student Name</th>
                                    <th>Class</th>
                                    <th>Date</th>
                                    <th>Mode</th>
                                    <th class="text-end">Amount</th>
                                    <th>Cashier</th>
                                @elseif($activeReport === 'cash_book')
                                    <th>Date</th>
                                    <th>Reference/Receipt</th>
                                    <th>Particulars</th>
                                    <th class="text-end">Debit (Collection)</th>
                                    <th class="text-end">Credit (Refund)</th>
                                @elseif($activeReport === 'day_book')
                                    <th>Date</th>
                                    <th>Student Name</th>
                                    <th>Description</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                @elseif($activeReport === 'ledger_book')
                                    <th>Date</th>
                                    <th>Reference Type</th>
                                    <th>Reference ID</th>
                                    <th>Description</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                    <th class="text-end">Running Balance</th>
                                @elseif($activeReport === 'outstanding_register')
                                    <th>Admission No</th>
                                    <th>Student Name</th>
                                    <th>Class Name</th>
                                    <th class="text-end">Total Demanded</th>
                                    <th class="text-end">Total Paid</th>
                                    <th class="text-end">Outstanding Balance</th>
                                @elseif($activeReport === 'demand_register')
                                    <th>Admission No</th>
                                    <th>Student Name</th>
                                    <th>Class Name</th>
                                    <th>Demand Date</th>
                                    <th>Description</th>
                                    <th class="text-end">Amount</th>
                                @elseif($activeReport === 'refund_register')
                                    <th>Date</th>
                                    <th>Student Name</th>
                                    <th>Refund Type</th>
                                    <th class="text-end">Amount Refunded</th>
                                    <th>Payment Mode</th>
                                    <th>Reason</th>
                                @elseif($activeReport === 'discount_register')
                                    <th>Student Name</th>
                                    <th>Class Name</th>
                                    <th>Discount Rule Name</th>
                                    <th class="text-end">Discount Amount</th>
                                    <th>Status</th>
                                @elseif($activeReport === 'late_fee_register')
                                    <th>Date</th>
                                    <th>Student Name</th>
                                    <th>Class Name</th>
                                    <th class="text-end">Late Fee Collected</th>
                                @elseif($activeReport === 'head_wise')
                                    <th>Fee Head Name</th>
                                    <th class="text-end">Total Collected</th>
                                @elseif($activeReport === 'month_wise')
                                    <th>Month</th>
                                    <th class="text-end">Cash</th>
                                    <th class="text-end">UPI</th>
                                    <th class="text-end">Bank Transfer</th>
                                    <th class="text-end">Online</th>
                                    <th class="text-end">Total Collected</th>
                                @elseif($activeReport === 'class_wise')
                                    <th>Class Name</th>
                                    <th class="text-end">Total Collected</th>
                                @elseif($activeReport === 'session_comparison')
                                    <th>Academic Session</th>
                                    <th class="text-end">Total Demand</th>
                                    <th class="text-end">Total Collected</th>
                                    <th class="text-end">Total Outstanding</th>
                                    <th class="text-end">Recovery Rate (%)</th>
                                @elseif($activeReport === 'income_summary')
                                    <th>Component / Head</th>
                                    <th class="text-end">Demanded Amount</th>
                                    <th class="text-end">Collected Amount</th>
                                    <th class="text-end">Outstanding Amount</th>
                                @elseif($activeReport === 'cancelled_receipts')
                                    <th>Receipt No</th>
                                    <th>Student Name</th>
                                    <th class="text-end">Original Amount</th>
                                    <th>Reversed Date</th>
                                    <th>Reason</th>
                                    <th>Reversed By</th>
                                @elseif($activeReport === 'receipt_register')
                                    <th>Receipt No</th>
                                    <th>Student Name</th>
                                    <th>Payment Date</th>
                                    <th>Payment Mode</th>
                                    <th class="text-end">Paid Amount</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data as $row)
                                <tr>
                                    @if($activeReport === 'collection_register')
                                        <td><strong>{{ $row->receipt_no }}</strong></td>
                                        <td>{{ $row->admission_no }}</td>
                                        <td>{{ $row->student_name }}</td>
                                        <td>{{ $row->class_name }}</td>
                                        <td>{{ $row->payment_date }}</td>
                                        <td><span class="badge bg-secondary badge-mode">{{ strtoupper($row->payment_mode) }}</span></td>
                                        <td class="text-end fw-bold">₹{{ number_format($row->final_amount, 2) }}</td>
                                        <td>{{ $row->cashier_name }}</td>
                                    @elseif($activeReport === 'cash_book')
                                        <td>{{ $row->date }}</td>
                                        <td><strong>{{ $row->reference }}</strong></td>
                                        <td>{{ $row->particulars }}</td>
                                        <td class="text-end text-success fw-bold">₹{{ number_format($row->debit, 2) }}</td>
                                        <td class="text-end text-danger fw-bold">₹{{ number_format($row->credit, 2) }}</td>
                                    @elseif($activeReport === 'day_book')
                                        <td>{{ $row->date }}</td>
                                        <td>{{ $row->student_name }}</td>
                                        <td>{{ $row->description }}</td>
                                        <td class="text-end text-danger">₹{{ number_format($row->debit, 2) }}</td>
                                        <td class="text-end text-success">₹{{ number_format($row->credit, 2) }}</td>
                                    @elseif($activeReport === 'ledger_book')
                                        <td>{{ $row->date }}</td>
                                        <td>{{ $row->reference_type ?? 'N/A' }}</td>
                                        <td>{{ $row->reference_id ?? 'N/A' }}</td>
                                        <td>{{ $row->description }}</td>
                                        <td class="text-end text-danger">₹{{ number_format($row->debit, 2) }}</td>
                                        <td class="text-end text-success">₹{{ number_format($row->credit, 2) }}</td>
                                        <td class="text-end fw-bold">₹{{ number_format($row->running_balance, 2) }}</td>
                                    @elseif($activeReport === 'outstanding_register')
                                        <td>{{ $row->admission_no }}</td>
                                        <td><strong>{{ $row->student_name }}</strong></td>
                                        <td>{{ $row->class_name }}</td>
                                        <td class="text-end">₹{{ number_format($row->total_charged, 2) }}</td>
                                        <td class="text-end">₹{{ number_format($row->total_paid, 2) }}</td>
                                        <td class="text-end text-danger fw-bold">₹{{ number_format($row->outstanding_balance, 2) }}</td>
                                    @elseif($activeReport === 'demand_register')
                                        <td>{{ $row->admission_no }}</td>
                                        <td>{{ $row->student_name }}</td>
                                        <td>{{ $row->class_name }}</td>
                                        <td>{{ $row->demand_date }}</td>
                                        <td>{{ $row->description }}</td>
                                        <td class="text-end fw-bold text-danger">₹{{ number_format($row->amount, 2) }}</td>
                                    @elseif($activeReport === 'refund_register')
                                        <td>{{ $row->date }}</td>
                                        <td><strong>{{ $row->student_name }}</strong></td>
                                        <td><span class="badge bg-danger">{{ strtoupper($row->type) }}</span></td>
                                        <td class="text-end fw-bold text-danger">₹{{ number_format($row->amount, 2) }}</td>
                                        <td><span class="badge bg-secondary badge-mode">{{ strtoupper($row->payment_mode) }}</span></td>
                                        <td>{{ $row->reason }}</td>
                                    @elseif($activeReport === 'discount_register')
                                        <td><strong>{{ $row->student_name }}</strong></td>
                                        <td>{{ $row->class_name }}</td>
                                        <td>{{ $row->discount_name }}</td>
                                        <td class="text-end fw-bold text-success">₹{{ number_format($row->amount, 2) }}</td>
                                        <td><span class="badge bg-info">{{ strtoupper($row->status) }}</span></td>
                                    @elseif($activeReport === 'late_fee_register')
                                        <td>{{ $row->date }}</td>
                                        <td><strong>{{ $row->student_name }}</strong></td>
                                        <td>{{ $row->class_name }}</td>
                                        <td class="text-end fw-bold text-danger">₹{{ number_format($row->late_fee_collected, 2) }}</td>
                                    @elseif($activeReport === 'head_wise')
                                        <td><strong>{{ $row->head_name }}</strong></td>
                                        <td class="text-end fw-bold text-success">₹{{ number_format($row->total_collected, 2) }}</td>
                                    @elseif($activeReport === 'month_wise')
                                        <td><strong>{{ $row['month_name'] }}</strong></td>
                                        <td class="text-end">₹{{ number_format($row['cash'], 2) }}</td>
                                        <td class="text-end">₹{{ number_format($row['upi'], 2) }}</td>
                                        <td class="text-end">₹{{ number_format($row['bank'], 2) }}</td>
                                        <td class="text-end">₹{{ number_format($row['online'], 2) }}</td>
                                        <td class="text-end fw-bold text-success">₹{{ number_format($row['total'], 2) }}</td>
                                    @elseif($activeReport === 'class_wise')
                                        <td><strong>{{ $row->class_name }}</strong></td>
                                        <td class="text-end fw-bold text-success">₹{{ number_format($row->total_collected, 2) }}</td>
                                    @elseif($activeReport === 'session_comparison')
                                        <td><strong>{{ $row->academic_year }}</strong></td>
                                        <td class="text-end">₹{{ number_format($row->total_demand, 2) }}</td>
                                        <td class="text-end text-success">₹{{ number_format($row->total_collected, 2) }}</td>
                                        <td class="text-end text-danger">₹{{ number_format($row->total_outstanding, 2) }}</td>
                                        @php
                                            $recovery = $row->total_demand > 0 ? round(($row->total_collected / $row->total_demand) * 100, 2) : 100.00;
                                        @endphp
                                        <td class="text-end fw-bold">{{ $recovery }}%</td>
                                    @elseif($activeReport === 'income_summary')
                                        <td><strong>{{ $row->head_name }}</strong></td>
                                        <td class="text-end">₹{{ number_format($row->demanded, 2) }}</td>
                                        <td class="text-end text-success">₹{{ number_format($row->collected, 2) }}</td>
                                        <td class="text-end text-danger">₹{{ number_format($row->outstanding, 2) }}</td>
                                    @elseif($activeReport === 'cancelled_receipts')
                                        <td><strong>{{ $row->receipt_no }}</strong></td>
                                        <td>{{ $row->student_name }}</td>
                                        <td class="text-end text-muted">₹{{ number_format($row->original_amount, 2) }}</td>
                                        <td>{{ $row->reversed_date }}</td>
                                        <td>{{ $row->reason }}</td>
                                        <td>{{ $row->approved_by_name }}</td>
                                    @elseif($activeReport === 'receipt_register')
                                        <td><strong>{{ $row->receipt_no }}</strong></td>
                                        <td>{{ $row->student_name }}</td>
                                        <td>{{ $row->payment_date }}</td>
                                        <td><span class="badge bg-secondary badge-mode">{{ strtoupper($row->payment_mode) }}</span></td>
                                        <td class="text-end fw-bold">₹{{ number_format($row->final_amount, 2) }}</td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-5 text-muted">
                                        <i class="bi bi-file-earmark-x fs-2"></i>
                                        <p class="mt-2 mb-0">No records found matching filters.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination for paginated sets -->
                @if($activeReport !== 'month_wise')
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <p class="text-muted small mb-0">
                            Showing {{ $data->firstItem() ?? 0 }} to {{ $data->lastItem() ?? 0 }} of {{ $data->total() }} records
                        </p>
                        <div>
                            {{ $data->links() }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
