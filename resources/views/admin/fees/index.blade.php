@extends('layouts.app')

@section('title', 'Fee Management')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Fee Management</h4>
                    <a href="{{ route('admin.fees.create') }}" class="btn btn-primary">Add New Fee Record</a>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Student</th>
                                    <th>Fee Structure</th>
                                    <th>Academic Year</th>
                                    <th>Term</th>
                                    <th>Amount</th>
                                    <th>Paid</th>
                                    <th>Due</th>
                                    <th>Status</th>
                                    <th>Due Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($fees as $fee)
                                <tr>
                                    <td>{{ $fee->id }}</td>
                                    <td>{{ $fee->student->name ?? 'N/A' }}</td>
                                    <td>{{ $fee->feeStructure->name ?? 'N/A' }}</td>
                                    <td>{{ $fee->academic_year }}</td>
                                    <td>{{ $fee->term }}</td>
                                    <td>₹{{ number_format($fee->amount, 2) }}</td>
                                    <td>₹{{ number_format($fee->paid_amount, 2) }}</td>
                                    <td>₹{{ number_format($fee->due_amount, 2) }}</td>
                                    <td>
                                        <span class="badge bg-{{ $fee->status == 'paid' ? 'success' : ($fee->status == 'partial' ? 'warning' : 'danger') }}">
                                            {{ ucfirst($fee->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $fee->due_date->format('d M Y') }}</td>
                                    <td>
                                        <a href="{{ route('admin.fees.show', $fee) }}" class="btn btn-sm btn-info">View</a>
                                        <a href="{{ route('admin.fees.edit', $fee) }}" class="btn btn-sm btn-warning">Edit</a>
                                        <form action="{{ route('admin.fees.destroy', $fee) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this fee record?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="11" class="text-center">No fee records found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $fees->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection