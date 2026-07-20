@extends('layouts.admin')

@section('title', 'Student Fee Dashboard - ' . $student->name)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Student Fee Dashboard</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.fees.index') }}">Fee Collection</a></li>
                        <li class="breadcrumb-item active">Student: {{ $student->name }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Info Card -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Student Information</h5>
                    <a href="{{ route('admin.fees.index') }}" class="btn btn-secondary btn-sm">← Back to Search</a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <p><strong>Name:</strong> {{ $student->name }}</p>
                        </div>
                        <div class="col-md-3">
                            <p><strong>Admission No:</strong> {{ $student->admission_no ?: 'N/A' }}</p>
                        </div>
                        <div class="col-md-3">
                            <p><strong>Class:</strong> {{ $student->schoolClass->name ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-3">
                            <p><strong>Mobile:</strong> {{ $student->mobile ?: 'N/A' }}</p>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-3">
                            <p><strong>Father Name:</strong> {{ $student->father_name }}</p>
                        </div>
                        <div class="col-md-3">
                            <p><strong>Mother Name:</strong> {{ $student->mother_name }}</p>
                        </div>
                        <div class="col-md-3">
                            <p><strong>Roll Number:</strong> {{ $student->roll_number ?: 'N/A' }}</p>
                        </div>
                        <div class="col-md-3">
                            <p><strong>Guardian Name:</strong> {{ $student->guardian_name ?: 'N/A' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fee Summary Card -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Fee Summary</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <p class="text-muted mb-1">Total Paid So Far</p>
                            <h4 class="text-success">₹{{ number_format($totalPaid ?? 0, 2) }}</h4>
                        </div>
                        <div class="col-md-4">
                            <p class="text-muted mb-1">Outstanding Balance (Due)</p>
                            <h4 class="text-danger">₹{{ number_format($outstandingDues ?? 0, 2) }}</h4>
                        </div>
                        <div class="col-md-4">
                            <p class="text-muted mb-1">Last Transaction</p>
                            @if(isset($lastTransaction) && $lastTransaction)
                                <h5>₹{{ number_format($lastTransaction->credit, 2) }} <small class="text-muted">on {{ \Carbon\Carbon::parse($lastTransaction->date)->format('d-m-Y') }}</small></h5>
                            @else
                                <h5 class="text-muted">No prior payment recorded</h5>
                            @endif
                        </div>
                    </div>
                    <div class="mt-2">
                        <a href="{{ route('admin.fees.collect.form', $student->id) }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-money-bill-wave me-1"></i> Collect Fee
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fee Structure Info Card -->
    @if(isset($feeStructure) && $feeStructure)
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Assigned Fee Structure</h5>
                    <span class="badge bg-info">{{ $feeStructure->class_name }} - {{ $feeStructure->academic_year }}</span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <p><strong>Frequency:</strong> {{ ucfirst($feeStructure->frequency) }}</p>
                        </div>
                        <div class="col-md-3">
                            <p><strong>Status:</strong> <span class="badge bg-{{ $feeStructure->status == 'active' ? 'success' : 'secondary' }}">{{ ucfirst($feeStructure->status) }}</span></p>
                        </div>
                        <div class="col-md-3">
                            <p><strong>Total Amount:</strong> ₹{{ number_format($feeStructure->total_amount, 2) }}</p>
                        </div>
                        <div class="col-md-3">
                            <p><strong>Created:</strong> {{ $feeStructure->created_at->format('d-m-Y') }}</p>
                        </div>
                    </div>
                    
                    <h6 class="mt-3">Fee Items:</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Fee Type</th>
                                    <th>Amount</th>
                                    <th>Due Day</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($feeStructure->feeStructureItems as $item)
                                <tr>
                                    <td>{{ $item->feeType->name ?? 'N/A' }}</td>
                                    <td>₹{{ number_format($item->amount, 2) }}</td>
                                    <td>{{ $item->due_day ?: 'N/A' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Fee Collection History Card -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Fee Collection History</h5>
                    <div>
                        <a href="{{ route('admin.fees.collect.form', $student->id) }}" class="btn btn-success">Collect New Fee</a>
                    </div>
                </div>
                <div class="card-body">
                    @if(isset($feeCollections) && count($feeCollections) > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Receipt No</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Payment Mode</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($feeCollections as $collection)
                                <tr>
                                    <td>{{ $collection->receipt_no }}</td>
                                    <td>{{ $collection->payment_date->format('d-m-Y') }}</td>
                                    <td>₹{{ number_format($collection->final_amount, 2) }}</td>
                                    <td><span class="badge bg-primary">{{ ucfirst($collection->payment_mode) }}</span></td>
                                    <td><span class="badge bg-success">Paid</span></td>
                                    <td>
                                        <a href="{{ route('admin.fees.receipt', $collection->id) }}" class="btn btn-sm btn-info" target="_blank">Receipt</a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-4">
                        <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No fee collections recorded yet for this student.</p>
                        <a href="{{ route('admin.fees.index') }}" class="btn btn-primary">Collect Fee Now</a>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Action Card -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h5 class="card-title">Quick Actions</h5>
                    <div class="d-flex justify-content-center gap-3 flex-wrap">
                        <a href="{{ route('admin.fees.collect.form', $student->id) }}" class="btn btn-success btn-lg">Collect Fee</a>
                        @if(isset($feeCollections) && count($feeCollections) > 0)
                        <a href="{{ route('admin.fees.receipt', $feeCollections->first()->id) }}" class="btn btn-info" target="_blank">View Last Receipt</a>
                        @else
                        <button class="btn btn-secondary" disabled>No Receipt Yet</button>
                        @endif
                        <a href="{{ route('admin.students.show', $student->id) }}" class="btn btn-secondary">Student Profile</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Any additional scripts for this page can go here
});
</script>
@endsection