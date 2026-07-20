@extends('layouts.admin')

@section('content')
<div class="container-fluid px-4 py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h2 text-dark font-weight-bold">Teacher Leave Requests</h1>
            <p class="text-secondary">Process and review leave applications submitted by academic staff.</p>
        </div>
        <div class="col-md-4 text-end">
            <div class="btn-group">
                <a href="{{ route('admin.leaves.index') }}" class="btn btn-outline-primary {{ !request('status') ? 'active' : '' }}">All</a>
                <a href="{{ route('admin.leaves.index', ['status' => 'pending']) }}" class="btn btn-outline-primary {{ request('status') == 'pending' ? 'active' : '' }}">Pending</a>
                <a href="{{ route('admin.leaves.index', ['status' => 'approved']) }}" class="btn btn-outline-primary {{ request('status') == 'approved' ? 'active' : '' }}">Approved</a>
                <a href="{{ route('admin.leaves.index', ['status' => 'rejected']) }}" class="btn btn-outline-primary {{ request('status') == 'rejected' ? 'active' : '' }}">Rejected</a>
            </div>
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
                            <th class="ps-4 py-3">Teacher</th>
                            <th class="py-3">Type</th>
                            <th class="py-3">Duration</th>
                            <th class="py-3 text-center">Days</th>
                            <th class="py-3">Status</th>
                            <th class="py-3 text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($leaves as $leave)
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; font-weight: 600;">
                                            {{ strtoupper(substr($leave->teacher->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <h6 class="mb-0 text-dark font-weight-bold">{{ $leave->teacher->name }}</h6>
                                            <small class="text-secondary">{{ $leave->teacher->email ?? $leave->teacher->phone }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary text-capitalize">{{ str_replace('_', ' ', $leave->leave_type) }}</span>
                                </td>
                                <td>
                                    <div class="text-dark">{{ $leave->start_date->format('M d, Y') }} - {{ $leave->end_date->format('M d, Y') }}</div>
                                    <small class="text-secondary">Applied on {{ $leave->created_at->format('M d, Y') }}</small>
                                </td>
                                <td class="text-center font-weight-bold text-dark">{{ $leave->days }}</td>
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
                                    <a href="{{ route('admin.leaves.show', $leave->id) }}" class="btn btn-sm btn-outline-primary px-3 rounded-pill">View & Action</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="text-secondary mb-3">
                                        <i class="bi bi-calendar-x" style="font-size: 3rem;"></i>
                                    </div>
                                    <h5 class="text-dark font-weight-bold">No Leave Applications</h5>
                                    <p class="text-secondary mb-0">No leave requests match the selected filter criteria.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($leaves->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $leaves->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
