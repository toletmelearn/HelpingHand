@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.expenses.index') }}">Expenses</a></li>
                        <li class="breadcrumb-item active">View Expense</li>
                    </ol>
                </div>
                <h4 class="page-title">Expense Details</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Expense Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th>ID:</th>
                                    <td>{{ $expense->id }}</td>
                                </tr>
                                <tr>
                                    <th>Title:</th>
                                    <td>{{ $expense->title }}</td>
                                </tr>
                                <tr>
                                    <th>Description:</th>
                                    <td>{{ $expense->description ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Amount:</th>
                                    <td>₹{{ number_format($expense->amount, 2) }}</td>
                                </tr>
                                <tr>
                                    <th>Expense Date:</th>
                                    <td>{{ $expense->expense_date->format('d-m-Y') }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th>Budget:</th>
                                    <td>{{ $expense->budget->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Category:</th>
                                    <td>{{ $expense->category->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <span class="badge 
                                            @if($expense->status == 'pending') bg-warning
                                            @elseif($expense->status == 'approved') bg-success
                                            @elseif($expense->status == 'rejected') bg-danger
                                            @endif">
                                            {{ ucfirst($expense->status) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Payment Method:</th>
                                    <td>{{ ucfirst(str_replace('_', ' ', $expense->payment_method)) }}</td>
                                </tr>
                                <tr>
                                    <th>Receipt Number:</th>
                                    <td>{{ $expense->receipt_number ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Vendor Name:</th>
                                    <td>{{ $expense->vendor_name ?: 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th>Created By:</th>
                                    <td>{{ $expense->creator->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Created At:</th>
                                    <td>{{ $expense->created_at->format('d-m-Y H:i:s') }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th>Approved By:</th>
                                    <td>{{ $expense->approver->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Approved At:</th>
                                    <td>{{ $expense->approved_at ? $expense->approved_at->format('d-m-Y H:i:s') : 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        @if($expense->canBeModified())
                            <a href="{{ route('admin.expenses.edit', $expense) }}" class="btn btn-primary me-2">Edit</a>
                        @endif
                        @if($expense->status == 'pending')
                            <a href="{{ route('admin.expenses.approve', $expense) }}" class="btn btn-success me-2" onclick="return confirm('Are you sure you want to approve this expense?')">Approve</a>
                            <a href="{{ route('admin.expenses.reject', $expense) }}" class="btn btn-danger me-2" onclick="return confirm('Are you sure you want to reject this expense?')">Reject</a>
                        @endif
                        @if($expense->canBeModified())
                            <form action="{{ route('admin.expenses.destroy', $expense) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this expense?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        @endif
                        <a href="{{ route('admin.expenses.index') }}" class="btn btn-secondary">Back to List</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection