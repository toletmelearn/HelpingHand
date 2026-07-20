@extends('layouts.admin')

@section('title', 'Front Office Dashboard')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Front Office & Receptionist Operations</h1>
            <p class="text-muted mb-0">Central command for inquiries, guest check-ins, appointments, and logistics.</p>
        </div>
        <div class="text-muted">
            <i class="bi bi-clock-history me-1"></i> Today: <strong>{{ \Carbon\Carbon::now()->format('F d, Y') }}</strong>
        </div>
    </div>

    <!-- Alert Banner for Follow-ups -->
    @if($stats['pending_follow_ups'] > 0)
        <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <div>
                You have <strong>{{ $stats['pending_follow_ups'] }}</strong> follow-up tasks due today. 
                <a href="{{ route('admin.front-office.enquiries.index') }}?status=follow_up" class="alert-link ms-2">View enquiries &rarr;</a>
            </div>
        </div>
    @endif

    <!-- Metrics Cards Grid -->
    <div class="row g-3 mb-4">
        <!-- Visitors Today -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 overflow-hidden bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="rounded-3 bg-primary bg-opacity-10 p-3 text-primary me-3">
                        <i class="bi bi-people fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1 text-uppercase small">Visitors Today</h6>
                        <h3 class="mb-0 fw-bold">{{ $stats['today_visitors'] }}</h3>
                        <small class="text-success small fw-medium">
                            <span class="badge bg-success bg-opacity-10 text-success">{{ $stats['currently_inside'] }} currently inside</span>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admissions Enquiries -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 overflow-hidden bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="rounded-3 bg-success bg-opacity-10 p-3 text-success me-3">
                        <i class="bi bi-chat-left-text fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1 text-uppercase small">Enquiries Today</h6>
                        <h3 class="mb-0 fw-bold">{{ $stats['today_enquiries'] }}</h3>
                        <small class="text-muted small">Conversion: <strong>{{ $stats['conversion_rate'] }}%</strong></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Appointments today -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 overflow-hidden bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="rounded-3 bg-info bg-opacity-10 p-3 text-info me-3">
                        <i class="bi bi-calendar-event fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1 text-uppercase small">Appointments</h6>
                        <h3 class="mb-0 fw-bold">{{ $stats['appointments_today'] }}</h3>
                        <small class="text-muted small">Scheduled for today</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Gate Passes -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 overflow-hidden bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="rounded-3 bg-danger bg-opacity-10 p-3 text-danger me-3">
                        <i class="bi bi-card-heading fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1 text-uppercase small">Gate Passes Today</h6>
                        <h3 class="mb-0 fw-bold">{{ $stats['gate_passes_today'] }}</h3>
                        <small class="text-muted small">Outbound releases</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions Panel -->
    <div class="card border-0 shadow-sm mb-4 bg-white">
        <div class="card-header bg-transparent border-0 pt-3 pb-0">
            <h5 class="fw-bold mb-0">Quick Operations & Actions</h5>
        </div>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-3">
                    <a href="{{ route('admin.front-office.visitors.create') }}" class="btn btn-outline-primary w-100 py-3 d-flex flex-column align-items-center justify-content-center border-2 rounded-3">
                        <i class="bi bi-person-plus fs-3 mb-2"></i>
                        <span class="fw-bold small">Guest Check-In</span>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="{{ route('admin.front-office.enquiries.create') }}" class="btn btn-outline-success w-100 py-3 d-flex flex-column align-items-center justify-content-center border-2 rounded-3">
                        <i class="bi bi-chat-square-dots fs-3 mb-2"></i>
                        <span class="fw-bold small">New Enquiry</span>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="{{ route('admin.front-office.appointments.create') }}" class="btn btn-outline-info w-100 py-3 d-flex flex-column align-items-center justify-content-center border-2 rounded-3">
                        <i class="bi bi-calendar-plus fs-3 mb-2"></i>
                        <span class="fw-bold small">Book Appointment</span>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="{{ route('admin.front-office.gate-passes.create') }}" class="btn btn-outline-danger w-100 py-3 d-flex flex-column align-items-center justify-content-center border-2 rounded-3">
                        <i class="bi bi-card-heading fs-3 mb-2"></i>
                        <span class="fw-bold small">Issue Gate Pass</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Status Logs -->
    <div class="row g-4">
        <!-- Visitors Currently Inside -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-white">
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3 pb-0">
                    <h5 class="fw-bold mb-0">Visitors Inside Campus</h5>
                    <span class="badge bg-primary rounded-pill">{{ $recentVisitors->whereNull('check_out')->count() }} active</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="text-uppercase small text-muted">
                                    <th>Guest</th>
                                    <th>Purpose</th>
                                    <th>In Time</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentVisitors->whereNull('check_out') as $visitor)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar bg-light rounded-circle p-2 me-2">
                                                    <i class="bi bi-person text-primary"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold">{{ $visitor->visitor_name }}</div>
                                                    <small class="text-muted">{{ $visitor->phone }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>{{ $visitor->purpose }}</div>
                                            <small class="text-muted">Host: {{ $visitor->host ? $visitor->host->name : 'N/A' }}</small>
                                        </td>
                                        <td>{{ $visitor->check_in->format('h:i A') }}</td>
                                        <td class="text-end">
                                            <form action="{{ route('admin.front-office.visitors.update', $visitor->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="check_out_now" value="1">
                                                <input type="hidden" name="visitor_name" value="{{ $visitor->visitor_name }}">
                                                <input type="hidden" name="phone" value="{{ $visitor->phone }}">
                                                <input type="hidden" name="purpose" value="{{ $visitor->purpose }}">
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                                                    Check-Out
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            <i class="bi bi-shield-check fs-2 mb-2 d-block text-secondary"></i>
                                            No visitors currently inside school grounds.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Appointments -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-white">
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3 pb-0">
                    <h5 class="fw-bold mb-0">Upcoming Meetings Today</h5>
                    <a href="{{ route('admin.front-office.appointments.index') }}" class="btn btn-sm btn-link text-decoration-none">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="text-uppercase small text-muted">
                                    <th>Attendee</th>
                                    <th>Staff To Meet</th>
                                    <th>Scheduled Slot</th>
                                    <th class="text-end">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($upcomingAppointments as $appt)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $appt->visitor_name }}</div>
                                            <small class="text-muted">{{ $appt->purpose }}</small>
                                        </td>
                                        <td>{{ $appt->teacher ? $appt->teacher->name : 'N/A' }}</td>
                                        <td>
                                            <div>{{ $appt->scheduled_date->format('M d') }}</div>
                                            <small class="text-muted">{{ \Carbon\Carbon::parse($appt->start_time)->format('h:i A') }}</small>
                                        </td>
                                        <td class="text-end">
                                            <span class="badge bg-{{ $appt->status === 'approved' ? 'success' : ($appt->status === 'pending' ? 'warning' : 'secondary') }} rounded-pill text-capitalize">
                                                {{ $appt->status }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            <i class="bi bi-calendar-x fs-2 mb-2 d-block text-secondary"></i>
                                            No appointments scheduled for today.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Status Logs Row 2 (Gate Passes) -->
    <div class="row g-4 mt-2">
        <div class="col-12">
            <div class="card border-0 shadow-sm bg-white">
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3 pb-0">
                    <h5 class="fw-bold mb-0">Recent Outbound Gate Passes</h5>
                    <a href="{{ route('admin.front-office.gate-passes.index') }}" class="btn btn-sm btn-link text-decoration-none">View All Registry</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="text-uppercase small text-muted">
                                    <th>Pass Holder</th>
                                    <th>Pass Type</th>
                                    <th>Purpose</th>
                                    <th>Departure</th>
                                    <th>Arrival</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentGatePasses as $pass)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $pass->holder_name }}</div>
                                            @if($pass->pass_type === 'student' && $pass->student)
                                                <small class="text-muted">Student Class: {{ $pass->student->schoolClass ? $pass->student->schoolClass->name : 'N/A' }}</small>
                                            @elseif($pass->pass_type === 'staff' && $pass->user)
                                                <small class="text-muted">Staff Member</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border text-capitalize">
                                                {{ $pass->pass_type }} Pass
                                            </span>
                                        </td>
                                        <td>{{ $pass->purpose }}</td>
                                        <td>
                                            <div>{{ $pass->request_date->format('M d, Y') }}</div>
                                            <small class="text-muted font-monospace">{{ \Carbon\Carbon::parse($pass->departure_time)->format('h:i A') }}</small>
                                        </td>
                                        <td>
                                            @if($pass->arrival_time)
                                                <span class="font-monospace small">{{ \Carbon\Carbon::parse($pass->arrival_time)->format('h:i A') }}</span>
                                            @else
                                                <span class="text-muted small">Not Returned</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $pass->status === 'completed' ? 'success' : ($pass->status === 'active' ? 'primary' : ($pass->status === 'pending' ? 'warning' : 'danger')) }} rounded-pill text-capitalize">
                                                {{ $pass->status }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            @if($pass->status === 'active' || $pass->status === 'approved')
                                                <form action="{{ route('admin.front-office.gate-passes.verify', $pass->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-success px-3" title="Verify Return & Complete Pass">
                                                        Verify Return
                                                    </button>
                                                </form>
                                            @endif
                                            <a href="{{ route('admin.front-office.gate-passes.show', $pass->id) }}" class="btn btn-sm btn-outline-primary" target="_blank" title="Print Gate Pass">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            <i class="bi bi-card-heading fs-2 mb-2 d-block text-secondary"></i>
                                            No recent gate passes recorded.
                                        </td>
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
