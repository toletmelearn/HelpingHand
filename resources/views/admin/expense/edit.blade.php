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
                        <li class="breadcrumb-item active">Edit Expense</li>
                    </ol>
                </div>
                <h4 class="page-title">Edit Expense</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Expense Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.expenses.update', $expense) }}">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="budget_id" class="form-label">Budget *</label>
                                    <select name="budget_id" id="budget_id" class="form-control @error('budget_id') is-invalid @enderror" required>
                                        <option value="">Select Budget</option>
                                        @foreach($budgets as $budget)
                                            <option value="{{ $budget->id }}" 
                                                {{ old('budget_id', $expense->budget_id) == $budget->id ? 'selected' : '' }}>
                                                {{ $budget->name }} ({{ $budget->getAllocatedAmount() }} / {{ $budget->total_amount }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('budget_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="budget_category_id" class="form-label">Category *</label>
                                    <select name="budget_category_id" id="budget_category_id" class="form-control @error('budget_category_id') is-invalid @enderror" required>
                                        <option value="">Select Category</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" 
                                                {{ old('budget_category_id', $expense->budget_category_id) == $category->id ? 'selected' : '' }}>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('budget_category_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Title *</label>
                                    <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror" 
                                           value="{{ old('title', $expense->title) }}" required>
                                    @error('title')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="amount" class="form-label">Amount *</label>
                                    <input type="number" step="0.01" name="amount" id="amount" class="form-control @error('amount') is-invalid @enderror" 
                                           value="{{ old('amount', $expense->amount) }}" min="0.01" required>
                                    @error('amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="expense_date" class="form-label">Expense Date *</label>
                                    <input type="date" name="expense_date" id="expense_date" class="form-control @error('expense_date') is-invalid @enderror" 
                                           value="{{ old('expense_date', $expense->expense_date->format('Y-m-d')) }}" required>
                                    @error('expense_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_method" class="form-label">Payment Method *</label>
                                    <select name="payment_method" id="payment_method" class="form-control @error('payment_method') is-invalid @enderror" required>
                                        <option value="">Select Payment Method</option>
                                        <option value="cash" {{ old('payment_method', $expense->payment_method) == 'cash' ? 'selected' : '' }}>Cash</option>
                                        <option value="bank_transfer" {{ old('payment_method', $expense->payment_method) == 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                        <option value="cheque" {{ old('payment_method', $expense->payment_method) == 'cheque' ? 'selected' : '' }}>Cheque</option>
                                        <option value="credit_card" {{ old('payment_method', $expense->payment_method) == 'credit_card' ? 'selected' : '' }}>Credit Card</option>
                                        <option value="online" {{ old('payment_method', $expense->payment_method) == 'online' ? 'selected' : '' }}>Online Payment</option>
                                    </select>
                                    @error('payment_method')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="vendor_name" class="form-label">Vendor Name</label>
                                    <input type="text" name="vendor_name" id="vendor_name" class="form-control @error('vendor_name') is-invalid @enderror" 
                                           value="{{ old('vendor_name', $expense->vendor_name) }}">
                                    @error('vendor_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="receipt_number" class="form-label">Receipt Number</label>
                                    <input type="text" name="receipt_number" id="receipt_number" class="form-control @error('receipt_number') is-invalid @enderror" 
                                           value="{{ old('receipt_number', $expense->receipt_number) }}">
                                    @error('receipt_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea name="description" id="description" class="form-control @error('description') is-invalid @enderror" 
                                      rows="3">{{ old('description', $expense->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="{{ route('admin.expenses.show', $expense) }}" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-success">Update Expense</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection