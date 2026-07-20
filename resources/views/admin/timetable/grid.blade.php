@extends('layouts.admin')

@section('title', 'Academic Timetable Scheduler')

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
    .timetable-grid {
        display: grid;
        grid-template-columns: 120px repeat(6, 1fr);
        gap: 10px;
    }
    .grid-header {
        background: #4e73df;
        color: white;
        text-align: center;
        padding: 10px;
        font-weight: bold;
        border-radius: 6px;
    }
    .grid-cell {
        background: #f8f9fc;
        border: 1px dashed #e3e6f0;
        min-height: 80px;
        padding: 8px;
        border-radius: 6px;
        position: relative;
    }
    .slot-card {
        background: #eef2fa;
        border-left: 4px solid #4e73df;
        padding: 6px;
        border-radius: 4px;
        font-size: 0.85rem;
    }
    .delete-slot-btn {
        position: absolute;
        top: 5px;
        right: 5px;
        color: #e74a3b;
        background: transparent;
        border: none;
        cursor: pointer;
        font-size: 0.8rem;
    }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Academic Timetable Scheduler</h1>
        @if($schoolClassId)
        <button class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#addSlotModal">
            <i class="fas fa-plus fa-sm text-white-50"></i> Schedule Class Period
        </button>
        @endif
    </div>

    <!-- Notifications -->
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

    <!-- Selector Card -->
    <div class="card glass-card mb-4">
        <div class="card-body">
            <form action="{{ route('timetable.index') }}" method="GET" class="row align-items-end">
                <div class="col-md-4">
                    <label for="school_class_id" class="font-weight-bold text-dark">Select Class</label>
                    <select name="school_class_id" id="school_class_id" class="form-control" required>
                        <option value="">-- Choose Class --</option>
                        @foreach($classes as $c)
                            <option value="{{ $c->id }}" {{ $schoolClassId == $c->id ? 'selected' : '' }}>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="section_id" class="font-weight-bold text-dark">Select Section (Optional)</label>
                    <select name="section_id" id="section_id" class="form-control">
                        <option value="">-- All Sections --</option>
                        @foreach($sections as $s)
                            <option value="{{ $s->id }}" {{ $sectionId == $s->id ? 'selected' : '' }}>
                                {{ $s->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-success btn-block">Load Timetable Grid</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Grid View -->
    @if($schoolClassId)
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="timetable-grid">
                <!-- Headers -->
                <div class="grid-header">Period</div>
                <div class="grid-header">Monday</div>
                <div class="grid-header">Tuesday</div>
                <div class="grid-header">Wednesday</div>
                <div class="grid-header">Thursday</div>
                <div class="grid-header">Friday</div>
                <div class="grid-header">Saturday</div>

                <!-- Periods -->
                @php
                    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                    $groupedTimings = $bellTimings->groupBy('period_name');
                @endphp

                @foreach($groupedTimings as $periodName => $timings)
                    @php $firstTiming = $timings->first(); @endphp
                    <div class="grid-cell font-weight-bold d-flex flex-column justify-content-center align-items-center text-dark" style="background:#eaecf4;">
                        <div>{{ $periodName }}</div>
                        <small class="text-muted">{{ $firstTiming->start_time->format('H:i') }} - {{ $firstTiming->end_time->format('H:i') }}</small>
                    </div>

                    @foreach($days as $day)
                        @php
                            $timing = $timings->where('day_of_week', $day)->first();
                            $slot = $timing ? $slots->where('bell_timing_id', $timing->id)->first() : null;
                        @endphp
                        <div class="grid-cell">
                            @if($slot)
                                <div class="slot-card shadow-sm">
                                    <strong class="text-primary d-block">{{ $slot->subject->name }}</strong>
                                    <span class="text-muted d-block small"><i class="fas fa-chalkboard-teacher"></i> {{ $slot->teacher->name }}</span>
                                    @if($slot->room_number)
                                        <span class="badge badge-secondary mt-1">Room {{ $slot->room_number }}</span>
                                    @endif
                                </div>
                                <form action="{{ route('timetable.destroy', $slot->id) }}" method="POST" onsubmit="return confirm('Clear this slot?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="delete-slot-btn" title="Clear slot">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            @elseif($timing)
                                @if($timing->is_break)
                                    <div class="text-center text-muted font-italic small mt-3">Break</div>
                                @else
                                    <button class="btn btn-xs btn-outline-light text-muted w-100 h-100 border-0" data-toggle="modal" data-target="#addSlotModal" onclick="setSlotDefaults('{{ $day }}', '{{ $timing->id }}')">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                @endif
                            @else
                                <div class="text-center text-muted font-italic small mt-3">N/A</div>
                            @endif
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>
    </div>
    @else
        <div class="text-center py-5">
            <i class="fas fa-calendar-alt fa-3x text-gray-300 mb-3"></i>
            <h5 class="text-gray-500">Select class and section above to display class schedule.</h5>
        </div>
    @endif
</div>

<!-- Add/Edit Slot Modal -->
@if($schoolClassId)
<div class="modal fade" id="addSlotModal" tabindex="-1" role="dialog" aria-labelledby="addSlotModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content glass-card">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold text-dark" id="addSlotModalLabel">Schedule Class Period</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('timetable.store') }}" method="POST">
                @csrf
                <input type="hidden" name="school_class_id" value="{{ $schoolClassId }}">
                <input type="hidden" name="section_id" value="{{ $sectionId }}">

                <div class="modal-body">
                    <div class="form-group">
                        <label for="modal_bell_timing_id" class="text-dark font-weight-bold">Select Period Slot</label>
                        <select name="bell_timing_id" id="modal_bell_timing_id" class="form-control" required onchange="triggerConflictCheck()">
                            <option value="">-- Choose Period --</option>
                            @foreach($bellTimings as $t)
                                <option value="{{ $t->id }}">
                                    {{ $t->day_of_week }} - {{ $t->period_name }} ({{ $t->start_time->format('H:i') }} - {{ $t->end_time->format('H:i') }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="modal_subject_id" class="text-dark font-weight-bold">Subject</label>
                        <select name="subject_id" id="modal_subject_id" class="form-control" required>
                            <option value="">-- Choose Subject --</option>
                            @foreach($subjects as $sub)
                                <option value="{{ $sub->id }}">{{ $sub->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="modal_teacher_id" class="text-dark font-weight-bold">Teacher</label>
                        <select name="teacher_id" id="modal_teacher_id" class="form-control" required onchange="triggerConflictCheck()">
                            <option value="">-- Choose Teacher --</option>
                            @foreach($teachers as $teacher)
                                <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="modal_room_number" class="text-dark font-weight-bold">Room Number (Optional)</label>
                        <input type="text" name="room_number" id="modal_room_number" class="form-control" placeholder="e.g. Lab-3A" onkeyup="triggerConflictCheck()">
                    </div>

                    <!-- Conflict alert area -->
                    <div id="conflictAlert" class="alert alert-warning d-none font-weight-bold mt-3">
                        <i class="fas fa-exclamation-triangle mr-2"></i> <span id="conflictAlertText"></span>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" id="saveSlotBtn" class="btn btn-primary">Schedule Slot</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function setSlotDefaults(day, timingId) {
        document.getElementById('modal_bell_timing_id').value = timingId;
        triggerConflictCheck();
    }

    function triggerConflictCheck() {
        const timingId = document.getElementById('modal_bell_timing_id').value;
        const teacherId = document.getElementById('modal_teacher_id').value;
        const room = document.getElementById('modal_room_number').value;

        if (!timingId || !teacherId) {
            document.getElementById('conflictAlert').classList.add('d-none');
            document.getElementById('saveSlotBtn').disabled = false;
            return;
        }

        fetch(`{{ route('timetable.check-conflicts') }}?bell_timing_id=${timingId}&teacher_id=${teacherId}&room_number=${room}`)
            .then(res => res.json())
            .then(data => {
                const alertDiv = document.getElementById('conflictAlert');
                const textSpan = document.getElementById('conflictAlertText');
                const saveBtn = document.getElementById('saveSlotBtn');

                if (data.conflict) {
                    alertDiv.classList.remove('d-none');
                    textSpan.innerText = data.message;
                    saveBtn.disabled = true; // Disable scheduling if there's conflict
                } else {
                    alertDiv.classList.add('d-none');
                    saveBtn.disabled = false;
                }
            });
    }
</script>
@endif
@endsection
