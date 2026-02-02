@extends('layouts.admin')

@section('title', 'Fee Structure Details')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Fee Structure Details</h4>
                    <div>
                        <a href="{{ route('admin.fee-structures.edit', $feeStructure) }}" class="btn btn-warning">Edit</a>
                        <a href="{{ route('admin.fee-structures.index') }}" class="btn btn-secondary">Back to List</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">ID</label>
                                <p class="form-control-plaintext">#{{ $feeStructure->id }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Status</label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-{{ $feeStructure->is_active ? 'success' : 'danger' }}">
                                        {{ $feeStructure->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Name</label>
                                <p class="form-control-plaintext">{{ $feeStructure->name }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Class</label>
                                <p class="form-control-plaintext">{{ $feeStructure->class_name }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Term</label>
                                <p class="form-control-plaintext">{{ $feeStructure->term }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Frequency</label>
                                <p class="form-control-plaintext">{{ $feeStructure->frequency }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Amount</label>
                                <p class="form-control-plaintext">â‚¹{{ number_format($feeStructure->amount, 2) }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Created At</label>
                                <p class="form-control-plaintext">{{ $feeStructure->created_at->format('d M Y h:i A') }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Valid From</label>
                                <p class="form-control-plaintext">{{ $feeStructure->valid_from->format('d M Y') }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Valid Until</label>
                                <p class="form-control-plaintext">{{ $feeStructure->valid_until->format('d M Y') }}</p>
                            </div>
                        </div>
                    </div>
                    
                    @if($feeStructure->description)
                    <div class="mb-3">
                        <label class="form-label fw-bold">Description</label>
                        <p class="form-control-plaintext">{{ $feeStructure->description }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
