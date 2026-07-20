@extends('layouts.admin')

@section('title', 'Accountant Dashboard')

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
    
    .card-premium {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .card-premium:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }
    
    .card-stat {
        position: relative;
        overflow: hidden;
        border-radius: 16px;
        color: #ffffff;
        padding: 24px;
        box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 0.08);
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    
    .card-stat-icon {
        position: absolute;
        right: 15px;
        bottom: 10px;
        font-size: 5rem;
        opacity: 0.15;
    }

    a.card-stat {
        text-decoration: none;
        color: #ffffff;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    a.card-stat:hover {
        color: #ffffff;
        transform: translateY(-3px);
        box-shadow: 0 8px 24px 0 rgba(0, 0, 0, 0.16);
    }

    .bg-gradient-blue { background: linear-gradient(135deg, #1e3a8a, #3b82f6); }
    .bg-gradient-red { background: linear-gradient(135deg, #7f1d1d, #ef4444); }
    .bg-gradient-green { background: linear-gradient(135deg, #064e3b, #10b981); }
    .bg-gradient-yellow { background: linear-gradient(135deg, #78350f, #f59e0b); }
    .bg-gradient-indigo { background: linear-gradient(135deg, #312e81, #6366f1); }
    .bg-gradient-purple { background: linear-gradient(135deg, #581c87, #a855f7); }
    .bg-gradient-teal { background: linear-gradient(135deg, #134e5e, #71b280); }
    .bg-gradient-pink { background: linear-gradient(135deg, #831843, #ec4899); }

    .stat-label {
        font-size: 0.85rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        opacity: 0.9;
    }

    .stat-value {
        font-size: 1.8rem;
        font-weight: 700;
        margin-top: 8px;
    }

    .table-premium th {
        background-color: #f1f5f9;
        color: #475569;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 12px;
    }

    .table-premium td {
        padding: 12px;
        border-bottom: 1px solid #e2e8f0;
        font-size: 0.85rem;
    }

    .badge-mode {
        font-weight: 600;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.75rem;
    }
</style>

<div class="container-fluid py-4 dashboard-container">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="page-title mb-1"><i class="bi bi-speedometer2 text-danger me-2"></i>Accountant Dashboard</h3>
            <p class="text-muted mb-0">Real-time statistics, monthly collection runs, outstanding dues, and cash audits.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.fees.reports.index') }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-bar-graph"></i> Reporting Portal</a>
        </div>
    </div>

    @php
        $canViewFinanceReports = auth()->user()->hasPermission('view-finance-reports') || auth()->user()->hasRole('admin');
    @endphp

    <!-- 8 Stats Cards Grid -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-sm-4 col-md-3">
            <a href="{{ route('admin.fees.collection-register', ['group_by' => 'receipt', 'start_date' => $today, 'end_date' => $today]) }}" class="card-stat bg-gradient-blue">
                <div>
                    <span class="stat-label">Today's Collection</span>
                    <h3 class="stat-value">₹{{ number_format($todayCollection, 2) }}</h3>
                </div>
                <i class="bi bi-cash-stack card-stat-icon"></i>
            </a>
        </div>
        <div class="col-6 col-sm-4 col-md-3">
            @if($canViewFinanceReports)
            <a href="{{ route('admin.fees.reports.index', ['type' => 'refund_register', 'start_date' => $today, 'end_date' => $today]) }}" class="card-stat bg-gradient-red">
                <div>
                    <span class="stat-label">Today's Refund</span>
                    <h3 class="stat-value">₹{{ number_format($todayRefund, 2) }}</h3>
                </div>
                <i class="bi bi-arrow-counterclockwise card-stat-icon"></i>
            </a>
            @else
            <div class="card-stat bg-gradient-red">
                <div>
                    <span class="stat-label">Today's Refund</span>
                    <h3 class="stat-value">₹{{ number_format($todayRefund, 2) }}</h3>
                </div>
                <i class="bi bi-arrow-counterclockwise card-stat-icon"></i>
            </div>
            @endif
        </div>
        <div class="col-6 col-sm-4 col-md-3">
            @if($canViewFinanceReports)
            <a href="{{ route('admin.fees.reports.index', ['type' => 'discount_register', 'start_date' => $today, 'end_date' => $today]) }}" class="card-stat bg-gradient-green">
                <div>
                    <span class="stat-label">Today's Discounts</span>
                    <h3 class="stat-value">₹{{ number_format($todayDiscounts, 2) }}</h3>
                </div>
                <i class="bi bi-percent card-stat-icon"></i>
            </a>
            @else
            <div class="card-stat bg-gradient-green">
                <div>
                    <span class="stat-label">Today's Discounts</span>
                    <h3 class="stat-value">₹{{ number_format($todayDiscounts, 2) }}</h3>
                </div>
                <i class="bi bi-percent card-stat-icon"></i>
            </div>
            @endif
        </div>
        <div class="col-6 col-sm-4 col-md-3">
            @if($canViewFinanceReports)
            <a href="{{ route('admin.fees.reports.index', ['type' => 'outstanding_register']) }}" class="card-stat bg-gradient-yellow">
                <div>
                    <span class="stat-label">Today's Outstanding</span>
                    <h3 class="stat-value">₹{{ number_format($todayPending, 2) }}</h3>
                </div>
                <i class="bi bi-exclamation-triangle card-stat-icon"></i>
            </a>
            @else
            <div class="card-stat bg-gradient-yellow">
                <div>
                    <span class="stat-label">Today's Outstanding</span>
                    <h3 class="stat-value">₹{{ number_format($todayPending, 2) }}</h3>
                </div>
                <i class="bi bi-exclamation-triangle card-stat-icon"></i>
            </div>
            @endif
        </div>
        <div class="col-6 col-sm-4 col-md-3">
            @if($canViewFinanceReports)
            <a href="{{ route('admin.fees.reports.index', ['type' => 'demand_register', 'start_date' => $today, 'end_date' => $today]) }}" class="card-stat bg-gradient-indigo">
                <div>
                    <span class="stat-label">Today's Due (New)</span>
                    <h3 class="stat-value">₹{{ number_format($todayDue, 2) }}</h3>
                </div>
                <i class="bi bi-calculator card-stat-icon"></i>
            </a>
            @else
            <div class="card-stat bg-gradient-indigo">
                <div>
                    <span class="stat-label">Today's Due (New)</span>
                    <h3 class="stat-value">₹{{ number_format($todayDue, 2) }}</h3>
                </div>
                <i class="bi bi-calculator card-stat-icon"></i>
            </div>
            @endif
        </div>
        <div class="col-6 col-sm-4 col-md-3">
            @if($canViewFinanceReports)
            <a href="{{ route('admin.fees.reports.index', ['type' => 'late_fee_register', 'start_date' => $today, 'end_date' => $today]) }}" class="card-stat bg-gradient-purple">
                <div>
                    <span class="stat-label">Today's Late Fee</span>
                    <h3 class="stat-value">₹{{ number_format($todayLateFee, 2) }}</h3>
                </div>
                <i class="bi bi-clock-history card-stat-icon"></i>
            </a>
            @else
            <div class="card-stat bg-gradient-purple">
                <div>
                    <span class="stat-label">Today's Late Fee</span>
                    <h3 class="stat-value">₹{{ number_format($todayLateFee, 2) }}</h3>
                </div>
                <i class="bi bi-clock-history card-stat-icon"></i>
            </div>
            @endif
        </div>
        <div class="col-6 col-sm-4 col-md-3">
            <a href="{{ route('admin.students.index', ['created_date' => $today]) }}" class="card-stat bg-gradient-teal">
                <div>
                    <span class="stat-label">Today's Admissions</span>
                    <h3 class="stat-value">{{ $todayAdmissions }}</h3>
                </div>
                <i class="bi bi-person-plus card-stat-icon"></i>
            </a>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="row g-4 mb-4">
        <!-- Monthly Net Collection Chart -->
        <div class="col-lg-8">
            <div class="card card-premium p-4 h-100">
                <h5 class="fw-bold mb-3 text-dark">Monthly Collection vs Outstanding Trend</h5>
                <div style="height: 300px;">
                    <canvas id="monthlyCollectionTrendChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Payment Mode Distribution Chart -->
        <div class="col-lg-4">
            <div class="card card-premium p-4 h-100">
                <h5 class="fw-bold mb-3 text-dark">Payment Mode Share</h5>
                <div style="height: 300px; display: flex; justify-content: center; align-items: center;">
                    <canvas id="paymentModeChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Fee Head Collection Share -->
        <div class="col-md-6">
            <div class="card card-premium p-4 h-100">
                <h5 class="fw-bold mb-3 text-dark">Fee Head Distribution</h5>
                <div style="height: 250px;">
                    <canvas id="feeHeadChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Class-wise Collection Summary -->
        <div class="col-md-6">
            <div class="card card-premium p-4 h-100">
                <h5 class="fw-bold mb-3 text-dark">Top Classes Collection</h5>
                <div style="height: 250px;">
                    <canvas id="classWiseChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Tables & Quick Actions -->
    <div class="row g-4">
        <!-- Recent Collections -->
        <div class="col-lg-4">
            <div class="card card-premium p-4 h-100">
                <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-receipt text-danger me-2"></i>Recent Receipts</h5>
                <div class="table-responsive">
                    <table class="table table-premium mb-0">
                        <thead>
                            <tr>
                                <th>Receipt</th>
                                <th>Student</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentTransactions as $tx)
                                <tr>
                                    <td><strong>{{ $tx->receipt_no }}</strong><br><span class="text-muted small">{{ $tx->payment_date }}</span></td>
                                    <td>{{ $tx->student_name }}<br><span class="badge bg-secondary badge-mode">{{ strtoupper($tx->payment_mode) }}</span></td>
                                    <td class="text-end fw-bold text-success">₹{{ number_format($tx->final_amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted">No recent receipts today.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pending Approval Queue -->
        <div class="col-lg-4">
            <div class="card card-premium p-4 h-100">
                <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-clock-history text-warning me-2"></i>Pending Approval Queue</h5>
                <div class="table-responsive">
                    <table class="table table-premium mb-0">
                        <thead>
                            <tr>
                                <th>Student / Type</th>
                                <th>Detail/Rule</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pendingQueue as $q)
                                <tr>
                                    <td><strong>{{ $q->student_name }}</strong><br><span class="badge bg-{{ $q->type === 'Fee Reversal' ? 'danger' : 'info' }}">{{ $q->type }}</span></td>
                                    <td class="small">{{ $q->reason }}<br><span class="text-muted text-xs">{{ $q->created_at }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="text-center py-4 text-muted">No pending verification approvals.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Quick Actions Panel -->
        <div class="col-lg-4">
            <div class="card card-premium p-4 h-100">
                <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-lightning-fill text-yellow me-2"></i>Quick Actions</h5>
                <div class="d-flex flex-column gap-2">
                    <a href="{{ route('admin.fees.index') }}" class="btn btn-danger text-start py-2.5"><i class="bi bi-wallet2 me-2"></i> Collect Fees</a>
                    <a href="{{ route('admin.fees.reports.index', ['type' => 'cash_book']) }}" class="btn btn-outline-secondary text-start py-2.5"><i class="bi bi-journal-text me-2"></i> View Cash Book</a>
                    <a href="{{ Route::has('admin.fees.defaulters.index') ? route('admin.fees.defaulters.index') : (Route::has('admin.fees.defaulters') ? route('admin.fees.defaulters') : '#') }}" class="btn btn-outline-secondary text-start py-2.5"><i class="bi bi-exclamation-triangle me-2"></i> Defaulter Registry</a>
                    <a href="{{ route('admin.fees.reports.index') }}" class="btn btn-outline-secondary text-start py-2.5"><i class="bi bi-file-earmark-bar-graph me-2"></i> Financial Reports Portal</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // 1. Monthly Collection vs Outstanding Trend Chart
        const ctxMonthly = document.getElementById('monthlyCollectionTrendChart').getContext('2d');
        const monthlyLabels = {!! json_encode(collect($monthlyCollections)->pluck('label')) !!};
        const netCollectionData = {!! json_encode(collect($monthlyCollections)->pluck('net')) !!};
        const outstandingData = {!! json_encode(collect($outstandingTrend)->pluck('outstanding')) !!};

        new Chart(ctxMonthly, {
            type: 'bar',
            data: {
                labels: monthlyLabels,
                datasets: [
                    {
                        label: 'Net Collection (₹)',
                        data: netCollectionData,
                        backgroundColor: '#3b82f6',
                        borderColor: '#1d4ed8',
                        borderWidth: 1,
                        order: 2
                    },
                    {
                        label: 'Outstanding Dues (₹)',
                        data: outstandingData,
                        type: 'line',
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        borderWidth: 2.5,
                        fill: true,
                        order: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // 2. Payment Mode Share Chart
        const ctxMode = document.getElementById('paymentModeChart').getContext('2d');
        const modesData = {!! json_encode($paymentModes) !!};
        new Chart(ctxMode, {
            type: 'doughnut',
            data: {
                labels: ['Cash', 'UPI', 'Bank', 'Online'],
                datasets: [{
                    data: [modesData.cash, modesData.upi, modesData.bank, modesData.online],
                    backgroundColor: ['#ef4444', '#3b82f6', '#10b981', '#f59e0b'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // 3. Fee Head Distribution Chart
        const ctxHead = document.getElementById('feeHeadChart').getContext('2d');
        const headLabels = {!! json_encode(collect($headCollection)->pluck('head_name')) !!};
        const headData = {!! json_encode(collect($headCollection)->pluck('total')) !!};
        new Chart(ctxHead, {
            type: 'pie',
            data: {
                labels: headLabels.length ? headLabels : ['No Dues Collection'],
                datasets: [{
                    data: headData.length ? headData : [0],
                    backgroundColor: ['#6366f1', '#a855f7', '#ec4899', '#14b8a6', '#f43f5e'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });

        // 4. Class-wise Collection Chart
        const ctxClass = document.getElementById('classWiseChart').getContext('2d');
        const classLabels = {!! json_encode(collect($classCollection)->pluck('class_name')) !!};
        const classData = {!! json_encode(collect($classCollection)->pluck('total')) !!};
        new Chart(ctxClass, {
            type: 'bar',
            data: {
                labels: classLabels.length ? classLabels : ['No Data'],
                datasets: [{
                    label: 'Total Collected (₹)',
                    data: classData.length ? classData : [0],
                    backgroundColor: '#10b981',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true
                    }
                }
            }
        });
    });
</script>
@endsection