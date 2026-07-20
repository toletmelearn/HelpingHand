@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item active">Expenses</li>
                    </ol>
                </div>
                <h4 class="page-title">Expenses</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        Expense Records
                        <a href="{{ route('admin.expenses.create') }}" class="btn btn-primary btn-sm float-end">Add New Expense</a>
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <div class="row mb-3">
                        <form method="GET" action="{{ route('admin.expenses.index') }}">
                            <div class="col-md-12">
                                <div class="row g-2">
                                    <div class="col-md-2">
                                        <select name="budget_id" class="form-control" onchange="this.form.submit()">
                                            <option value="">All Budgets</option>
                                            @foreach($budgets as $id => $name)
                                                <option value="{{ $id }}" {{ request('budget_id') == $id ? 'selected' : '' }}>
                                                    {{ $name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select name="category_id" class="form-control" onchange="this.form.submit()">
                                            <option value="">All Categories</option>
                                            @foreach($categories as $id => $name)
                                                <option value="{{ $id }}" {{ request('category_id') == $id ? 'selected' : '' }}>
                                                    {{ $name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select name="status" class="form-control" onchange="this.form.submit()">
                                            <option value="">All Statuses</option>
                                            @foreach($statuses as $key => $value)
                                                <option value="{{ $key }}" {{ request('status') == $key ? 'selected' : '' }}>
                                                    {{ $value }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select name="payment_method" class="form-control" onchange="this.form.submit()">
                                            <option value="">All Payment Methods</option>
                                            @foreach($paymentMethods as $key => $value)
                                                <option value="{{ $key }}" {{ request('payment_method') == $key ? 'selected' : '' }}>
                                                    {{ $value }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="input-group">
                                            <input type="text" name="search" class="form-control" placeholder="Search expenses..." value="{{ request('search') }}">
                                            <button class="btn btn-outline-secondary" type="submit">Search</button>
                                            <a href="{{ route('admin.expenses.index') }}" class="btn btn-outline-secondary">Clear</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Expenses Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Budget</th>
                                    <th>Category</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Vendor</th>
                                    <th>Payment Method</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($expenses as $expense)
                                <tr>
                                    <td>{{ $expense->id }}</td>
                                    <td>{{ $expense->title }}</td>
                                    <td>{{ $expense->budget->name ?? 'N/A' }}</td>
                                    <td>{{ $expense->category->name ?? 'N/A' }}</td>
                                    <td>₹{{ number_format($expense->amount, 2) }}</td>
                                    <td>{{ $expense->expense_date->format('d-m-Y') }}</td>
                                    <td>
                                        <span class="badge 
                                            @if($expense->status == 'pending') bg-warning
                                            @elseif($expense->status == 'approved') bg-success
                                            @elseif($expense->status == 'rejected') bg-danger
                                            @endif">
                                            {{ ucfirst($expense->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $expense->vendor_name }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $expense->payment_method)) }}</td>
                                    <td>{{ $expense->creator->name ?? 'N/A' }}</td>
                                    <td>
                                        <a href="{{ route('admin.expenses.show', $expense) }}" class="btn btn-info btn-sm">View</a>
                                        @if($expense->canBeModified())
                                            <a href="{{ route('admin.expenses.edit', $expense) }}" class="btn btn-primary btn-sm">Edit</a>
                                        @endif
                                        @if($expense->status == 'pending')
                                            <a href="{{ route('admin.expenses.approve', $expense) }}" class="btn btn-success btn-sm">Approve</a>
                                            <a href="{{ route('admin.expenses.reject', $expense) }}" class="btn btn-danger btn-sm">Reject</a>
                                        @endif
                                        @if($expense->canBeModified())
                                            <form action="{{ route('admin.expenses.destroy', $expense) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this expense?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="11" class="text-center">No expenses found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-center">
                        {{ $expenses->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection