@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('budgets.index') }}">Budgets</a></li>
                        <li class="breadcrumb-item active">{{ $budget->name }}</li>
                    </ol>
                </div>
                <h4 class="page-title">Budget: {{ $budget->name }}</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-8">
                            <h5 class="card-title mb-0">{{ $budget->name }}</h5>
                        </div>
                        <div class="col-md-4 text-end">
                            @if($budget->canBeModified())
                                <a href="{{ route('budgets.edit', $budget) }}" class="btn btn-primary btn-sm">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Fiscal Year</label>
                                <input type="text" class="form-control" value="{{ $budget->fiscal_year }}" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <input type="text" class="form-control" value="{{ $budget->status_label }}" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Total Amount</label>
                                <input type="text" class="form-control" value="₹{{ number_format($budget->total_amount, 2) }}" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Allocated Amount</label>
                                <input type="text" class="form-control" value="₹{{ number_format($budget->allocated_amount, 2) }}" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Spent Amount</label>
                                <input type="text" class="form-control" value="₹{{ number_format($budget->spent_amount, 2) }}" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Created By</label>
                                <input type="text" class="form-control" value="{{ $budget->creator->name ?? 'N/A' }}" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Created Date</label>
                                <input type="text" class="form-control" value="{{ $budget->created_at->format('d M Y h:i A') }}" readonly>
                            </div>
                        </div>
                    </div>
                    
                    @if($budget->approval_date)
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Approved By</label>
                                <input type="text" class="form-control" value="{{ $budget->approver->name ?? 'N/A' }}" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Approval Date</label>
                                <input type="text" class="form-control" value="{{ $budget->approval_date->format('d M Y') }}" readonly>
                            </div>
                        </div>
                    </div>
                    @endif
                    
                    @if($budget->isOverBudget())
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> This budget is over budget by ₹{{ number_format($budget->getOverBudgetAmount(), 2) }}
                    </div>
                    @endif
                    
                    <div class="mb-4">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" rows="3" readonly>{{ $budget->description }}</textarea>
                    </div>
                    
                    <!-- Action buttons based on status -->
                    <div class="d-flex gap-2 mb-4">
                        @if($budget->status === 'draft')
                            <a href="{{ route('budget.approve', $budget) }}" class="btn btn-success" onclick="return confirm('Are you sure you want to approve this budget?')">
                                <i class="fas fa-check"></i> Approve Budget
                            </a>
                        @elseif($budget->status === 'approved')
                            <a href="{{ route('budget.lock', $budget) }}" class="btn btn-warning" onclick="return confirm('Are you sure you want to lock this budget?')">
                                <i class="fas fa-lock"></i> Lock Budget
                            </a>
                        @elseif($budget->status === 'locked')
                            <a href="{{ route('budget.close', $budget) }}" class="btn btn-danger" onclick="return confirm('Are you sure you want to close this budget?')">
                                <i class="fas fa-times"></i> Close Budget
                            </a>
                        @endif
                        
                        @if($budget->canBeModified())
                            <a href="{{ route('budgets.edit', $budget) }}" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Edit Budget
                            </a>
                        @endif
                    </div>
                </div>
            </div>
            
            <!-- Category Allocation Section -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Category Allocation</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Allocated Amount</th>
                                    <th>Spent Amount</th>
                                    <th>Remaining Amount</th>
                                    <th>Utilization %</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categoryStats as $stat)
                                <tr>
                                    <td>{{ $stat['category']->name }}</td>
                                    <td>₹{{ number_format($stat['allocated'], 2) }}</td>
                                    <td>₹{{ number_format($stat['spent'], 2) }}</td>
                                    <td>₹{{ number_format($stat['remaining'], 2) }}</td>
                                    <td>{{ $stat['utilization'] }}%</td>
                                    <td>
                                        <span class="badge badge-{{ $stat['utilization'] > 90 ? 'danger' : ($stat['utilization'] > 75 ? 'warning' : 'success') }}">
                                            {{ $stat['utilization'] > 100 ? 'Overspent' : ($stat['utilization'] > 90 ? 'Critical' : ($stat['utilization'] > 75 ? 'Warning' : 'Good')) }}
                                        </span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center">No category allocations found</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Expenses Section -->
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-8">
                            <h5 class="card-title mb-0">Expenses</h5>
                        </div>
                        <div class="col-md-4 text-end">
                            <a href="{{ route('expenses.create', ['budget_id' => $budget->id]) }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Add Expense
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Vendor</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($budget->expenses as $expense)
                                <tr>
                                    <td>{{ $expense->title }}</td>
                                    <td>{{ $expense->category->name }}</td>
                                    <td>₹{{ number_format($expense->amount, 2) }}</td>
                                    <td>{{ $expense->expense_date->format('d M Y') }}</td>
                                    <td>
                                        <span class="badge badge-{{ $expense->status == 'approved' ? 'success' : ($expense->status == 'pending' ? 'warning' : 'danger') }}">
                                            {{ $expense->status_label }}
                                        </span>
                                    </td>
                                    <td>{{ $expense->vendor_name }}</td>
                                    <td>
                                        <a href="{{ route('expenses.show', $expense) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center">No expenses found for this budget</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection