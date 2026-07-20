@extends('layouts.admin')

@section('title', 'Hostel Dorm Allocations Dashboard')

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
        <h1 class="h3 mb-0 text-gray-800">Hostel & Dorm Allocations</h1>
        <button class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#allocateBedModal">
            <i class="fas fa-bed fa-sm text-white-50"></i> Allocate Hostel Bed
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
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card metric-card shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Dorm Capacity</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $totalCapacity }} Beds</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-hotel fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card metric-card shadow h-100 py-2" style="border-left-color: #e74a3b;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Occupied Beds</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $occupiedBeds }} Beds</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-friends fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card metric-card shadow h-100 py-2" style="border-left-color: #1cc88a;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Vacant Beds</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $vacantBeds }} Beds</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Allocations Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Active Dorm Allocations Directory</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Hostel</th>
                            <th>Room Number</th>
                            <th>Cost per Bed</th>
                            <th>Allocated Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($allocations as $alloc)
                            <tr>
                                <td>
                                    <strong>{{ $alloc->student->first_name }} {{ $alloc->student->last_name }}</strong>
                                    <span class="text-muted d-block small">Admission No: {{ $alloc->student->admission_no ?: 'N/A' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $alloc->room->hostel->name }}</strong>
                                    <span class="badge badge-secondary small text-capitalize">{{ $alloc->room->hostel->type }}</span>
                                </td>
                                <td>Room {{ $alloc->room->room_no }}</td>
                                <td>${{ number_format($alloc->room->cost_per_bed, 2) }}</td>
                                <td>{{ $alloc->created_at->format('Y-m-d H:i') }}</td>
                                <td>
                                    <form action="{{ route('hostel.vacate', $alloc->id) }}" method="POST" onsubmit="return confirm('Vacate student from this dorm room?');">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-danger">
                                            <i class="fas fa-door-open"></i> Vacate Bed
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">No student hostel room allocations currently active.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Allocate Bed Modal -->
<div class="modal fade" id="allocateBedModal" tabindex="-1" role="dialog" aria-labelledby="allocateBedModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content glass-card">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold text-dark" id="allocateBedModalLabel">Allocate Hostel Bed</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('hostel.allocate') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label for="modal_student_id" class="text-dark font-weight-bold">Select Student</label>
                        <select name="student_id" id="modal_student_id" class="form-control" required>
                            <option value="">-- Choose Student --</option>
                            @foreach($students as $st)
                                <option value="{{ $st->id }}">
                                    {{ $st->first_name }} {{ $st->last_name }} ({{ ucfirst($st->gender) }}, Admission: {{ $st->admission_no ?: 'N/A' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="modal_room_id" class="text-dark font-weight-bold">Select Hostel Room</label>
                        <select name="room_id" id="modal_room_id" class="form-control" required>
                            <option value="">-- Choose Room --</option>
                            @foreach($rooms as $room)
                                <option value="{{ $room->id }}">
                                    {{ $room->hostel->name }} ({{ ucfirst($room->hostel->type) }}) - Room {{ $room->room_no }} (Available capacity: {{ $room->capacity - $room->allocations->where('status', 'active')->count() }} / {{ $room->capacity }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Assign Bed</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
