@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.budget-categories.index') }}">Budget Categories</a></li>
                        <li class="breadcrumb-item active">View Category</li>
                    </ol>
                </div>
                <h4 class="page-title">Budget Category Details</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Category Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th>ID:</th>
                                    <td>{{ $budgetCategory->id }}</td>
                                </tr>
                                <tr>
                                    <th>Name:</th>
                                    <td>{{ $budgetCategory->name }}</td>
                                </tr>
                                <tr>
                                    <th>Description:</th>
                                    <td>{{ $budgetCategory->description ?: 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Type:</th>
                                    <td>{{ $types[$budgetCategory->type] ?? ucfirst($budgetCategory->type) }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th>Default Allocation %:</th>
                                    <td>{{ $budgetCategory->default_allocation_percentage }}%</td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <span class="badge 
                                            @if($budgetCategory->is_active) bg-success
                                            @else bg-secondary
                                            @endif">
                                            {{ $budgetCategory->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Created By:</th>
                                    <td>{{ $budgetCategory->creator->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Created At:</th>
                                    <td>{{ $budgetCategory->created_at->format('d-m-Y H:i:s') }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <h6>Statistics</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <th>Total Expenses:</th>
                                    <td>{{ $stats['total_expenses'] }}</td>
                                </tr>
                                <tr>
                                    <th>Total Budgets:</th>
                                    <td>{{ $stats['total_budgets'] }}</td>
                                </tr>
                                <tr>
                                    <th>Active Budgets:</th>
                                    <td>{{ $stats['active_budgets'] }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="{{ route('admin.budget-categories.edit', $budgetCategory) }}" class="btn btn-primary me-2">Edit</a>
                        <a href="{{ route('admin.budget-categories.toggle-active', $budgetCategory) }}" class="btn btn-{{ $budgetCategory->is_active ? 'warning' : 'success' }}">
                            {{ $budgetCategory->is_active ? 'Deactivate' : 'Activate' }}
                        </a>
                        <form action="{{ route('admin.budget-categories.destroy', $budgetCategory) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this budget category?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                        <a href="{{ route('admin.budget-categories.index') }}" class="btn btn-secondary">Back to List</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection