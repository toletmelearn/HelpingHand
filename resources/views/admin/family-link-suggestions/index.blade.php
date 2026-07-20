@extends('layouts.admin')

@section('title', 'Family Link Suggestions')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Family Link Suggestions</h4>
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.families.index') }}">Families</a></li>
                    <li class="breadcrumb-item active">Link Suggestions</li>
                </ol>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="alert alert-info shadow-sm">
        Detected when a new student shares a phone number with an existing student -- never linked automatically. Confirm to group them as siblings for discount purposes, or dismiss if it's a coincidence (e.g. a reused number).
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Class / Section</th>
                            <th>Sibling(s)</th>
                            <th>Matched Value</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($suggestions as $suggestion)
                            @php
                                $student = $suggestion->student;
                                // "Sibling(s)" is every student this confirm action would
                                // group with $student: the whole existing family if matched
                                // against one already-confirmed group, or just the single
                                // candidate if this would create a brand new family.
                                $siblings = $suggestion->matchedFamily
                                    ? $suggestion->matchedFamily->students
                                    : ($suggestion->candidateStudent ? collect([$suggestion->candidateStudent]) : collect());

                                // Student has a raw `section` string column as well as a
                                // section() relationship of the same name -- Eloquent's magic
                                // accessor always returns the raw column value, never the
                                // relation, when both exist on a fully-selected model.
                                // getRelation() bypasses that and returns the eager-loaded
                                // Section model instead.
                                $sectionOf = fn($s) => $s && $s->relationLoaded('section') ? $s->getRelation('section') : null;
                                $studentSection = $sectionOf($student);
                            @endphp
                            <tr>
                                <td>{{ $student->name ?? 'N/A' }} ({{ $student->admission_no ?? '—' }})</td>
                                <td>
                                    @if($student)
                                        {{ $student->schoolClass->name ?? 'N/A' }}
                                        @if($studentSection) - {{ $studentSection->name }} @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if($suggestion->matchedFamily)
                                        <div class="small text-muted mb-1">Existing family: {{ $suggestion->matchedFamily->guardian_name }}</div>
                                    @endif
                                    @forelse($siblings as $sibling)
                                        @php $siblingSection = $sectionOf($sibling); @endphp
                                        <span class="badge bg-info text-dark me-1 mb-1 p-2">
                                            {{ $sibling->name }} ({{ $sibling->admission_no ?? '—' }}) --
                                            {{ $sibling->schoolClass->name ?? 'N/A' }}@if($siblingSection) - {{ $siblingSection->name }}@endif
                                        </span>
                                    @empty
                                        —
                                    @endforelse
                                </td>
                                <td>{{ $suggestion->matched_value }}</td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <form method="POST" action="{{ route('admin.family-link-suggestions.confirm', $suggestion->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success">Confirm</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.family-link-suggestions.dismiss', $suggestion->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Dismiss</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-4 text-muted">No pending suggestions.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $suggestions->links() }}</div>
        </div>
    </div>
</div>
@endsection
