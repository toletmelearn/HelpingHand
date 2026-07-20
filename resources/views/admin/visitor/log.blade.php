@extends('layouts.admin')

@section('title', 'Visitor & Gate Pass Management')

@section('content')
<style>
    .glass-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 12px;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.06);
    }
    .metric-card {
        border-left: 4px solid #4e73df;
        transition: transform 0.2s;
    }
    .metric-card:hover {
        transform: scale(1.02);
    }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Visitor & Gate Entries Management</h1>
        <button class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#checkInVisitorModal">
            <i class="fas fa-id-card fa-sm text-white-50"></i> Register Gate Entry
        </button>
    </div>

    <!-- Alert notifications -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <!-- Metrics Row -->
    <div class="row mb-4">
        <div class="col-xl-6 col-md-6 mb-4">
            <div class="card metric-card shadow h-100 py-2" style="border-left-color: #1cc88a;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Visitors Checked In (Today)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $totalVisitorsToday }} Guests</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6 col-md-6 mb-4">
            <div class="card metric-card shadow h-100 py-2" style="border-left-color: #f6c23e;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Currently On Campus</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $currentlyOnCampus }} Guests</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-building fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Allocations Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Visitor Entry History Log</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Visitor</th>
                            <th>Purpose</th>
                            <th>Vehicle No</th>
                            <th>Host Staff</th>
                            <th>Check-in Hour</th>
                            <th>Check-out Hour</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($visitors as $visitor)
                            <tr>
                                <td><strong>{{ $visitor->visitor_name }}</strong></td>
                                <td>{{ $visitor->purpose }}</td>
                                <td>{{ $visitor->vehicle_no ?: 'N/A' }}</td>
                                <td>{{ $visitor->host ? $visitor->host->name : 'N/A' }}</td>
                                <td>{{ \Carbon\Carbon::parse($visitor->check_in)->format('Y-m-d H:i') }}</td>
                                <td>
                                    @if($visitor->check_out)
                                        {{ \Carbon\Carbon::parse($visitor->check_out)->format('Y-m-d H:i') }}
                                    @else
                                        <span class="badge badge-warning">Active (On Campus)</span>
                                    @endif
                                </td>
                                <td>
                                    @if(!$visitor->check_out)
                                        <form action="{{ route('visitor.checkout', $visitor->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-warning">
                                                <i class="fas fa-sign-out-alt"></i> Check Out
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted">Completed</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">No visitor entry logs recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Check In Visitor Modal -->
<div class="modal fade" id="checkInVisitorModal" tabindex="-1" role="dialog" aria-labelledby="checkInVisitorModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content glass-card">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold text-dark" id="checkInVisitorModalLabel">Register Gate Entry</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('visitor.checkin') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label for="visitor_name" class="text-dark font-weight-bold">Visitor Full Name</label>
                        <input type="text" name="visitor_name" id="visitor_name" class="form-control" placeholder="e.g. John Doe" required>
                    </div>

                    <div class="form-group">
                        <label for="purpose" class="text-dark font-weight-bold">Purpose of Visit</label>
                        <input type="text" name="purpose" id="purpose" class="form-control" placeholder="e.g. Parental Meeting with Class Teacher" required>
                    </div>

                    <div class="form-group">
                        <label for="vehicle_no" class="text-dark font-weight-bold">Vehicle Registration Number (Optional)</label>
                        <input type="text" name="vehicle_no" id="vehicle_no" class="form-control" placeholder="e.g. DL-3C-AB-1234">
                    </div>

                    <div class="form-group">
                        <label for="host_user_id" class="text-dark font-weight-bold">Host Staff / Teacher (Optional)</label>
                        <select name="host_user_id" id="host_user_id" class="form-control">
                            <option value="">-- Choose Host --</option>
                            @foreach($hosts as $host)
                                <option value="{{ $host->id }}">{{ $host->name }} ({{ $host->email }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success">Register Entrance</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
