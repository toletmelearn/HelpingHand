@extends('layouts.parent')

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-md-12 mb-4">
            <h1 class="h3 mb-2 text-gray-800">Hostel & Accommodation Details</h1>
            <p class="mb-4">Review room boarding details and billing charges for <strong>{{ $student->name }}</strong>.</p>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow border-left-primary">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Boarding & Room Allotment Status</h6>
                </div>
                <div class="card-body">
                    @if($allocation && $allocation->status === 'active')
                        <div class="row mb-3">
                            <div class="col-sm-4 text-muted">Hostel Building:</div>
                            <div class="col-sm-8"><strong>{{ $allocation->room->hostel->name }}</strong> ({{ ucfirst($allocation->room->hostel->type) }} facility)</div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4 text-muted">Room Number:</div>
                            <div class="col-sm-8"><strong>Room {{ $allocation->room->room_no }}</strong></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4 text-muted">Monthly Boarding Fee:</div>
                            <div class="col-sm-8"><strong>₹{{ number_format($allocation->room->cost_per_bed, 2) }}</strong></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4 text-muted">Status:</div>
                            <div class="col-sm-8"><span class="badge bg-success">Active</span></div>
                        </div>
                    @else
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-hotel fa-3x mb-3"></i>
                            <p>No active hostel room allotment found for this student.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
