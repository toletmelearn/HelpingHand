@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.budgets.index') }}">Budgets</a></li>
                        <li class="breadcrumb-item active">Create Budget</li>
                    </ol>
                </div>
                <h4 class="page-title">Create New Budget</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Budget Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('budgets.store') }}">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Budget Name *</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="fiscal_year" class="form-label">Fiscal Year *</label>
                                    <select class="form-control @error('fiscal_year') is-invalid @enderror" id="fiscal_year" name="fiscal_year" required>
                                        <option value="">Select Fiscal Year</option>
                                        @for($year = $currentYear; $year <= $currentYear + 5; $year++)
                                            <option value="{{ $year }}" {{ old('fiscal_year') == $year ? 'selected' : '' }}>
                                                {{ $year }}-{{ $year + 1 }}
                                            </option>
                                        @endfor
                                    </select>
                                    @error('fiscal_year')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="total_amount" class="form-label">Total Budget Amount *</label>
                            <input type="number" step="0.01" class="form-control @error('total_amount') is-invalid @enderror" id="total_amount" name="total_amount" value="{{ old('total_amount') }}" required>
                            @error('total_amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <h5 class="mt-4 mb-3">Category Allocations</h5>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered" id="allocations-table">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Allocation Amount</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="allocations-body">
                                    <tr>
                                        <td>
                                            <select class="form-control category-select" name="allocations[0][category_id]" required>
                                                <option value="">Select Category</option>
                                                @foreach($categories as $category)
                                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" class="form-control allocation-amount" name="allocations[0][amount]" placeholder="Amount">
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-danger btn-sm remove-row" disabled>Remove</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-secondary" id="add-allocation">Add Category</button>
                            <div>
                                <span class="me-2">Total Allocated:</span>
                                <strong id="total-allocated">â‚¹0.00</strong>
                                <span class="mx-2">/</span>
                                <strong id="total-budget-amount">â‚¹{{ number_format(old('total_amount', 0), 2) }}</strong>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">Create Budget</button>
                            <a href="{{ route('admin.budgets.index') }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let allocationIndex = 0;
    
    // Add allocation row
    document.getElementById('add-allocation').addEventListener('click', function() {
        allocationIndex++;
        const newRow = `
            <tr>
                <td>
                    <select class="form-control category-select" name="allocations[${allocationIndex}][category_id]" required>
                        <option value="">Select Category</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ addslashes($category->name) }}</option>
                        @endforeach
                    </select>
                </td>
                <td>
                    <input type="number" step="0.01" class="form-control allocation-amount" name="allocations[${allocationIndex}][amount]" placeholder="Amount">
                </td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm remove-row">Remove</button>
                </td>
            </tr>
        `;
        
        document.getElementById('allocations-body').insertAdjacentHTML('beforeend', newRow);
        attachEventListeners();
    });
    
    // Attach event listeners for removal
    function attachEventListeners() {
        document.querySelectorAll('.remove-row').forEach(button => {
            button.addEventListener('click', function() {
                if (document.querySelectorAll('#allocations-body tr').length > 1) {
                    this.closest('tr').remove();
                    calculateTotal();
                }
            });
        });
        
        document.querySelectorAll('.allocation-amount').forEach(input => {
            input.addEventListener('input', calculateTotal);
        });
    }
    
    // Calculate total allocated amount
    function calculateTotal() {
        let total = 0;
        document.querySelectorAll('.allocation-amount').forEach(input => {
            if (input.value) {
                total += parseFloat(input.value) || 0;
            }
        });
        
        document.getElementById('total-allocated').textContent = 'â‚¹' + total.toFixed(2);
        
        // Compare with total budget amount
        const budgetAmount = parseFloat(document.getElementById('total_amount').value) || 0;
        const allocatedAmount = total;
        
        if (allocatedAmount > budgetAmount) {
            document.getElementById('total-allocated').style.color = 'red';
        } else if (allocatedAmount === budgetAmount) {
            document.getElementById('total-allocated').style.color = 'green';
        } else {
            document.getElementById('total-allocated').style.color = 'black';
        }
    }
    
    // Update budget amount display when input changes
    document.getElementById('total_amount').addEventListener('input', function() {
        document.getElementById('total-budget-amount').textContent = 'â‚¹' + (parseFloat(this.value) || 0).toFixed(2);
        calculateTotal();
    });
    
    attachEventListeners();
});
</script>
@endsection
