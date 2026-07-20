@extends('layouts.parent')

@section('content')
<div class="container-fluid px-4 py-4 text-dark">
    <div class="row mb-4 align-items-center">
        <div class="col-sm-6">
            <h1 class="h2 text-dark font-weight-bold">Parent-Teacher Meetings (PTM)</h1>
            <p class="text-secondary">Schedule, request, and view your PTM appointments with academic teachers.</p>
        </div>
        <div class="col-sm-6 text-sm-end">
            <button class="btn btn-primary rounded-pill px-4 py-2 font-weight-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#requestPtmModal">
                <i class="bi bi-calendar-plus"></i> Request PTM Slot
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0 rounded-lg">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h5 class="card-title text-dark font-weight-bold mb-0">PTM Scheduled Appointments</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-secondary font-weight-bold text-uppercase" style="font-size: 0.8rem;">
                        <tr>
                            <th class="ps-4">Teacher Name</th>
                            <th>Meeting Date</th>
                            <th>Time Slot</th>
                            <th>Reason / Notes</th>
                            <th class="pe-4">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($meetings as $meeting)
                            <tr>
                                <td class="ps-4 font-weight-bold text-dark">{{ $meeting->teacher->name }}</td>
                                <td class="text-dark font-weight-bold">{{ $meeting->meeting_date->format('M d, Y') }}</td>
                                <td class="text-dark font-weight-bold">{{ $meeting->time_slot }}</td>
                                <td style="max-width: 250px;" class="text-truncate" title="{{ $meeting->notes }}">{{ $meeting->notes ?? '-' }}</td>
                                <td class="pe-4">
                                    @if($meeting->status == 'requested')
                                        <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">Requested</span>
                                    @elseif($meeting->status == 'approved')
                                        <span class="badge bg-success px-3 py-2 rounded-pill">Approved</span>
                                    @else
                                        <span class="badge bg-danger px-3 py-2 rounded-pill">Cancelled</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-secondary">No PTM meetings requested or scheduled yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Request PTM Modal -->
<div class="modal fade" id="requestPtmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog text-dark">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('parent.ptm.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-primary text-white border-0 py-3">
                    <h5 class="modal-title font-weight-bold">Request PTM Appointment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-secondary">Teacher</label>
                        <select class="form-select text-dark" name="teacher_id" required>
                            <option value="" disabled selected>Select Teacher...</option>
                            @foreach($teachers as $teacher)
                                <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary">Meeting Date</label>
                        <input type="date" class="form-control text-dark" name="meeting_date" required value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary">Available Time Slot</label>
                        <select class="form-select text-dark" name="time_slot" required>
                            <option value="" disabled selected>Select Time Slot...</option>
                            <option value="10:00 AM - 10:30 AM">10:00 AM - 10:30 AM</option>
                            <option value="11:00 AM - 11:30 AM">11:00 AM - 11:30 AM</option>
                            <option value="02:00 PM - 02:30 PM">02:00 PM - 02:30 PM</option>
                            <option value="03:00 PM - 03:30 PM">03:00 PM - 03:30 PM</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label text-secondary">Notes / Agenda</label>
                        <textarea class="form-control text-dark" name="notes" rows="3" placeholder="State agenda of the meeting..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Request Slot</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
