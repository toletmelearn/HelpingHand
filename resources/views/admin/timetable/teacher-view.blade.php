@extends('layouts.admin')

@section('title', 'Teacher Timetable')

@section('content')
<style>
    .glass-card {
        background: rgba(255, 255, 255, 0.85);
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 12px;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.06);
    }
    table.tt-grid {
        width: 100%;
        border-collapse: collapse;
    }
    table.tt-grid th, table.tt-grid td {
        border: 1px solid #e3e6f0;
        padding: 8px;
        text-align: center;
        vertical-align: middle;
        font-size: 0.85rem;
    }
    table.tt-grid th {
        background: #4e73df;
        color: #fff;
    }
    table.tt-grid td.period-label {
        background: #f1f5f9;
        font-weight: bold;
        text-align: left;
    }
    table.tt-grid td.non-teaching {
        background: #e2e8f0;
        color: #64748b;
        font-style: italic;
    }
    table.tt-grid td.has-slot .subject { font-weight: bold; display: block; }
    table.tt-grid td.has-slot .meta { color: #555; display: block; font-size: 0.75rem; }
    table.tt-grid tr.tt-row-hidden { display: none; }
    @media print {
        .no-print { display: none !important; }
        .glass-card { box-shadow: none; border: none; }
    }
</style>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4 no-print">
        <h1 class="h3 mb-0 text-gray-800">Teacher Timetable</h1>
        <a href="{{ route('timetable.workspace') }}" class="btn btn-outline-secondary btn-sm">Back to Timetable</a>
    </div>

    <div class="card glass-card mb-4 no-print">
        <div class="card-body">
            <form action="{{ route('timetable.view.teacher') }}" method="GET" class="row align-items-end g-2">
                <div class="col-md-6">
                    <label for="teacher_id" class="font-weight-bold text-dark">Teacher (type to search)</label>
                    <select name="teacher_id" id="teacher_id" class="form-control" onchange="this.form.submit()">
                        <option value="">-- Choose a teacher --</option>
                        @foreach($teachers as $t)
                            <option value="{{ $t->id }}" {{ optional($selectedTeacher)->id === $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if($selectedTeacher)
                <div class="col-md-3">
                    <label for="tt-day-filter" class="font-weight-bold text-dark">Filter by day</label>
                    <select id="tt-day-filter" class="form-control" onchange="filterTeacherGrid()">
                        <option value="">All days</option>
                        @foreach($days as $day)
                            <option value="{{ $day }}">{{ $day }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <a href="{{ route('timetable.pdf.teacher', ['teacher_id' => $selectedTeacher->id]) }}" class="btn btn-outline-secondary shadow-sm">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                    <a href="{{ route('timetable.export.teacher', ['teacher_id' => $selectedTeacher->id]) }}" class="btn btn-outline-success shadow-sm">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                </div>
                @endif
            </form>
        </div>
    </div>

    @if($selectedTeacher)
        <div class="card glass-card mb-4">
            <div class="card-body">
                <h6 class="font-weight-bold text-dark mb-3">{{ $selectedTeacher->name }} &mdash; {{ $session->name ?? 'No current session' }}</h6>
                @if(empty($grid))
                    <p class="text-muted">No published timetable slots found for this teacher.</p>
                @else
                <div class="table-responsive">
                    <table class="tt-grid" id="tt-grid">
                        <thead>
                            <tr>
                                <th>Period</th>
                                @foreach($days as $day)
                                    <th>{{ $day }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($periods as $period)
                                <tr data-days="{{ implode(',', $days) }}">
                                    <td class="period-label">{{ $period }}</td>
                                    @foreach($days as $day)
                                        @php
                                            $meta = $periodMeta[$period][$day] ?? null;
                                            $isNonTeaching = $meta && !$meta['is_teaching'];
                                            $slot = $grid[$period][$day] ?? null;
                                        @endphp
                                        <td data-day="{{ $day }}" class="{{ $isNonTeaching ? 'non-teaching' : ($slot ? 'has-slot' : '') }}">
                                            @if($isNonTeaching)
                                                {{ $meta['label'] }}
                                            @elseif($slot)
                                                <span class="subject">{{ $slot->subject->name }}</span>
                                                <span class="meta">{{ $slot->schoolClass->name ?? '' }}{{ $slot->section ? ' '.$slot->section->name : '' }}</span>
                                                @if($slot->room_number)
                                                    <span class="meta">Room {{ $slot->room_number }}</span>
                                                @endif
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    @else
        <div class="card glass-card">
            <div class="card-body text-muted">Pick a teacher above to see their timetable.</div>
        </div>
    @endif
</div>

<script>
    function filterTeacherGrid() {
        const day = document.getElementById('tt-day-filter').value;
        document.querySelectorAll('#tt-grid td[data-day]').forEach(function (cell) {
            const show = !day || cell.dataset.day === day;
            cell.style.display = show ? '' : 'none';
        });
        document.querySelectorAll('#tt-grid thead th').forEach(function (th, idx) {
            if (idx === 0) { return; }
            const dayName = th.textContent.trim();
            th.style.display = (!day || dayName === day) ? '' : 'none';
        });
    }
</script>
@endsection
