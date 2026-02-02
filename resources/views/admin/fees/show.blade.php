@extends('layouts.admin')

@section('title', 'Fee Record Details')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Fee Record Details</h4>
                    <div>
                        <a href="{{ route('admin.fees.edit', $fee) }}" class="btn btn-warning">Edit</a>
                        <a href="{{ route('admin.fees.index') }}" class="btn btn-secondary">Back to List</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">ID</label>
                                <p class="form-control-plaintext">#{{ $fee->id }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Status</label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-{{ $fee->status == 'paid' ? 'success' : ($fee->status == 'partial' ? 'warning' : 'danger') }}">
                                        {{ ucfirst($fee->status) }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Student</label>
                                <p class="form-control-plaintext">{{ $fee->student->name ?? 'N/A' }} ({{ $fee->student->roll_number ?? 'N/A' }})</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Fee Structure</label>
                                <p class="form-control-plaintext">{{ $fee->feeStructure->name ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Academic Year</label>
                                <p class="form-control-plaintext">{{ $fee->academic_year }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Term</label>
                                <p class="form-control-plaintext">{{ $fee->term }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Total Amount</label>
                                <p class="form-control-plaintext">â‚¹{{ number_format($fee->amount, 2) }}</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Paid Amount</label>
                                <p class="form-control-plaintext">â‚¹{{ number_format($fee->paid_amount, 2) }}</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Due Amount</label>
                                <p class="form-control-plaintext">â‚¹{{ number_format($fee->due_amount, 2) }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Due Date</label>
                                <p class="form-control-plaintext">{{ $fee->due_date->format('d M Y') }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Created At</label>
                                <p class="form-control-plaintext">{{ $fee->created_at->format('d M Y h:i A') }}</p>
                            </div>
                        </div>
                    </div>
                    
                    @if($fee->notes)
                    <div class="mb-3">
                        <label class="form-label fw-bold">Notes</label>
                        <p class="form-control-plaintext">{{ $fee->notes }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
