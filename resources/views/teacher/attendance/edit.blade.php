@extends('layouts.teacher')

@section('title', 'Attendance Record')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">Attendance Record -- {{ $student->name ?? '' }}</h2>
            <p class="text-muted">{{ optional($attendance->date)->format('l, d M Y') }}</p>
        </div>
    </div>

    <div class="alert alert-warning" role="alert">
        Teacher attendance marking, updates, reports, and export are temporarily disabled until class/status/schema policy is aligned.
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Record Details</h5>
        </div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Student</dt>
                <dd class="col-sm-9">{{ $student->name ?? '' }}</dd>

                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9">{{ ucfirst($attendance->status ?? '') }}</dd>

                <dt class="col-sm-3">Remarks</dt>
                <dd class="col-sm-9">{{ $attendance->remarks ?: '—' }}</dd>
            </dl>
        </div>
    </div>
</div>
@endsection
