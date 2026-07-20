@extends('layouts.admin')

@section('title', 'Fee Demand Register')

@section('content')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');
    
    .demand-container {
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
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .card-premium:hover {
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
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
        transition: all 0.2s ease-in-out;
    }
    
    .form-select-custom:focus, .form-control-custom:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    }
    
    .table-premium {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
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
    
    .table-premium tr:hover td {
        background-color: #f8fafc;
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
    
    .btn-export {
        border-radius: 8px;
        font-weight: 600;
        padding: 8px 16px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
    }
    
    .btn-export-pdf {
        background-color: #ef4444;
        color: #ffffff;
        border: none;
    }
    
    .btn-export-pdf:hover {
        background-color: #dc2626;
        color: #ffffff;
    }
    
    .btn-export-excel {
        background-color: #10b981;
        color: #ffffff;
        border: none;
    }
    
    .btn-export-excel:hover {
        background-color: #059669;
        color: #ffffff;
    }
    
    .btn-export-print {
        background-color: #6366f1;
        color: #ffffff;
        border: none;
    }
    
    .btn-export-print:hover {
        background-color: #4f46e5;
        color: #ffffff;
    }
    
    .badge-status {
        font-size: 0.75rem;
        font-weight: 600;
        padding: 4px 8px;
        border-radius: 9999px;
    }
    
    .badge-paid {
        background-color: #d1fae5;
        color: #065f46;
    }
    
    .badge-unpaid {
        background-color: #fee2e2;
        color: #991b1b;
    }
    
    .badge-partial {
        background-color: #fef3c7;
        color: #92400e;
    }
    
    .badge-overpaid {
        background-color: #e0f2fe;
        color: #075985;
    }
</style>

<div class="container-fluid py-4 demand-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="page-title mb-1"><i class="bi bi-journal-text text-primary me-2"></i>Fee Demand Register</h3>
            <p class="text-muted mb-0">Detailed breakdown of demand, collection, discounts, refunds and outstanding amounts across the institution.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.fees.demand-register.export', array_merge(request()->all(), ['format' => 'excel'])) }}" class="btn btn-export btn-export-excel">
                <i class="bi bi-file-earmark-excel"></i> Export Excel
            </a>
            <a href="{{ route('admin.fees.demand-register.export', array_merge(request()->all(), ['format' => 'pdf'])) }}" class="btn btn-export btn-export-pdf">
                <i class="bi bi-file-earmark-pdf"></i> Export PDF
            </a>
            <a href="{{ route('admin.fees.demand-register.export', array_merge(request()->all(), ['format' => 'print'])) }}" target="_blank" class="btn btn-export btn-export-print">
                <i class="bi bi-printer"></i> Print Register
            </a>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="card card-premium p-4 mb-4">
        <form action="{{ route('admin.fees.demand-register') }}" method="GET" class="row g-3">
            <div class="col-md-2">
                <label for="class_id" class="filter-label mb-1">Class</label>
                <select name="class_id" id="class_id" class="form-select form-select-custom">
                    <option value="">All Classes</option>
                    @foreach($classes as $c)
                        <option value="{{ $c->id }}" {{ request('class_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="section_id" class="filter-label mb-1">Section</label>
                <select name="section_id" id="section_id" class="form-select form-select-custom">
                    <option value="">All Sections</option>
                    @foreach($sections as $s)
                        <option value="{{ $s->id }}" {{ request('section_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="session" class="filter-label mb-1">Session</label>
                <select name="session" id="session" class="form-select form-select-custom">
                    <option value="">All Sessions</option>
                    @foreach($sessions as $session)
                        <option value="{{ $session }}" {{ request('session') == $session ? 'selected' : '' }}>{{ $session }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="month" class="filter-label mb-1">Month</label>
                <select name="month" id="month" class="form-select form-select-custom">
                    <option value="">All Months</option>
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" {{ request('month') == $m ? 'selected' : '' }}>{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-md-2">
                <label for="fee_head_id" class="filter-label mb-1">Fee Head</label>
                <select name="fee_head_id" id="fee_head_id" class="form-select form-select-custom">
                    <option value="">All Fee Heads</option>
                    @foreach($feeHeads as $fh)
                        <option value="{{ $fh->id }}" {{ request('fee_head_id') == $fh->id ? 'selected' : '' }}>{{ $fh->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="status" class="filter-label mb-1">Status</label>
                <select name="status" id="status" class="form-select form-select-custom">
                    <option value="">All Statuses</option>
                    <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Paid</option>
                    <option value="unpaid" {{ request('status') === 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                    <option value="partially_paid" {{ request('status') === 'partially_paid' ? 'selected' : '' }}>Partially Paid</option>
                    <option value="overpaid" {{ request('status') === 'overpaid' ? 'selected' : '' }}>Overpaid</option>
                </select>
            </div>
            <div class="col-12 d-flex justify-content-end gap-2 mt-3">
                <a href="{{ route('admin.fees.demand-register') }}" class="btn btn-outline-secondary px-4"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-funnel"></i> Apply Filters</button>
            </div>
        </form>
    </div>

    <!-- Data Table Card -->
    <div class="card card-premium overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table table-premium mb-0">
                <thead>
                    <tr>
                        <th>Admission No</th>
                        <th>Student</th>
                        <th>Class</th>
                        <th class="text-end">Fee Demand</th>
                        <th class="text-end">Discount</th>
                        <th class="text-end">Late Fee</th>
                        <th class="text-end">Collected</th>
                        <th class="text-end">Refund</th>
                        <th class="text-end">Outstanding</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $row)
                        @php
                            $outstanding = (float) $row->outstanding;
                            $collected = (float) $row->collected;
                        @endphp
                        <tr>
                            <td><strong>{{ $row->admission_no }}</strong></td>
                            <td>{{ $row->student_name }}</td>
                            <td>{{ $row->class_name }} @if($row->section_name) ({{ $row->section_name }}) @endif</td>
                            <td class="text-end">₹{{ number_format($row->fee_demand, 2) }}</td>
                            <td class="text-end text-success">₹{{ number_format($row->discount, 2) }}</td>
                            <td class="text-end text-danger">₹{{ number_format($row->late_fee, 2) }}</td>
                            <td class="text-end">₹{{ number_format($row->collected, 2) }}</td>
                            <td class="text-end">₹{{ number_format($row->refund, 2) }}</td>
                            <td class="text-end fw-bold {{ $outstanding > 0 ? 'text-danger' : ($outstanding < 0 ? 'text-primary' : '') }}">
                                ₹{{ number_format($row->outstanding, 2) }}
                            </td>
                            <td class="text-center">
                                @if($outstanding <= 0 && $collected > 0)
                                    <span class="badge badge-status badge-paid">Paid</span>
                                @elseif($outstanding > 0 && $collected == 0)
                                    <span class="badge badge-status badge-unpaid">Unpaid</span>
                                @elseif($outstanding > 0 && $collected > 0)
                                    <span class="badge badge-status badge-partial">Partial</span>
                                @elseif($outstanding < 0)
                                    <span class="badge badge-status badge-overpaid">Overpaid</span>
                                @else
                                    <span class="badge badge-status bg-light text-dark">No Due</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-folder-x fs-2"></i>
                                <p class="mt-2 mb-0">No records found matching the specified filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <!-- Page Totals (Only for the records visible on this page) -->
                    @if($records->count() > 0)
                        <tr class="totals-row">
                            <td colspan="3">PAGE TOTALS</td>
                            <td class="text-end">₹{{ number_format($records->sum('fee_demand'), 2) }}</td>
                            <td class="text-end">₹{{ number_format($records->sum('discount'), 2) }}</td>
                            <td class="text-end">₹{{ number_format($records->sum('late_fee'), 2) }}</td>
                            <td class="text-end">₹{{ number_format($records->sum('collected'), 2) }}</td>
                            <td class="text-end">₹{{ number_format($records->sum('refund'), 2) }}</td>
                            <td class="text-end">₹{{ number_format($records->sum('outstanding'), 2) }}</td>
                            <td></td>
                        </tr>
                    @endif
                    
                    <!-- Grand Totals (Calculated dynamically across all matching records) -->
                    @if($totals)
                        <tr class="grand-totals-row">
                            <td colspan="3">GRAND TOTALS ({{ $records->total() }} Students)</td>
                            <td class="text-end">₹{{ number_format($totals->total_demand ?? 0, 2) }}</td>
                            <td class="text-end">₹{{ number_format($totals->total_discount ?? 0, 2) }}</td>
                            <td class="text-end">₹{{ number_format($totals->total_late_fee ?? 0, 2) }}</td>
                            <td class="text-end">₹{{ number_format($totals->total_collected ?? 0, 2) }}</td>
                            <td class="text-end">₹{{ number_format($totals->total_refund ?? 0, 2) }}</td>
                            <td class="text-end">₹{{ number_format($totals->total_outstanding ?? 0, 2) }}</td>
                            <td></td>
                        </tr>
                    @endif
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Pagination Links -->
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
