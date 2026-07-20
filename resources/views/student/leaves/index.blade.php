@extends('layouts.student')

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-md-12 mb-4">
            <h1 class="h3 mb-2 text-gray-800">My Leave Applications</h1>
            <p class="mb-4">Submit and track leave requests for class teacher review.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row">
        <!-- New Leave Form -->
        <div class="col-md-4 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Apply For Leave</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('student.leaves.store') }}" method="POST">
                        @csrf
                        <div class="form-group mb-3">
                            <label>Start Date</label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>End Date</label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Reason</label>
                            <textarea name="reason" class="form-control" rows="4" placeholder="Explain the reason for leave..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Submit Application</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- History -->
        <div class="col-md-8 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Leave History</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Approved By</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($leaves as $leave)
                                    <tr>
                                        <td>{{ $leave->start_date }}</td>
                                        <td>{{ $leave->end_date }}</td>
                                        <td>{{ $leave->reason }}</td>
                                        <td>
                                            @if($leave->status === 'pending')
                                                <span class="badge bg-warning text-dark">Pending</span>
                                            @elseif($leave->status === 'approved')
                                                <span class="badge bg-success">Approved</span>
                                            @else
                                                <span class="badge bg-danger">Rejected</span>
                                            @endif
                                        </td>
                                        <td>{{ $leave->approver ? $leave->approver->name : 'N/A' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">No leave requests logged yet.</td>
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
