@extends('layouts.admin')

@section('title', 'Attendance Preflight Preview')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-eye"></i> Attendance Preflight Preview</h2>
        <div>
            <a href="{{ url()->previous() }}" class="btn btn-secondary">Back</a>
        </div>
    </div>

    <div class="alert alert-warning">
        <strong>Notice:</strong> This is a preflight preview only. No attendance has been marked.
    </div>

    <div class="card mb-3">
        <div class="card-header">Summary</div>
        <div class="card-body">
            @php $s = $result['summary']; @endphp
            <ul>
                <li>Total rows: {{ $s['total_rows'] }}</li>
                <li>Valid rows: {{ $s['valid_rows'] }}</li>
                <li>Rows with errors: {{ $s['rows_with_errors'] }}</li>
                <li>Rows with warnings: {{ $s['rows_with_warnings'] }}</li>
                <li>Would create: {{ $s['would_create'] }}</li>
                <li>Would update: {{ $s['would_update'] }}</li>
                <li>Would skip: {{ $s['would_skip'] }}</li>
            </ul>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">Create</div>
                <div class="card-body">
                    <ul>
                        @foreach($result['normalized'] as $row)
                            @if($row['action'] === 'create')
                                <li>#{{ $row['row'] }} - {{ $row['student_name'] ?? 'ID:'.$row['student_id'] }} - {{ $row['requested_status'] }}</li>
                            @endif
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Update</div>
                <div class="card-body">
                    <ul>
                        @foreach($result['normalized'] as $row)
                            @if($row['action'] === 'update')
                                <li>#{{ $row['row'] }} - {{ $row['student_name'] ?? 'ID:'.$row['student_id'] }} - {{ $row['requested_status'] }}</li>
                            @endif
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">Skipped</div>
                <div class="card-body">
                    <ul>
                        @foreach($result['normalized'] as $row)
                            @if($row['action'] === 'skip')
                                <li>#{{ $row['row'] }} - {{ $row['student_name'] ?? 'ID:'.$row['student_id'] }} - {{ $row['latest_status'] ?? 'skip' }}</li>
                            @endif
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Errors & Warnings</div>
                <div class="card-body">
                    <h6>Errors</h6>
                    <ul>
                        @foreach($result['errors'] as $k => $errs)
                            <li>Row {{ $k }}: {{ implode(', ', $errs) }}</li>
                        @endforeach
                    </ul>

                    <h6>Warnings</h6>
                    <ul>
                        @foreach($result['warnings'] as $k => $wrn)
                            <li>Row {{ $k }}: {{ implode(', ', $wrn) }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3">
        <a href="{{ url()->previous() }}" class="btn btn-secondary">Back to edit inputs</a>
    </div>
</div>
@endsection
