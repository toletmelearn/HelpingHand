@extends('layouts.admin')

@section('title', 'Fee Structure Management')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Fee Structure Management</h4>
                    <a href="{{ route('admin.fee-structures.create') }}" class="btn btn-primary">Add New Fee Structure</a>
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
                                    <th>Name</th>
                                    <th>Class</th>
                                    <th>Term</th>
                                    <th>Amount</th>
                                    <th>Frequency</th>
                                    <th>Valid From</th>
                                    <th>Valid Until</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($feeStructures as $feeStructure)
                                <tr>
                                    <td>{{ $feeStructure->id }}</td>
                                    <td>{{ $feeStructure->name }}</td>
                                    <td>{{ $feeStructure->class_name }}</td>
                                    <td>{{ $feeStructure->term }}</td>
                                    <td>â‚¹{{ number_format($feeStructure->amount, 2) }}</td>
                                    <td>{{ $feeStructure->frequency }}</td>
                                    <td>{{ $feeStructure->valid_from->format('d M Y') }}</td>
                                    <td>{{ $feeStructure->valid_until->format('d M Y') }}</td>
                                    <td>
                                        <span class="badge bg-{{ $feeStructure->is_active ? 'success' : 'danger' }}">
                                            {{ $feeStructure->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.fee-structures.show', $feeStructure) }}" class="btn btn-sm btn-info">View</a>
                                        <a href="{{ route('admin.fee-structures.edit', $feeStructure) }}" class="btn btn-sm btn-warning">Edit</a>
                                        <form action="{{ route('admin.fee-structures.destroy', $feeStructure) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this fee structure?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="10" class="text-center">No fee structures found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $feeStructures->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
