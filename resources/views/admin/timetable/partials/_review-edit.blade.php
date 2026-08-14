{{--
    Timetable Editor Phase B: extracted from grid.blade.php verbatim (plus
    the $reviewEditRoute parameterization below) so the standalone grid
    page (timetable.index) and the Workspace's "Review & Edit" section
    render the exact same markup/JS/data -- one source of truth, not a
    drifting copy. $reviewEditRoute lets the class/section selector, the
    Published/Draft toggle, and the post-generation redirect stay on
    whichever page included this partial; every mutation (add/edit/delete/
    publish/discard) already uses back() in the controller, so those need
    no parameterization -- they naturally return to whichever page the
    form was submitted from.
--}}
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
        cursor: pointer;
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
    /* Phase 5 (Locked Lessons): mirrors .delete-slot-btn's positioning, opposite corner so the two never overlap. */
    .lock-toggle-form {
        position: absolute;
        top: 5px;
        left: 5px;
    }
    .lock-toggle-btn {
        color: #b58105;
        background: transparent;
        border: none;
        cursor: pointer;
        font-size: 0.8rem;
        padding: 0;
    }
    .slot-card.is-locked-slot {
        border-left-color: #f6c23e;
        box-shadow: inset 0 0 0 1px #f6c23e;
    }
    /* Swap Engine: the first lesson picked, waiting for the second click. */
    .slot-card.swap-selected {
        outline: 3px solid #f6c23e;
        outline-offset: 1px;
    }
    /* T4b item 4: draft cells must read as visibly not-live at a glance. */
    .grid-cell.is-draft-cell {
        border: 1px dashed #f6c23e;
        background: #fffaf0;
    }
    .slot-card.is-draft-slot {
        background: #fff3cd;
        border-left: 4px solid #f6c23e;
    }
    .draft-badge {
        display: inline-block;
        background: #f6c23e;
        color: #4a3800;
        font-weight: bold;
        font-size: 0.7rem;
        padding: 1px 6px;
        border-radius: 3px;
        margin-left: 6px;
    }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Academic Timetable Scheduler</h1>
        @if($schoolClassId)
        <div>
            <a href="{{ route('timetable.pdf.class', ['school_class_id' => $schoolClassId, 'section_id' => $sectionId]) }}" class="btn btn-outline-secondary shadow-sm">
                <i class="fas fa-file-pdf"></i> Download Class Timetable PDF
            </a>
            <a href="{{ route('timetable.export.class', ['school_class_id' => $schoolClassId, 'section_id' => $sectionId]) }}" class="btn btn-outline-success shadow-sm">
                <i class="fas fa-file-excel"></i> Download Class Timetable Excel
            </a>
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addSlotModal">
                <i class="fas fa-plus fa-sm text-white-50"></i> Schedule Class Period
            </button>
            <button type="button" class="btn btn-outline-warning shadow-sm" id="swapModeToggleBtn">
                <i class="fas fa-random"></i> Swap Lessons
            </button>
        </div>
        @endif
    </div>
    @if($schoolClassId)
    <div id="swapModeBanner" class="alert alert-warning d-none mb-3">
        <i class="fas fa-random me-1"></i>
        <span id="swapModeBannerText">Swap mode: click the first lesson to swap.</span>
        <button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="swapModeCancelBtn">Cancel</button>
    </div>
    @endif

    @if($schoolClassId)
    <!-- T4b: Draft/Published toggle + Generate (Beta) -->
    <div class="d-sm-flex align-items-center justify-content-between mb-3">
        <div class="btn-group" role="group">
            <a href="{{ route($reviewEditRoute ?? 'timetable.index', ['school_class_id' => $schoolClassId, 'section_id' => $sectionId, 'status' => 'published']) }}"
               class="btn btn-sm {{ $view === 'published' ? 'btn-primary' : 'btn-outline-primary' }}">Published</a>
            <a href="{{ route($reviewEditRoute ?? 'timetable.index', ['school_class_id' => $schoolClassId, 'section_id' => $sectionId, 'status' => 'draft']) }}"
               class="btn btn-sm {{ $view === 'draft' ? 'btn-warning' : 'btn-outline-warning' }}">
                Draft @if($hasDraft)<span class="badge badge-light">●</span>@endif
            </a>
        </div>
        @can('generate', \App\Models\TimetableSlot::class)
        <div>
            @if($view === 'published' && $hasDraft)
                <span class="text-warning font-weight-bold mr-2"><i class="fas fa-exclamation-circle"></i> A draft is waiting for review.</span>
            @endif
            <button type="button" class="btn btn-sm btn-warning shadow-sm" id="generateBetaBtn" data-class-id="{{ $schoolClassId }}">
                <i class="fas fa-magic"></i> Generate (Beta)
            </button>
        </div>
        @endcan
    </div>

    @if($view === 'draft' && $activeGeneration)
        <div class="card shadow mb-3 border-left-warning">
            <div class="card-body">
                <h6 class="font-weight-bold text-dark">
                    Draft review -- generation #{{ $activeGeneration->id }}
                    ({{ $activeGeneration->placed_count }} placed, {{ $activeGeneration->unplaced_count }} unplaced
                    @if($activeGeneration->placement_percent !== null)
                        , {{ $activeGeneration->placement_percent }}%
                    @endif
                    )
                </h6>

                @php $unplacedSentences = collect($activeGeneration->report['unplaced'] ?? [])->pluck('reason'); @endphp
                @if($unplacedSentences->isNotEmpty())
                    <div class="alert alert-warning">
                        <strong>Could not place everything:</strong>
                        <ul class="mb-0">
                            @foreach($unplacedSentences as $sentence)
                                <li>{{ $sentence }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @can('publish', \App\Models\TimetableSlot::class)
                <form action="{{ route('timetable.generation.publish', $activeGeneration) }}" method="POST" class="d-inline" onsubmit="return confirm('Publish this draft? It will archive the current live timetable for this class and make the draft live.');">
                    @csrf
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Publish</button>
                </form>
                <form action="{{ route('timetable.generation.discard', $activeGeneration) }}" method="POST" class="d-inline" onsubmit="return confirm('Discard this draft? The live timetable will be untouched.');">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger"><i class="fas fa-trash"></i> Discard draft</button>
                </form>
                @endcan
            </div>
        </div>
    @elseif($view === 'draft')
        <div class="alert alert-secondary">No draft exists yet for this class. Use "Generate (Beta)" to create one.</div>
    @endif
    @endif

    <!-- Notifications -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            {{ session('error') }}
            <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <!-- Selector Card -->
    <div class="card glass-card mb-4">
        <div class="card-body">
            <form action="{{ route($reviewEditRoute ?? 'timetable.index') }}" method="GET" class="row align-items-end">
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
                        <div class="grid-cell {{ $slot && $view === 'draft' ? 'is-draft-cell' : '' }}">
                            @if($slot)
                                <div class="slot-card shadow-sm {{ $view === 'draft' ? 'is-draft-slot' : '' }} {{ $slot->is_locked ? 'is-locked-slot' : '' }}"
                                     role="button" tabindex="0" title="{{ $slot->combinedClassGroup ? 'Combined-group lessons can\'t be edited from here' : 'Click to edit this lesson' }}"
                                     data-slot="{{ json_encode([
                                         'id' => $slot->id,
                                         'school_class_id' => $slot->school_class_id,
                                         'section_id' => $slot->section_id,
                                         'bell_timing_id' => $slot->bell_timing_id,
                                         'subject_id' => $slot->subject_id,
                                         'teacher_id' => $slot->teacher_id,
                                         'co_teacher_id' => $slot->co_teacher_id,
                                         'room_number' => $slot->room_number,
                                         'combined' => (bool) $slot->combined_class_group_id,
                                         'is_locked' => (bool) $slot->is_locked,
                                     ]) }}"
                                     onclick="handleSlotCardClick(this)">
                                    <strong class="text-primary d-block">{{ $slot->subject->name }}</strong>
                                    <span class="text-muted d-block small">
                                        <i class="fas fa-chalkboard-teacher"></i> {{ $slot->teacher->name }}{{ $slot->coTeacher ? ' / ' . $slot->coTeacher->name : '' }}
                                    </span>
                                    @if($slot->room_number)
                                        <span class="badge badge-secondary mt-1">Room {{ $slot->room_number }}</span>
                                    @endif
                                    @if($slot->combinedClassGroup)
                                        <span class="badge badge-info mt-1" title="Shared with the other classes in this combined group">
                                            <i class="fas fa-users"></i> {{ $slot->combinedClassGroup->name }}
                                        </span>
                                    @endif
                                    @if($slot->is_locked)
                                        <span class="badge badge-warning mt-1" title="Locked -- Auto-Fix and Rebalance will never move this, and it survives regeneration">
                                            <i class="fas fa-lock"></i> Locked
                                        </span>
                                    @endif
                                    @if($view === 'draft')
                                        <span class="draft-badge">DRAFT</span>
                                    @endif
                                </div>
                                <form action="{{ route($slot->is_locked ? 'timetable.unlock' : 'timetable.lock', $slot->id) }}" method="POST" class="lock-toggle-form">
                                    @csrf
                                    <button type="submit" class="lock-toggle-btn" title="{{ $slot->is_locked ? 'Unlock this lesson' : 'Lock this lesson (Auto-Fix/Rebalance will never move it)' }}">
                                        <i class="fas {{ $slot->is_locked ? 'fa-lock' : 'fa-lock-open' }}"></i>
                                    </button>
                                </form>
                                <form action="{{ route('timetable.destroy', $slot->id) }}" method="POST" onsubmit="return confirm({{ $slot->combinedClassGroup ? \Illuminate\Support\Js::from('This is a combined-group lesson -- clearing it removes this period for every member class, not just this one. Continue?') : \Illuminate\Support\Js::from('Clear this slot?') }});">
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
                                    <button class="btn btn-xs btn-outline-light text-muted w-100 h-100 border-0" data-bs-toggle="modal" data-bs-target="#addSlotModal" onclick="setSlotDefaults('{{ $day }}', '{{ $timing->id }}')">
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
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('timetable.store') }}" method="POST">
                @csrf
                <input type="hidden" name="school_class_id" value="{{ $schoolClassId }}">
                <input type="hidden" name="section_id" value="{{ $sectionId }}">
                {{-- T4b item 4: the manual editor writes to whichever grid is
                     currently open, so editing a draft never touches the live timetable. --}}
                <input type="hidden" name="status" value="{{ $view }}">

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
                        <select name="subject_id" id="modal_subject_id" class="form-control" required onchange="triggerConflictCheck()">
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
                        <label for="modal_co_teacher_id" class="text-dark font-weight-bold">Co-Teacher (Optional, Team Teaching)</label>
                        <select name="co_teacher_id" id="modal_co_teacher_id" class="form-control" onchange="triggerConflictCheck()">
                            <option value="">-- None --</option>
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
                    <div id="conflictSuggestions" class="mt-2"></div>
                    <button type="button" id="autoFixTryBtn" class="btn btn-sm btn-outline-warning d-none mb-1" onclick="openAutoFixPreview('modal')">
                        <i class="fas fa-magic"></i> Try Auto-Fix
                    </button>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" id="saveSlotBtn" class="btn btn-primary">Schedule Slot</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{--
    Timetable Editor Slice 1: editing an ALREADY-PLACED lesson. Same modal
    pattern as "Add" above (same field set, same live conflict-check-as-
    you-type behaviour against the same TimetableConflictResolver-backed
    endpoint), plus Class/Section selects since an edit is allowed to move
    a lesson to a different class-section, not just a different period.
    Deliberately one combined "Period" dropdown (day + period together),
    matching the Add modal above, rather than two separate Day/Period
    selects -- bell_timing_id is the only thing the schema actually stores
    (there is no separate `day` column), and this keeps the new UI
    consistent with the existing one instead of introducing new
    day-then-period cascading-select logic.
--}}
@if($schoolClassId)
<div class="modal fade" id="editSlotModal" tabindex="-1" role="dialog" aria-labelledby="editSlotModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content glass-card">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold text-dark" id="editSlotModalLabel">Edit Lesson</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editSlotForm" method="POST">
                @csrf
                @method('PATCH')

                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_school_class_id" class="text-dark font-weight-bold">Class</label>
                        <select name="school_class_id" id="edit_school_class_id" class="form-control" required onchange="triggerEditConflictCheck()">
                            @foreach($classes as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_section_id" class="text-dark font-weight-bold">Section (Optional)</label>
                        <select name="section_id" id="edit_section_id" class="form-control" onchange="triggerEditConflictCheck()">
                            <option value="">-- Whole Class --</option>
                            @foreach($sections as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_bell_timing_id" class="text-dark font-weight-bold">Day &amp; Period</label>
                        <select name="bell_timing_id" id="edit_bell_timing_id" class="form-control" required onchange="triggerEditConflictCheck()">
                            @foreach($bellTimings as $t)
                                <option value="{{ $t->id }}">
                                    {{ $t->day_of_week }} - {{ $t->period_name }} ({{ $t->start_time->format('H:i') }} - {{ $t->end_time->format('H:i') }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_subject_id" class="text-dark font-weight-bold">Subject</label>
                        <select name="subject_id" id="edit_subject_id" class="form-control" required onchange="triggerEditConflictCheck()">
                            @foreach($subjects as $sub)
                                <option value="{{ $sub->id }}">{{ $sub->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_teacher_id" class="text-dark font-weight-bold">Teacher</label>
                        <select name="teacher_id" id="edit_teacher_id" class="form-control" required onchange="triggerEditConflictCheck()">
                            @foreach($teachers as $teacher)
                                <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_co_teacher_id" class="text-dark font-weight-bold">Co-Teacher (Optional, Team Teaching)</label>
                        <select name="co_teacher_id" id="edit_co_teacher_id" class="form-control" onchange="triggerEditConflictCheck()">
                            <option value="">-- None --</option>
                            @foreach($teachers as $teacher)
                                <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_room_number" class="text-dark font-weight-bold">Room Number (Optional)</label>
                        <input type="text" name="room_number" id="edit_room_number" class="form-control" placeholder="e.g. Lab-3A" onkeyup="triggerEditConflictCheck()">
                    </div>

                    <!-- Conflict alert area -->
                    <div id="editConflictAlert" class="alert alert-warning d-none font-weight-bold mt-3">
                        <i class="fas fa-exclamation-triangle mr-2"></i> <span id="editConflictAlertText"></span>
                    </div>
                    <div id="editConflictSuggestions" class="mt-2"></div>
                    <button type="button" id="editAutoFixTryBtn" class="btn btn-sm btn-outline-warning d-none mb-1" onclick="openAutoFixPreview('edit')">
                        <i class="fas fa-magic"></i> Try Auto-Fix
                    </button>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="saveEditBtn" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{--
    Swap Engine: read-only preview, then an explicit confirm before the
    real POST. Every conflict shown here comes straight from
    TimetableConflictResolver via TimetableSwapService::preview() -- never
    fabricated client-side. The Confirm button is disabled whenever the
    preview reports a conflict; the server re-validates independently at
    apply-time regardless (see TimetableController::swapSlots()), so this
    is a UX guard, not the actual authorization boundary.
--}}
@if($schoolClassId)
<div class="modal fade" id="swapPreviewModal" tabindex="-1" role="dialog" aria-labelledby="swapPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content glass-card">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold text-dark" id="swapPreviewModalLabel">Swap Preview</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="swapPreviewLoading" class="text-center text-muted py-3">
                    <i class="fas fa-spinner fa-spin"></i> Checking...
                </div>
                <div id="swapPreviewContent" class="d-none">
                    <div class="row">
                        <div class="col-6 border-right">
                            <h6 class="font-weight-bold">Lesson A</h6>
                            <div id="swapPreviewA" class="small"></div>
                        </div>
                        <div class="col-6">
                            <h6 class="font-weight-bold">Lesson B</h6>
                            <div id="swapPreviewB" class="small"></div>
                        </div>
                    </div>
                    <div id="swapPreviewOkNote" class="alert alert-success mt-3 d-none">
                        <i class="fas fa-check-circle mr-1"></i> Both lessons are valid at each other's period. Ready to swap.
                    </div>
                    <div id="swapPreviewConflictAlert" class="alert alert-warning mt-3 d-none">
                        <strong>Swap cannot be completed.</strong>
                        <ul id="swapPreviewConflictList" class="mb-0 mt-1"></ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="swapConfirmBtn" class="btn btn-warning" disabled>
                    <i class="fas fa-random"></i> Confirm Swap
                </button>
            </div>
        </div>
    </div>
</div>
@endif

{{--
    Phase 4 (Auto-Fix, chain repair): read-only preview, then an explicit
    confirm before the real POST -- same preview-then-confirm shape as the
    Swap Engine modal above. Every step shown here comes straight from
    TimetableAutoFixService::previewChainFix() (itself built entirely on
    TimetableConflictResolver + TimetableSuggestionService's candidate
    pool) -- never fabricated client-side. The Confirm button is disabled
    whenever no fix was found; the server re-validates every step
    independently at apply-time regardless, so this is a UX guard, not the
    actual authorization/validation boundary.
--}}
@if($schoolClassId)
<div class="modal fade" id="autoFixPreviewModal" tabindex="-1" role="dialog" aria-labelledby="autoFixPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content glass-card">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold text-dark" id="autoFixPreviewModalLabel">Auto-Fix Preview</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="autoFixPreviewLoading" class="text-center text-muted py-3">
                    <i class="fas fa-spinner fa-spin"></i> Searching for a fix...
                </div>
                <div id="autoFixPreviewContent" class="d-none">
                    <div id="autoFixPreviewOkNote" class="alert alert-success d-none">
                        <i class="fas fa-check-circle mr-1"></i> <span id="autoFixPreviewOkText"></span>
                    </div>
                    <div id="autoFixPreviewFailNote" class="alert alert-warning d-none"></div>
                    <ol id="autoFixPreviewSteps" class="mb-0"></ol>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="autoFixConfirmBtn" class="btn btn-warning" disabled>
                    <i class="fas fa-magic"></i> Apply Fix
                </button>
            </div>
        </div>
    </div>
</div>
@endif

<script>
    function setSlotDefaults(day, timingId) {
        document.getElementById('modal_bell_timing_id').value = timingId;
        triggerConflictCheck();
    }

    function triggerConflictCheck() {
        const timingId = document.getElementById('modal_bell_timing_id').value;
        const teacherId = document.getElementById('modal_teacher_id').value;
        const coTeacherId = document.getElementById('modal_co_teacher_id').value;
        const subjectId = document.getElementById('modal_subject_id').value;
        const room = document.getElementById('modal_room_number').value;

        if (!timingId || !teacherId) {
            document.getElementById('conflictAlert').classList.add('d-none');
            document.getElementById('saveSlotBtn').disabled = false;
            return;
        }

        const status = @json($view ?? 'published');
        const schoolClassId = @json($schoolClassId);
        const sectionId = @json($sectionId);

        fetch(`{{ route('timetable.check-conflicts') }}?bell_timing_id=${timingId}&teacher_id=${teacherId}&co_teacher_id=${coTeacherId}&subject_id=${subjectId}&room_number=${room}&status=${status}&school_class_id=${schoolClassId ?? ''}&section_id=${sectionId ?? ''}`)
            .then(res => res.json())
            .then(data => {
                const alertDiv = document.getElementById('conflictAlert');
                const textSpan = document.getElementById('conflictAlertText');
                const saveBtn = document.getElementById('saveSlotBtn');
                const suggestionsDiv = document.getElementById('conflictSuggestions');
                suggestionsDiv.innerHTML = '';

                if (data.conflict) {
                    alertDiv.classList.remove('d-none');
                    textSpan.innerText = data.message;
                    saveBtn.disabled = true; // Disable scheduling if there's conflict
                    renderSuggestions(data.suggestions, suggestionsDiv, 'modal_bell_timing_id', triggerConflictCheck);
                    document.getElementById('autoFixTryBtn').classList.remove('d-none');
                } else {
                    alertDiv.classList.add('d-none');
                    saveBtn.disabled = false;
                    document.getElementById('autoFixTryBtn').classList.add('d-none');
                }
            });
    }

    /**
     * Timetable Editor Slice 1: the SAME check-conflicts endpoint the Add
     * modal already uses, TimetableConflictResolver end to end -- no
     * second/parallel conflict engine. Passing `id` makes the server-side
     * resolver ignore THIS row itself (ignore_slot_id), exactly the same
     * mechanism the controller's update() action uses for its own,
     * authoritative, final check -- this live check is a preview only,
     * never trusted for the actual save.
     */
    function triggerEditConflictCheck() {
        const slotId = document.getElementById('editSlotForm').dataset.slotId;
        const timingId = document.getElementById('edit_bell_timing_id').value;
        const teacherId = document.getElementById('edit_teacher_id').value;
        const coTeacherId = document.getElementById('edit_co_teacher_id').value;
        const subjectId = document.getElementById('edit_subject_id').value;
        const room = document.getElementById('edit_room_number').value;
        const schoolClassId = document.getElementById('edit_school_class_id').value;
        const sectionId = document.getElementById('edit_section_id').value;

        const alertDiv = document.getElementById('editConflictAlert');
        const saveBtn = document.getElementById('saveEditBtn');

        if (!timingId || !teacherId || !schoolClassId) {
            alertDiv.classList.add('d-none');
            saveBtn.disabled = false;
            return;
        }

        const status = @json($view ?? 'published');

        fetch(`{{ route('timetable.check-conflicts') }}?bell_timing_id=${timingId}&teacher_id=${teacherId}&co_teacher_id=${coTeacherId}&subject_id=${subjectId}&room_number=${room}&status=${status}&school_class_id=${schoolClassId}&section_id=${sectionId}&id=${slotId}`)
            .then(res => res.json())
            .then(data => {
                const textSpan = document.getElementById('editConflictAlertText');
                const suggestionsDiv = document.getElementById('editConflictSuggestions');
                suggestionsDiv.innerHTML = '';

                if (data.conflict) {
                    alertDiv.classList.remove('d-none');
                    textSpan.innerText = data.message;
                    saveBtn.disabled = true;
                    renderSuggestions(data.suggestions, suggestionsDiv, 'edit_bell_timing_id', triggerEditConflictCheck);
                    document.getElementById('editAutoFixTryBtn').classList.remove('d-none');
                } else {
                    alertDiv.classList.add('d-none');
                    saveBtn.disabled = false;
                    document.getElementById('editAutoFixTryBtn').classList.add('d-none');
                }
            });
    }

    /**
     * Opens the Edit modal for an occupied cell, pre-filled from the row's
     * own data-slot attribute (real server-rendered data, not guessed).
     * Combined-group rows are out of scope for this slice -- see
     * TimetableController::update() -- so clicking one just explains why
     * instead of opening a modal it would only reject.
     */
    function openEditModal(cardEl) {
        const slot = JSON.parse(cardEl.dataset.slot);

        if (slot.combined) {
            alert('This lesson is part of a combined class group. Clear it and re-place it via Combined Groups to change it -- editing a combined lesson from a single cell isn\'t supported yet.');
            return;
        }

        const form = document.getElementById('editSlotForm');
        form.action = @json(url('/admin/timetable')) + '/' + slot.id;
        form.dataset.slotId = slot.id;

        document.getElementById('edit_school_class_id').value = slot.school_class_id;
        document.getElementById('edit_section_id').value = slot.section_id ?? '';
        document.getElementById('edit_bell_timing_id').value = slot.bell_timing_id;
        document.getElementById('edit_subject_id').value = slot.subject_id;
        document.getElementById('edit_teacher_id').value = slot.teacher_id;
        document.getElementById('edit_co_teacher_id').value = slot.co_teacher_id ?? '';
        document.getElementById('edit_room_number').value = slot.room_number ?? '';
        document.getElementById('editConflictAlert').classList.add('d-none');
        document.getElementById('editConflictSuggestions').innerHTML = '';
        document.getElementById('editAutoFixTryBtn').classList.add('d-none');
        document.getElementById('saveEditBtn').disabled = false;

        bootstrap.Modal.getOrCreateInstance(document.getElementById('editSlotModal')).show();
    }

    // --- Swap Engine -------------------------------------------------
    // Two-click selection (click lesson A, click lesson B) rather than
    // drag-and-drop -- that's a later phase. Every conflict shown comes
    // from TimetableConflictResolver via TimetableSwapService; nothing
    // here fabricates a conflict or a suggestion.
    let swapModeActive = false;
    let swapSelectedSlotId = null;

    document.getElementById('swapModeToggleBtn')?.addEventListener('click', function () {
        swapModeActive = !swapModeActive;
        swapSelectedSlotId = null;
        updateSwapModeUi();
    });

    document.getElementById('swapModeCancelBtn')?.addEventListener('click', function () {
        swapModeActive = false;
        swapSelectedSlotId = null;
        updateSwapModeUi();
    });

    function updateSwapModeUi() {
        const banner = document.getElementById('swapModeBanner');
        const toggleBtn = document.getElementById('swapModeToggleBtn');
        document.querySelectorAll('.slot-card.swap-selected').forEach(el => el.classList.remove('swap-selected'));

        if (!swapModeActive) {
            banner?.classList.add('d-none');
            toggleBtn?.classList.remove('active');
            return;
        }

        banner?.classList.remove('d-none');
        toggleBtn?.classList.add('active');
        const bannerText = document.getElementById('swapModeBannerText');
        if (bannerText) {
            bannerText.innerText = swapSelectedSlotId
                ? 'Swap mode: click the second lesson to swap with.'
                : 'Swap mode: click the first lesson to swap.';
        }
    }

    /**
     * Dispatches a slot-card click to the normal Edit flow, or, while Swap
     * mode is active, the two-click swap-selection flow -- one click
     * handler, so there's no second, competing click-target mechanism.
     */
    function handleSlotCardClick(cardEl) {
        if (!swapModeActive) {
            openEditModal(cardEl);
            return;
        }

        const slot = JSON.parse(cardEl.dataset.slot);

        if (slot.combined) {
            alert('This lesson is part of a combined class group and can\'t be swapped from a single cell. Clear it and re-place it via Combined Groups instead.');
            return;
        }

        if (swapSelectedSlotId === null) {
            swapSelectedSlotId = slot.id;
            cardEl.classList.add('swap-selected');
            updateSwapModeUi();
            return;
        }

        if (swapSelectedSlotId === slot.id) {
            // Same lesson clicked twice -- deselect, not a self-swap attempt.
            swapSelectedSlotId = null;
            updateSwapModeUi();
            return;
        }

        const slotAId = swapSelectedSlotId;
        const slotBId = slot.id;
        swapModeActive = false;
        swapSelectedSlotId = null;
        updateSwapModeUi();

        openSwapPreview(slotAId, slotBId);
    }

    function describeSwapSlot(desc) {
        if (!desc) return '<span class="text-muted">Unknown</span>';
        const parts = [];
        parts.push(`<strong>${desc.class ?? ''}${desc.section ? ' ' + desc.section : ''}</strong>`);
        parts.push(desc.subject ?? '');
        parts.push(`<i class="fas fa-chalkboard-teacher"></i> ${desc.teacher ?? ''}${desc.co_teacher ? ' / ' + desc.co_teacher : ''}`);
        if (desc.room_number) { parts.push(`Room ${desc.room_number}`); }
        parts.push(`<span class="text-muted">${desc.day_of_week ?? ''} ${desc.period_name ?? ''}</span>`);
        return parts.join('<br>');
    }

    /** Read-only preview -- calls timetable.swap-preview, never mutates anything. */
    function openSwapPreview(slotAId, slotBId) {
        const modalEl = document.getElementById('swapPreviewModal');
        document.getElementById('swapPreviewLoading').classList.remove('d-none');
        document.getElementById('swapPreviewContent').classList.add('d-none');
        document.getElementById('swapPreviewOkNote').classList.add('d-none');
        document.getElementById('swapPreviewConflictAlert').classList.add('d-none');
        document.getElementById('swapConfirmBtn').disabled = true;

        bootstrap.Modal.getOrCreateInstance(modalEl).show();

        fetch(`{{ route('timetable.swap-preview') }}?slot_a_id=${slotAId}&slot_b_id=${slotBId}`)
            .then(res => res.json())
            .then(data => {
                document.getElementById('swapPreviewLoading').classList.add('d-none');
                document.getElementById('swapPreviewContent').classList.remove('d-none');
                document.getElementById('swapPreviewA').innerHTML = describeSwapSlot(data.slot_a);
                document.getElementById('swapPreviewB').innerHTML = describeSwapSlot(data.slot_b);

                if (data.ok) {
                    document.getElementById('swapPreviewOkNote').classList.remove('d-none');
                    document.getElementById('swapConfirmBtn').disabled = false;
                } else {
                    const list = document.getElementById('swapPreviewConflictList');
                    list.innerHTML = '';
                    const conflicts = data.conflicts || [];
                    if (conflicts.length) {
                        conflicts.forEach(c => {
                            const li = document.createElement('li');
                            li.innerText = c.message;
                            list.appendChild(li);
                        });
                    } else {
                        const li = document.createElement('li');
                        li.innerText = data.message || 'This swap is not valid.';
                        list.appendChild(li);
                    }
                    document.getElementById('swapPreviewConflictAlert').classList.remove('d-none');
                    document.getElementById('swapConfirmBtn').disabled = true;
                }

                document.getElementById('swapConfirmBtn').dataset.slotAId = slotAId;
                document.getElementById('swapConfirmBtn').dataset.slotBId = slotBId;
            })
            .catch(() => {
                document.getElementById('swapPreviewLoading').classList.add('d-none');
                document.getElementById('swapPreviewContent').classList.remove('d-none');
                document.getElementById('swapPreviewConflictAlert').classList.remove('d-none');
                document.getElementById('swapPreviewConflictList').innerHTML = '<li>Could not check this swap. Please try again.</li>';
            });
    }

    document.getElementById('swapConfirmBtn')?.addEventListener('click', function () {
        const btn = this;
        const slotAId = btn.dataset.slotAId;
        const slotBId = btn.dataset.slotBId;
        btn.disabled = true;
        btn.innerText = 'Swapping...';

        fetch(@json(route('timetable.swap')), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
            },
            body: JSON.stringify({ slot_a_id: slotAId, slot_b_id: slotBId }),
        })
            .then(res => res.json().then(data => ({ status: res.status, data })))
            .then(({ status, data }) => {
                if (status === 200 && data.applied) {
                    window.location.reload();
                } else {
                    const conflictText = (data.conflicts || []).map(c => c.message).join('\n');
                    alert('Swap failed: ' + (data.message || 'Unknown error') + (conflictText ? '\n\n' + conflictText : ''));
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-random"></i> Confirm Swap';
                }
            })
            .catch(() => {
                alert('Swap failed: could not reach the server. Please try again.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-random"></i> Confirm Swap';
            });
    });

    // --- Auto-Fix (chain repair) --------------------------------------
    // Reads whichever modal (Add or Edit) is currently open, previews a
    // chain fix via TimetableAutoFixService::previewChainFix(), and on
    // confirm POSTs the exact previewed steps to applyChainFix() -- the
    // server re-validates every step against live data regardless (see
    // TimetableController::autoFixApplyChain()), this is a UX guard only.
    let autoFixCurrentSteps = [];

    function autoFixReadFields(context) {
        const prefix = context === 'edit' ? 'edit_' : 'modal_';
        const schoolClassId = context === 'edit'
            ? document.getElementById('edit_school_class_id').value
            : @json($schoolClassId);
        const sectionId = context === 'edit'
            ? document.getElementById('edit_section_id').value
            : @json($sectionId);

        return {
            school_class_id: schoolClassId ?? '',
            // URLSearchParams stringifies JS null as the literal text
            // "null", which would fail the section_id exists: validation --
            // an empty string is correctly treated as "no section" instead.
            section_id: sectionId ?? '',
            bell_timing_id: document.getElementById(prefix + 'bell_timing_id').value,
            teacher_id: document.getElementById(prefix + 'teacher_id').value,
            co_teacher_id: document.getElementById(prefix + 'co_teacher_id').value,
            subject_id: document.getElementById(prefix + 'subject_id').value,
            room_number: document.getElementById(prefix + 'room_number').value,
            // Whichever grid (draft/published) is currently open -- without
            // this, the server-side check defaults to 'published' and a
            // conflict against draft data would be silently missed. Same
            // hidden-state pattern triggerConflictCheck() already uses.
            status: @json($view ?? 'published'),
            // Issue #14: the generation this draft review is FOR (absent/''
            // on the plain published grid, where there is no active
            // generation at all) -- without this, a lesson Auto-Fix places
            // while resolving this generation's unplaced lessons is never
            // captured by Publish's generation-scoped promote sweep, and
            // stays a permanently invisible draft. Same empty-string-not-
            // literal-"null" guard as section_id above: openAutoFixPreview()
            // sends these fields via URLSearchParams, which would otherwise
            // stringify a JS null into the literal text "null".
            timetable_generation_id: @json($activeGeneration->id ?? null) ?? '',
        };
    }

    function openAutoFixPreview(context) {
        const fields = autoFixReadFields(context);
        const modalEl = document.getElementById('autoFixPreviewModal');

        document.getElementById('autoFixPreviewLoading').classList.remove('d-none');
        document.getElementById('autoFixPreviewContent').classList.add('d-none');
        document.getElementById('autoFixPreviewOkNote').classList.add('d-none');
        document.getElementById('autoFixPreviewFailNote').classList.add('d-none');
        document.getElementById('autoFixPreviewSteps').innerHTML = '';
        document.getElementById('autoFixConfirmBtn').disabled = true;

        bootstrap.Modal.getOrCreateInstance(modalEl).show();

        const params = new URLSearchParams(fields).toString();

        fetch(`{{ route('timetable.auto-fix.preview-chain') }}?${params}`, {
            headers: { 'Accept': 'application/json' },
        })
            .then(res => res.json())
            .then(data => {
                document.getElementById('autoFixPreviewLoading').classList.add('d-none');
                document.getElementById('autoFixPreviewContent').classList.remove('d-none');

                const stepsList = document.getElementById('autoFixPreviewSteps');
                (data.steps || []).forEach(step => {
                    const li = document.createElement('li');
                    li.innerText = step.description;
                    stepsList.appendChild(li);
                });

                if (data.ok) {
                    autoFixCurrentSteps = (data.steps || []).map(s => ({ slot_id: s.slot_id, to_bell_timing_id: s.to_bell_timing_id }));
                    document.getElementById('autoFixPreviewOkText').innerText = data.message;
                    document.getElementById('autoFixPreviewOkNote').classList.remove('d-none');
                    document.getElementById('autoFixConfirmBtn').disabled = false;
                } else {
                    autoFixCurrentSteps = [];
                    document.getElementById('autoFixPreviewFailNote').innerText = data.message || 'No fix could be found.';
                    document.getElementById('autoFixPreviewFailNote').classList.remove('d-none');
                    document.getElementById('autoFixConfirmBtn').disabled = true;
                }

                document.getElementById('autoFixConfirmBtn').dataset.context = context;
            })
            .catch(() => {
                document.getElementById('autoFixPreviewLoading').classList.add('d-none');
                document.getElementById('autoFixPreviewContent').classList.remove('d-none');
                document.getElementById('autoFixPreviewFailNote').innerText = 'Could not check for a fix. Please try again.';
                document.getElementById('autoFixPreviewFailNote').classList.remove('d-none');
            });
    }

    document.getElementById('autoFixConfirmBtn')?.addEventListener('click', function () {
        const btn = this;
        const context = btn.dataset.context;
        const fields = autoFixReadFields(context);
        btn.disabled = true;
        btn.innerText = 'Applying...';

        fetch(@json(route('timetable.auto-fix.apply-chain')), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
            },
            body: JSON.stringify({ ...fields, steps: autoFixCurrentSteps }),
        })
            .then(res => res.json().then(data => ({ status: res.status, data })))
            .then(({ status, data }) => {
                if (status === 200 && data.applied) {
                    window.location.reload();
                } else {
                    alert('Auto-Fix failed: ' + (data.message || 'Unknown error'));
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-magic"></i> Apply Fix';
                }
            })
            .catch(() => {
                alert('Auto-Fix failed: could not reach the server. Please try again.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-magic"></i> Apply Fix';
            });
    });

    /**
     * Phase 3 (Smart Suggestions): every option shown here was already
     * validated clean by TimetableConflictResolver via
     * TimetableSuggestionService -- clicking a "move this lesson" option
     * just re-points the period dropdown and re-checks (still a real,
     * server-validated check, not a client-side shortcut). "Relocate the
     * other lesson instead" options are shown as information only --
     * actually moving someone else's lesson is the Auto-Fix "Try Auto-Fix"
     * button above, a separate preview-then-confirm flow of its own.
     * Shared by both the Add and Edit modals -- one rendering function,
     * no duplicated suggestion-display logic between them.
     */
    function renderSuggestions(suggestions, container, targetFieldId, recheckFn) {
        if (!suggestions) return;

        (suggestions.move_lesson || []).forEach(s => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-success mr-1 mb-1';
            btn.innerText = s.description;
            btn.addEventListener('click', function () {
                document.getElementById(targetFieldId).value = s.bell_timing_id;
                recheckFn();
            });
            container.appendChild(btn);
        });

        (suggestions.relocate_blocker || []).forEach(s => {
            const note = document.createElement('div');
            note.className = 'small text-muted mb-1';
            note.innerText = 'Alternative: ' + s.description + ' (not applied automatically).';
            container.appendChild(note);
        });
    }

    @if(session('edit_conflict'))
        document.addEventListener('DOMContentLoaded', function () {
            const conflict = @json(session('edit_conflict'));
            const old = @json(old());
            // Re-open with whatever the user last submitted (old()), not the
            // pre-edit values, so the rejected attempt is visible to fix.
            const form = document.getElementById('editSlotForm');
            if (!form) { return; }
            form.action = @json(url('/admin/timetable')) + '/' + conflict.slot_id;
            form.dataset.slotId = conflict.slot_id;
            document.getElementById('edit_school_class_id').value = old.school_class_id ?? '';
            document.getElementById('edit_section_id').value = old.section_id ?? '';
            document.getElementById('edit_bell_timing_id').value = old.bell_timing_id ?? '';
            document.getElementById('edit_subject_id').value = old.subject_id ?? '';
            document.getElementById('edit_teacher_id').value = old.teacher_id ?? '';
            document.getElementById('edit_co_teacher_id').value = old.co_teacher_id ?? '';
            document.getElementById('edit_room_number').value = old.room_number ?? '';

            const alertDiv = document.getElementById('editConflictAlert');
            alertDiv.classList.remove('d-none');
            document.getElementById('editConflictAlertText').innerText = conflict.message;
            renderSuggestions({ move_lesson: conflict.suggestions }, document.getElementById('editConflictSuggestions'), 'edit_bell_timing_id', triggerEditConflictCheck);

            // Bootstrap 5's own jQuery-interop shim ($.fn.modal) attaches
            // itself lazily -- racing this against a DOMContentLoaded
            // listener registered earlier in the document (this one) can
            // fire before that attachment happens. bootstrap.Modal is the
            // bundle's own native export, available synchronously the
            // moment its script tag runs -- no such race.
            bootstrap.Modal.getOrCreateInstance(document.getElementById('editSlotModal')).show();
        });
    @endif
</script>
@endif

@can('generate', \App\Models\TimetableSlot::class)
@if($schoolClassId)
<script>
    document.getElementById('generateBetaBtn')?.addEventListener('click', function () {
        // T4b item 3: the confirm dialog must say, in plain words, that
        // this creates a DRAFT and touches nothing live.
        if (!confirm('Generate (Beta) will create a DRAFT timetable proposal for this class. It does NOT change the live, published timetable -- nothing goes live until you review and Publish it. Continue?')) {
            return;
        }

        const btn = this;
        const classId = btn.dataset.classId;
        btn.disabled = true;
        btn.innerText = 'Generating...';

        fetch(@json(route('timetable.generate')), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
            },
            body: JSON.stringify({ school_class_ids: [classId] }),
        })
        .then(res => res.json())
        .then(data => pollGenerationStatus(data.status_url, btn))
        .catch(() => {
            btn.disabled = false;
            btn.innerText = 'Generate (Beta)';
            alert('Could not start generation. Please try again.');
        });
    });

    function pollGenerationStatus(statusUrl, btn) {
        const poll = setInterval(() => {
            fetch(statusUrl)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'completed') {
                        clearInterval(poll);
                        const pct = data.placement_percent !== null ? `${data.placement_percent}%` : 'n/a';
                        const unplacedList = (data.unplaced || []).map(u => u.reason).join('\n');
                        alert(`Generation complete: ${data.placed_count} placed, ${data.unplaced_count} unplaced (${pct}).` + (unplacedList ? `\n\nCould not place:\n${unplacedList}` : ''));
                        window.location = @json(route($reviewEditRoute ?? 'timetable.index')) + `?school_class_id=${btn.dataset.classId}&status=draft`;
                    } else if (data.status === 'failed') {
                        clearInterval(poll);
                        btn.disabled = false;
                        btn.innerText = 'Generate (Beta)';
                        alert('Generation failed: ' + (data.error || 'unknown error'));
                    }
                    // queued/running: keep polling
                })
                .catch(() => clearInterval(poll));
        }, 2000);
    }
</script>
@endif
@endcan
