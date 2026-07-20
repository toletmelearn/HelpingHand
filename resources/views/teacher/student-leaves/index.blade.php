@extends('layouts.teacher')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-12 mb-4">
            <h1 class="h3 mb-2 text-gray-800">Student Leave Approval Dashboard</h1>
            <p class="mb-4">Review, approve, or reject student leave requests for your assigned classes.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row">
        <div class="col-md-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Student Leave Request Queue</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($leaves as $leave)
                                    <tr>
                                        <td><strong>{{ $leave->student->name }}</strong> (Class: {{ $leave->student->class }})</td>
                                        <td>{{ $leave->start_date }}</td>
                                        <td>{{ $leave->end_date }}</td>
                                        <td>{{ $leave->reason }}</td>
                                        <td>
                                            @if($leave->status === 'pending')
                                                <span class="badge bg-warning text-dark">Pending Review</span>
                                            @elseif($leave->status === 'approved')
                                                <span class="badge bg-success">Approved</span>
                                            @else
                                                <span class="badge bg-danger">Rejected</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($leave->status === 'pending')
                                                <div class="d-flex gap-2">
                                                    <form action="{{ route('teacher.student-leaves.update', $leave->id) }}" method="POST">
                                                        @csrf
                                                        <input type="hidden" name="status" value="approved">
                                                        <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                                    </form>
                                                    <form action="{{ route('teacher.student-leaves.update', $leave->id) }}" method="POST">
                                                        @csrf
                                                        <input type="hidden" name="status" value="rejected">
                                                        <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                                                    </form>
                                                </div>
                                            @else
                                                <span class="text-muted">Decision Completed</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No student leave requests pending in your queue.</td>
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
