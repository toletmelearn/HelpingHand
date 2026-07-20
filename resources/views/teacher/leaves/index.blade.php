@extends('layouts.teacher')

@section('content')
<div class="container-fluid px-4 py-4">
    <div class="row mb-4 align-items-center">
        <div class="col-sm-6">
            <h1 class="h2 text-dark font-weight-bold">My Leave Requests</h1>
            <p class="text-secondary">Track, apply for, and check the status of your leave requests.</p>
        </div>
        <div class="col-sm-6 text-sm-end">
            <a href="{{ route('teacher.leaves.create') }}" class="btn btn-primary px-4 py-2 rounded-pill font-weight-bold shadow-sm">
                <i class="bi bi-plus"></i> Apply for Leave
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0 rounded-lg">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-secondary font-weight-bold text-uppercase" style="font-size: 0.8rem;">
                        <tr>
                            <th class="ps-4 py-3">Leave Type</th>
                            <th class="py-3">Duration</th>
                            <th class="py-3 text-center">Days</th>
                            <th class="py-3">Reason</th>
                            <th class="py-3">Status</th>
                            <th class="py-3 text-end pe-4">Processed Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($leaves as $leave)
                            <tr>
                                <td class="ps-4">
                                    <span class="text-dark font-weight-bold text-capitalize">{{ str_replace('_', ' ', $leave->leave_type) }}</span>
                                </td>
                                <td>
                                    <div class="text-dark">{{ $leave->start_date->format('M d, Y') }} - {{ $leave->end_date->format('M d, Y') }}</div>
                                    <small class="text-secondary">Applied on {{ $leave->created_at->format('M d, Y') }}</small>
                                </td>
                                <td class="text-center font-weight-bold text-dark">{{ $leave->days }}</td>
                                <td style="max-width: 250px;" class="text-truncate" title="{{ $leave->reason }}">{{ $leave->reason }}</td>
                                <td>
                                    @if($leave->status == 'pending')
                                        <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">Pending</span>
                                    @elseif($leave->status == 'approved')
                                        <span class="badge bg-success px-3 py-2 rounded-pill">Approved</span>
                                    @else
                                        <span class="badge bg-danger px-3 py-2 rounded-pill">Rejected</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    @if($leave->status != 'pending')
                                        <div class="text-dark font-weight-bold">{{ $leave->approved_at ? $leave->approved_at->format('M d, Y') : '' }}</div>
                                        @if($leave->approval_notes)
                                            <small class="text-secondary d-block" title="{{ $leave->approval_notes }}">Notes: {{ Str::limit($leave->approval_notes, 25) }}</small>
                                        @endif
                                    @else
                                        <span class="text-secondary">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="text-secondary mb-3">
                                        <i class="bi bi-calendar-check" style="font-size: 3rem;"></i>
                                    </div>
                                    <h5 class="text-dark font-weight-bold">No Leave Requests</h5>
                                    <p class="text-secondary mb-0">You have not submitted any leave applications yet.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
