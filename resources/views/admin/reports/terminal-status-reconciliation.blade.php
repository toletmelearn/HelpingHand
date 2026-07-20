@extends('layouts.admin')

@section('title', 'Terminal Status Reconciliation')

@section('content')
@php
    $summary = $results['summary'];
    $sections = [
        'terminal_status_drift' => [
            'title' => 'Terminal Status Class/Section Drift',
            'issue' => 'Latest terminal status has active-looking class or section fields.',
            'action' => 'Review terminal status cleanup; possible Phase 3M-style cleanup needed.',
        ],
        'passed_out_without_log' => [
            'title' => 'Passed Out Status Without Promotion Log',
            'issue' => 'Latest passed_out status has no Passed Out promotion log.',
            'action' => 'Review missing Passed Out promotion log; do not auto-create without admin confirmation.',
        ],
        'passed_out_logs_without_latest_status' => [
            'title' => 'Passed Out Logs Without Latest Passed Out Status',
            'issue' => 'Passed Out promotion log exists, but latest status is not passed_out.',
            'action' => 'Review status/log alignment; do not auto-create status without admin confirmation.',
        ],
        'class_fk_conflicts' => [
            'title' => 'Class FK Conflicts',
            'issue' => 'class_id and school_class_id disagree.',
            'action' => 'Review class_id/school_class_id conflict; decide canonical class before repair.',
        ],
        'class_fk_null_mismatches' => [
            'title' => 'Class FK Null/Non-Null Mismatches',
            'issue' => 'Only one of class_id or school_class_id is populated.',
            'action' => 'Review class compatibility fields before repair.',
        ],
    ];
@endphp

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="bi bi-clipboard-check"></i> Terminal Status Reconciliation
        </h1>
    </div>

    <div class="alert alert-warning">
        <strong>Read-only report.</strong>
        This is a read-only reconciliation report. No data is repaired from this page.
    </div>

    <div class="row g-3 mb-4">
        @foreach([
            'terminal_status_drift' => 'Terminal Drift',
            'passed_out_without_log' => 'Passed Out Missing Log',
            'passed_out_logs_without_latest_status' => 'Logs Missing Latest Status',
            'suspicious_passed_out_logs' => 'Suspicious Logs',
            'class_fk_conflicts' => 'Class FK Conflicts',
            'class_fk_null_mismatches' => 'Class FK Mismatches',
        ] as $key => $label)
            <div class="col-md-4 col-xl-2">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ $label }}</div>
                        <div class="display-6">{{ $summary[$key] ?? 0 }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @foreach($sections as $key => $meta)
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="h5 mb-0">{{ $meta['title'] }}</h2>
            </div>
            <div class="card-body">
                @if($results[$key]->isEmpty())
                    <p class="text-muted mb-0">No records detected.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Student Name</th>
                                    <th>Latest Status</th>
                                    <th>class_id</th>
                                    <th>school_class_id</th>
                                    <th>Class</th>
                                    <th>section_id</th>
                                    <th>Section</th>
                                    <th>Matching Log</th>
                                    <th>Issue</th>
                                    <th>Recommended Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($results[$key] as $row)
                                    <tr>
                                        <td>{{ $row->student_id ?? 'N/A' }}</td>
                                        <td>{{ $row->name ?? 'N/A' }}</td>
                                        <td>{{ $row->latest_status ?? 'N/A' }}</td>
                                        <td>{{ $row->class_id ?? 'NULL' }}</td>
                                        <td>{{ $row->school_class_id ?? 'NULL' }}</td>
                                        <td>{{ $row->class ?? 'NULL' }}</td>
                                        <td>{{ $row->section_id ?? 'NULL' }}</td>
                                        <td>{{ $row->section ?? 'NULL' }}</td>
                                        <td>{{ $row->matching_log_status ?? 'N/A' }}</td>
                                        <td>{{ $meta['issue'] }}</td>
                                        <td>{{ $meta['action'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    <div class="card mb-4">
        <div class="card-header">
            <h2 class="h5 mb-0">Suspicious Passed Out Promotion Logs</h2>
        </div>
        <div class="card-body">
            @if($results['suspicious_passed_out_logs']->isEmpty())
                <p class="text-muted mb-0">No records detected.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Log ID</th>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>from_class</th>
                                <th>to_class</th>
                                <th>Issue</th>
                                <th>Recommended Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($results['suspicious_passed_out_logs'] as $row)
                                <tr>
                                    <td>{{ $row->log_id ?? 'N/A' }}</td>
                                    <td>{{ $row->student_id ?? 'N/A' }}</td>
                                    <td>{{ $row->name ?? 'N/A' }}</td>
                                    <td>{{ $row->from_class ?? 'NULL' }}</td>
                                    <td>{{ $row->to_class ?? 'NULL' }}</td>
                                    <td>Passed Out promotion log has suspicious source class.</td>
                                    <td>Review promotion log source class before any repair.</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
