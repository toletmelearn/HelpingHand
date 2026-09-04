@extends('layouts.admin')

@section('title', 'Room Timetable')

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
    @media print {
        .no-print { display: none !important; }
        .glass-card { box-shadow: none; border: none; }
    }
</style>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4 no-print">
        <h1 class="h3 mb-0 text-gray-800">Room Timetable</h1>
        <a href="{{ route('timetable.workspace') }}" class="btn btn-outline-secondary btn-sm">Back to Timetable</a>
    </div>

    <div class="card glass-card mb-4 no-print">
        <div class="card-body">
            <form action="{{ route('timetable.view.room') }}" method="GET" class="row align-items-end g-2">
                <div class="col-md-6">
                    <label for="room" class="font-weight-bold text-dark">Room (type to search)</label>
                    <select name="room" id="room" class="form-control" onchange="this.form.submit()">
                        <option value="">-- Choose a room --</option>
                        @forelse($rooms as $room)
                            <option value="{{ $room }}" {{ $selectedRoom === $room ? 'selected' : '' }}>{{ $room }}</option>
                        @empty
                            <option value="" disabled>No rooms found on any published slot</option>
                        @endforelse
                    </select>
                </div>
                @if($selectedRoom)
                <div class="col-md-3">
                    <label for="tt-day-filter" class="font-weight-bold text-dark">Filter by day</label>
                    <select id="tt-day-filter" class="form-control" onchange="filterRoomGrid()">
                        <option value="">All days</option>
                        @foreach($days as $day)
                            <option value="{{ $day }}">{{ $day }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <a href="{{ route('timetable.pdf.room', ['room' => $selectedRoom]) }}" class="btn btn-outline-secondary shadow-sm">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                    <a href="{{ route('timetable.export.room', ['room' => $selectedRoom]) }}" class="btn btn-outline-success shadow-sm">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                </div>
                @endif
            </form>
        </div>
    </div>

    @if($selectedRoom)
        <div class="card glass-card mb-4">
            <div class="card-body">
                <h6 class="font-weight-bold text-dark mb-3">Room {{ $selectedRoom }} &mdash; {{ $session->name ?? 'No current session' }}</h6>
                @if(empty($grid))
                    <p class="text-muted">No published timetable slots found for this room.</p>
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
                                <tr>
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
                                                <span class="meta">{{ $slot->teacher->name ?? '' }}</span>
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
            <div class="card-body text-muted">Pick a room above to see its timetable.</div>
        </div>
    @endif
</div>

<script>
    function filterRoomGrid() {
        const day = document.getElementById('tt-day-filter').value;
        document.querySelectorAll('#tt-grid td[data-day]').forEach(function (cell) {
            cell.style.display = (!day || cell.dataset.day === day) ? '' : 'none';
        });
        document.querySelectorAll('#tt-grid thead th').forEach(function (th, idx) {
            if (idx === 0) { return; }
            const dayName = th.textContent.trim();
            th.style.display = (!day || dayName === day) ? '' : 'none';
        });
    }
</script>
@endsection
