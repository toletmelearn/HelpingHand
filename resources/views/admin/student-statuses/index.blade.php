@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">Student Status Management</h1>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Student Status Records</h6>
                    <a href="{{ route('admin.student-statuses.create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Add New Status Record
                    </a>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif
                    
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Status</th>
                                    <th>Status Date</th>
                                    <th>Reason</th>
                                    <th>Document No.</th>
                                    <th>Issued By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($studentStatuses as $studentStatus)
                                    <tr>
                                        <td>{{ $studentStatus->student->name ?? 'N/A' }}</td>
                                        <td>
                                            <span class="badge bg-{{ 
                                                $studentStatus->status == 'passed_out' ? 'success' : (
                                                $studentStatus->status == 'tc_issued' ? 'warning' : (
                                                $studentStatus->status == 'left_school' ? 'danger' : (
                                                $studentStatus->status == 'active' ? 'primary' : 'secondary')))
                                            }}">
                                                {{ ucfirst(str_replace('_', ' ', $studentStatus->status)) }}
                                            </span>
                                        </td>
                                        <td>{{ $studentStatus->status_date->format('M d, Y') }}</td>
                                        <td>{{ $studentStatus->reason ?: '-' }}</td>
                                        <td>{{ $studentStatus->document_number ?: '-' }}</td>
                                        <td>{{ $studentStatus->issued_by ?: '-' }}</td>
                                        <td>
                                            <a href="{{ route('admin.student-statuses.show', $studentStatus->id) }}" class="btn btn-sm btn-info">View</a>
                                            <a href="{{ route('admin.student-statuses.edit', $studentStatus->id) }}" class="btn btn-sm btn-warning">Edit</a>
                                            <form action="{{ route('admin.student-statuses.destroy', $studentStatus->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this student status record?')">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">No student status records found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
