@extends('layouts.admin')

@section('title', 'Guard Duty Assignments')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-shield-lock me-2 text-primary"></i>Guard Duty Assignments</h1>
            <p class="text-muted mb-0">Assign security guards to specific gates on a daily basis to route gate passes.</p>
        </div>
        <a href="{{ route('admin.front-office.gate-passes.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Registry
        </a>
    </div>

    <!-- Success Message -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row g-4">
        <!-- Assign Duty Form -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm bg-white h-100">
                <div class="card-header bg-transparent border-0 pt-4">
                    <h5 class="fw-bold mb-0">Assign Gate Duty</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.front-office.duty-assignments.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Duty Date</label>
                            <input type="date" name="duty_date" class="form-control" value="{{ $date }}" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Select Guard</label>
                            <select name="user_id" class="form-select" required>
                                <option value="">-- Choose Security Guard --</option>
                                @foreach($guards as $guard)
                                    <option value="{{ $guard->id }}">{{ $guard->name }} ({{ $guard->email }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Select Gate / Post</label>
                            <select name="gate_name" class="form-select" required>
                                <option value="">-- Choose Gate Location --</option>
                                <option value="Gate 1">Gate 1 (Primary Wing)</option>
                                <option value="Gate 2">Gate 2 (Senior Wing)</option>
                                <option value="Main Gate">Main Gate</option>
                                <option value="Hostel Gate">Hostel Gate</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Shift</label>
                            <select name="shift" class="form-select" required>
                                <option value="Morning">Morning Shift (6 AM - 2 PM)</option>
                                <option value="Evening">Evening Shift (2 PM - 10 PM)</option>
                                <option value="Night">Night Shift (10 PM - 6 AM)</option>
                                <option value="General" selected>General Shift (8 AM - 5 PM)</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="bi bi-check2-circle me-1"></i> Assign Shift Duty
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Assignments List -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm bg-white h-100">
                <div class="card-header bg-transparent border-0 pt-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Active Duties for {{ \Carbon\Carbon::parse($date)->format('M d, Y') }}</h5>
                    <form action="{{ route('admin.front-office.duty-assignments.index') }}" method="GET" class="d-flex g-2">
                        <input type="date" name="date" class="form-control form-control-sm me-2" value="{{ $date }}" onchange="this.form.submit()">
                    </form>
                </div>
                <div class="card-body p-0 mt-3">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr class="text-uppercase small text-muted">
                                    <th>Security Guard</th>
                                    <th>Assigned Gate</th>
                                    <th>Shift</th>
                                    <th>Assigned By</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($assignments as $assignment)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar bg-light text-primary rounded-circle p-2 me-2">
                                                    <i class="bi bi-shield-fill"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark">{{ $assignment->guardUser->name ?? 'N/A' }}</div>
                                                    <small class="text-muted">{{ $assignment->guardUser->email ?? '' }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary p-2"><i class="bi bi-door-open me-1"></i>{{ $assignment->gate_name }}</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><i class="bi bi-clock me-1"></i>{{ $assignment->shift }}</span>
                                        </td>
                                        <td>
                                            <small class="text-muted">By {{ $assignment->assigner->name ?? 'System' }}</small>
                                        </td>
                                        <td>
                                            @if($assignment->status === 'active')
                                                <span class="badge bg-success rounded-pill">Active</span>
                                            @else
                                                <span class="badge bg-light text-muted border rounded-pill">Completed</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <form action="{{ route('admin.front-office.duty-assignments.destroy', $assignment->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to cancel this shift duty assignment?')">
                                                    <i class="bi bi-trash"></i> Cancel Duty
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="bi bi-calendar-x fs-2 mb-2 d-block text-secondary"></i>
                                            No guard duty assignments logged for this date.
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
