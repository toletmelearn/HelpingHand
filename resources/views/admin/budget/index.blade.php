@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.budget.index') }}">Budget</a></li>
                        <li class="breadcrumb-item active">Budget Plans</li>
                    </ol>
                </div>
                <h4 class="page-title">Budget Plans</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="card-title mb-0">Budget Plans</h5>
                        </div>
                        <div class="col-md-6 text-end">
                            <a href="{{ route('admin.budgets.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Create New Budget
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <form method="GET" action="{{ route('admin.budgets.index') }}" class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label for="fiscal_year" class="form-label">Fiscal Year</label>
                            <select name="fiscal_year" id="fiscal_year" class="form-select">
                                <option value="">All Years</option>
                                @foreach($years as $year)
                                    <option value="{{ $year }}" {{ request('fiscal_year') == $year ? 'selected' : '' }}>
                                        {{ $year }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-select">
                                <option value="">All Statuses</option>
                                @foreach($statuses as $key => $label)
                                    <option value="{{ $key }}" {{ request('status') == $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" name="search" id="search" class="form-control" placeholder="Search budgets..." value="{{ request('search') }}">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Filter</button>
                            <a href="{{ route('admin.budgets.index') }}" class="btn btn-secondary">Clear</a>
                        </div>
                    </form>

                    <!-- Budget Table -->
                    <div class="table-responsive">
                        <table class="table table-centered table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Fiscal Year</th>
                                    <th>Total Amount</th>
                                    <th>Allocated</th>
                                    <th>Spent</th>
                                    <th>Remaining</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($budgets as $budget)
                                <tr>
                                    <td>
                                        <a href="{{ route('budgets.show', $budget) }}" class="text-body fw-bold">
                                            {{ $budget->name }}
                                        </a>
                                        @if($budget->isOverBudget())
                                            <span class="badge badge-danger ms-1">OVER BUDGET</span>
                                        @endif
                                    </td>
                                    <td>{{ $budget->fiscal_year }}</td>
                                    <td>â‚¹{{ number_format($budget->total_amount, 2) }}</td>
                                    <td>â‚¹{{ number_format($budget->allocated_amount, 2) }}</td>
                                    <td>â‚¹{{ number_format($budget->spent_amount, 2) }}</td>
                                    <td>â‚¹{{ number_format($budget->allocated_amount - $budget->spent_amount, 2) }}</td>
                                    <td>
                                        <span class="badge badge-{{ $budget->status == 'approved' ? 'success' : ($budget->status == 'draft' ? 'secondary' : ($budget->status == 'locked' ? 'warning' : 'danger')) }}">
                                            {{ $budget->status_label }}
                                        </span>
                                    </td>
                                    <td>{{ $budget->creator->name ?? 'N/A' }}</td>
                                    <td>
                                        <div class="btn-group dropdown">
                                            <button type="button" class="btn btn-outline-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                Actions
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="{{ route('budgets.show', $budget) }}">
                                                    <i class="fas fa-eye me-1"></i> View
                                                </a>
                                                @if($budget->canBeModified())
                                                <a class="dropdown-item" href="{{ route('budgets.edit', $budget) }}">
                                                    <i class="fas fa-edit me-1"></i> Edit
                                                </a>
                                                <form method="POST" action="{{ route('budgets.destroy', $budget) }}" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Are you sure you want to delete this budget?')">
                                                        <i class="fas fa-trash me-1"></i> Delete
                                                    </button>
                                                </form>
                                                @endif
                                                @if($budget->status === 'draft')
                                                <a class="dropdown-item" href="{{ route('budget.approve', $budget) }}" onclick="return confirm('Are you sure you want to approve this budget?')">
                                                    <i class="fas fa-check me-1"></i> Approve
                                                </a>
                                                @elseif($budget->status === 'approved')
                                                <a class="dropdown-item" href="{{ route('budget.lock', $budget) }}" onclick="return confirm('Are you sure you want to lock this budget?')">
                                                    <i class="fas fa-lock me-1"></i> Lock
                                                </a>
                                                @elseif($budget->status === 'locked')
                                                <a class="dropdown-item" href="{{ route('budget.close', $budget) }}" onclick="return confirm('Are you sure you want to close this budget?')">
                                                    <i class="fas fa-times me-1"></i> Close
                                                </a>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9" class="text-center">No budgets found</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="mt-4">
                        {{ $budgets->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
