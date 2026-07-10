@extends('layouts.teacher')

@section('title', 'My Salaries - Teacher Panel')

@section('content')
<div class="container-fluid mt-4 text-dark">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="font-weight-bold text-success"><i class="fas fa-wallet me-2"></i> My Salary & Payouts</h2>
            <p class="text-secondary">View your monthly payslips, earnings, allowances, and deductions.</p>
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0 rounded-lg mb-4">
        <div class="card-header bg-success text-white py-3">
            <h5 class="mb-0 font-weight-bold"><i class="fas fa-list me-2"></i> Salary Payout History</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-4">Period</th>
                            <th>Pay Date</th>
                            <th>Pay Scale</th>
                            <th class="text-end">Basic Salary</th>
                            <th class="text-end">Gross Earnings</th>
                            <th class="text-end">Deductions</th>
                            <th class="text-end">Net Payout</th>
                            <th>Payment Method</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($salaries as $salary)
                            <tr>
                                <td class="ps-4">
                                    @if($salary->pay_month && $salary->pay_year)
                                        {{ \Carbon\Carbon::create($salary->pay_year, $salary->pay_month, 1)->format('M Y') }}
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td class="font-weight-bold">
                                    {{ $salary->payment_date ? $salary->payment_date->format('M d, Y') : $salary->created_at->format('M d, Y') }}
                                </td>
                                <td>
                                    <span class="badge bg-secondary px-2 py-1">{{ $salary->pay_scale ?? 'Standard' }}</span>
                                </td>
                                <td class="text-end text-dark font-weight-bold">₹{{ number_format($salary->basic_salary, 2) }}</td>
                                <td class="text-end text-dark">₹{{ number_format($salary->gross_salary, 2) }}</td>
                                <td class="text-end text-danger">₹{{ number_format($salary->pf_amount + $salary->esi_amount + $salary->tax_deduction + $salary->other_deductions + ($salary->attendance_deduction_amount ?? 0), 2) }}</td>
                                <td class="text-end text-success font-weight-bold">₹{{ number_format($salary->net_salary, 2) }}</td>
                                <td class="text-capitalize">{{ str_replace('_', ' ', $salary->payment_method) }}</td>
                                <td class="text-end pe-4">
                                    <a href="{{ route('teacher.salaries.pdf', $salary->id) }}" class="btn btn-sm btn-outline-danger px-3 rounded-pill">
                                        <i class="fas fa-file-pdf me-1"></i> Download Slip
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-secondary">
                                    <div class="mb-3">
                                        <i class="fas fa-receipt" style="font-size: 3rem; opacity: 0.5;"></i>
                                    </div>
                                    <h5 class="text-dark font-weight-bold">No Salary Payouts Yet</h5>
                                    <p class="text-secondary mb-0">Your monthly salary logs will appear here once processed by the school administration.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($salaries->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $salaries->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
