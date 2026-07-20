@extends('layouts.admin')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-12 mb-4">
            <h1 class="h3 mb-2 text-gray-800">Hostel & Accommodation Management</h1>
            <p class="mb-4">Configure hostels, room numbers, bed capacities, boarding costs, and assign students.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row">
        <!-- Create Hostel -->
        <div class="col-md-4 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Create New Hostel</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.hostels.store-hostel') }}" method="POST">
                        @csrf
                        <div class="form-group mb-3">
                            <label>Hostel Name</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Tagore Boys Hostel" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Type</label>
                            <select name="type" class="form-control" required>
                                <option value="boys">Boys</option>
                                <option value="girls">Girls</option>
                                <option value="coed">Co-Ed</option>
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label>Total Capacity (Beds)</label>
                            <input type="number" name="capacity" class="form-control" min="1" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Create Hostel</button>
                    </form>
                </div>
            </div>

            <!-- Create Room -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Add Room to Hostel</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.hostels.store-room') }}" method="POST">
                        @csrf
                        <div class="form-group mb-3">
                            <label>Select Hostel</label>
                            <select name="hostel_id" class="form-control" required>
                                @foreach($hostels as $hostel)
                                    <option value="{{ $hostel->id }}">{{ $hostel->name }} ({{ ucfirst($hostel->type) }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label>Room Number / Name</label>
                            <input type="text" name="room_no" class="form-control" placeholder="e.g. 101-A" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Beds Capacity</label>
                            <input type="number" name="capacity" class="form-control" min="1" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Monthly Cost Per Bed (₹)</label>
                            <input type="number" name="cost_per_bed" class="form-control" min="0" step="0.01" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Add Room</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Allocation and Lists -->
        <div class="col-md-8 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Allocate Student to Room</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.hostels.store-allocation') }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-md-5 mb-3">
                                <label>Select Student</label>
                                <select name="student_id" class="form-control" required>
                                    @foreach($students as $student)
                                        <option value="{{ $student->id }}">{{ $student->name }} (Class: {{ $student->class }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-5 mb-3">
                                <label>Select Room</label>
                                <select name="room_id" class="form-control" required>
                                    @foreach($hostels as $h)
                                        @foreach($h->rooms as $r)
                                            <option value="{{ $r->id }}">{{ $h->name }} - Room {{ $r->room_no }} (₹{{ $r->cost_per_bed }}/mo)</option>
                                        @endforeach
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 mb-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-warning w-100">Allocate</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Hostels List -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Active Hostel Details & Occupancy</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Hostel</th>
                                    <th>Type</th>
                                    <th>Rooms & Beds (Allocated / Capacity)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($hostels as $hostel)
                                    <tr>
                                        <td><strong>{{ $hostel->name }}</strong></td>
                                        <td>{{ ucfirst($hostel->type) }}</td>
                                        <td>
                                            <ul class="mb-0">
                                                @foreach($hostel->rooms as $room)
                                                    <li>
                                                        Room {{ $room->room_no }}: 
                                                        {{ $room->allocations->where('status', 'active')->count() }} / {{ $room->capacity }} beds allocated (₹{{ $room->cost_per_bed }}/bed)
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center">No hostels registered yet.</td>
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
