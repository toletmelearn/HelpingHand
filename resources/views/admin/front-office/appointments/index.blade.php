@extends('layouts.admin')

@section('title', 'Scheduled Appointments')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Meeting & Appointments Scheduler</h1>
            <p class="text-muted mb-0">Book, manage, and prevent overlap conflicts for parent-teacher schedules.</p>
        </div>
        <a href="{{ route('admin.front-office.appointments.create') }}" class="btn btn-primary">
            <i class="bi bi-calendar-plus me-1"></i> Book Appointment
        </a>
    </div>

    <!-- Success Message -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Filters Card -->
    <div class="card border-0 shadow-sm mb-4 bg-white">
        <div class="card-body p-3">
            <form action="{{ route('admin.front-office.appointments.index') }}" method="GET" class="row g-2">
                <div class="col-md-3">
                    <input type="date" name="date" class="form-control" value="{{ request('date') }}" placeholder="Filter by date">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Filter by Status</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                        <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="no_show" {{ request('status') === 'no_show' ? 'selected' : '' }}>No Show</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <select name="teacher_id" class="form-select">
                        <option value="">Filter by Teacher/Staff</option>
                        @foreach($teachers as $teacher)
                            <option value="{{ $teacher->id }}" {{ request('teacher_id') == $teacher->id ? 'selected' : '' }}>
                                {{ $teacher->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100">Filter Slots</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card border-0 shadow-sm bg-white">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-uppercase small text-muted">
                            <th>Visitor Attendee</th>
                            <th>Staff Member to Meet</th>
                            <th>Scheduled Date</th>
                            <th>Time Block</th>
                            <th>Purpose</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($appointments as $appt)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $appt->visitor_name }}</div>
                                    @if($appt->guardian)
                                        <small class="text-muted"><i class="bi bi-person-badge-fill me-1"></i>Guardian Connected</small>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-medium">{{ $appt->teacher ? $appt->teacher->name : 'N/A' }}</div>
                                    <small class="text-muted">Role: Teacher</small>
                                </td>
                                <td>{{ $appt->scheduled_date->format('M d, Y') }}</td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace">
                                        {{ \Carbon\Carbon::parse($appt->start_time)->format('h:i A') }} - {{ \Carbon\Carbon::parse($appt->end_time)->format('h:i A') }}
                                    </span>
                                </td>
                                <td>{{ $appt->purpose }}</td>
                                <td>
                                    <span class="badge bg-{{ $appt->status === 'completed' || $appt->status === 'approved' ? 'success' : ($appt->status === 'pending' ? 'warning' : 'danger') }} rounded-pill text-capitalize">
                                        {{ str_replace('_', ' ', $appt->status) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#statusModal-{{ $appt->id }}">
                                            Status
                                        </button>
                                        <a href="{{ route('admin.front-office.appointments.edit', $appt->id) }}" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('admin.front-office.appointments.destroy', $appt->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this appointment?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Status Update Modal -->
                                    <div class="modal fade" id="statusModal-{{ $appt->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content text-start">
                                                <form action="{{ route('admin.front-office.appointments.status', $appt->id) }}" method="POST">
                                                    @csrf
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Update Meeting Status</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Status</label>
                                                            <select name="status" class="form-select">
                                                                <option value="pending" {{ $appt->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                                                <option value="approved" {{ $appt->status === 'approved' ? 'selected' : '' }}>Approved</option>
                                                                <option value="rejected" {{ $appt->status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                                                                <option value="completed" {{ $appt->status === 'completed' ? 'selected' : '' }}>Completed</option>
                                                                <option value="no_show" {{ $appt->status === 'no_show' ? 'selected' : '' }}>No Show</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Feedback / Discussion Brief</label>
                                                            <textarea name="feedback" class="form-control" rows="4" placeholder="Enter brief points discussed during meeting...">{{ $appt->feedback }}</textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No appointments booked yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $appointments->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
