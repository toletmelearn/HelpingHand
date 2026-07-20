@extends('layouts.admin')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-12 mb-4">
            <h1 class="h3 mb-2 text-gray-800">Pre-Admission Enquiry & Selection Pipeline</h1>
            <p class="mb-4">Log candidate enquiries, schedule evaluation interviews, and enter entrance/interview scores.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row">
        <!-- New Enquiry Form -->
        <div class="col-md-4 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Log New Admission Enquiry</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.admissions.store-enquiry') }}" method="POST">
                        @csrf
                        <div class="form-group mb-3">
                            <label>Candidate Name</label>
                            <input type="text" name="candidate_name" class="form-control" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Parent Name</label>
                            <input type="text" name="parent_name" class="form-control" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Phone Number</label>
                            <input type="text" name="phone" class="form-control" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Email Address</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Log Enquiry</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Enquiry Pipeline Lists -->
        <div class="col-md-8 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Enquiries Queue & Evaluations</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Candidate</th>
                                    <th>Parent Contact</th>
                                    <th>Status</th>
                                    <th>Evaluation Scores</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($enquiries as $enquiry)
                                    <tr>
                                        <td>
                                            <strong>{{ $enquiry->candidate_name }}</strong>
                                            @if($enquiry->interview_date)
                                                <br><small class="text-primary">Interview: {{ $enquiry->interview_date }}</small>
                                            @endif
                                        </td>
                                        <td>{{ $enquiry->parent_name }}<br><small>{{ $enquiry->phone }}</small></td>
                                        <td>
                                            @if($enquiry->status === 'enquiry')
                                                <span class="badge bg-secondary">New Enquiry</span>
                                            @elseif($enquiry->status === 'interview')
                                                <span class="badge bg-info">Interview Scheduled</span>
                                            @elseif($enquiry->status === 'selected')
                                                <span class="badge bg-success">Selected</span>
                                            @elseif($enquiry->status === 'admitted')
                                                <span class="badge bg-primary">Admitted</span>
                                            @else
                                                <span class="badge bg-danger">Closed</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($enquiry->interview_score !== null)
                                                Interview: {{ $enquiry->interview_score }}%<br>
                                                Entrance: {{ $enquiry->entrance_score }}%
                                            @else
                                                <span class="text-muted">Not Evaluated</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($enquiry->status === 'enquiry')
                                                <!-- Schedule Interview Form -->
                                                <form action="{{ route('admin.admissions.schedule', $enquiry->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <div class="input-group input-group-sm">
                                                        <input type="date" name="interview_date" class="form-control form-control-sm" required>
                                                        <button type="submit" class="btn btn-info btn-sm">Schedule</button>
                                                    </div>
                                                </form>
                                            @elseif($enquiry->status === 'interview')
                                                <!-- Evaluate Form -->
                                                <form action="{{ route('admin.admissions.evaluate', $enquiry->id) }}" method="POST" class="mt-2">
                                                    @csrf
                                                    <div class="row g-1">
                                                        <div class="col-md-4">
                                                            <input type="number" name="interview_score" class="form-control form-control-sm" placeholder="Int. %" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <input type="number" name="entrance_score" class="form-control form-control-sm" placeholder="Ent. %" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <select name="status" class="form-select form-select-sm" required>
                                                                <option value="selected">Select</option>
                                                                <option value="closed">Reject</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-12 mt-1">
                                                            <button type="submit" class="btn btn-warning btn-sm w-100">Submit Scores</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            @elseif($enquiry->status === 'selected')
                                                <a href="{{ route('admin.admissions.confirm-form', $enquiry->id) }}" class="btn btn-success btn-sm w-100 fw-bold">
                                                    Complete Admission <i class="fas fa-user-plus ms-1"></i>
                                                </a>
                                            @elseif($enquiry->status === 'admitted')
                                                <span class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i> Admitted</span>
                                            @else
                                                <span class="text-muted">Pipeline Complete</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">No pre-admission logs found.</td>
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
