@extends('layouts.admin')

@section('title', 'Timetable Editor')

@section('content')
<style>
    .glass-card {
        background: rgba(255, 255, 255, 0.85);
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 12px;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.06);
    }
    .workspace-stat-card {
        border-radius: 10px;
        padding: 1rem;
        background: #f8f9fc;
        border: 1px solid #e3e6f0;
        height: 100%;
    }
    .workspace-stat-card .stat-value {
        font-size: 1.5rem;
        font-weight: 700;
    }
    .workspace-phase-note {
        border-left: 4px solid #d1d3e2;
        background: #f8f9fc;
        padding: 0.9rem 1.1rem;
        border-radius: 6px;
        color: #6e707e;
    }
</style>

@php
    // Land straight on Review & Edit when a class was already selected
    // (e.g. coming from a "Load Timetable Grid" submit) instead of always
    // resetting to Home.
    $defaultTab = $schoolClassId ? 'review' : 'home';
    $readinessBadge = [
        'ready' => ['label' => 'Ready', 'class' => 'badge-success'],
        'warning' => ['label' => 'Warnings', 'class' => 'badge-warning'],
        'blocked' => ['label' => 'Blocked', 'class' => 'badge-danger'],
    ][$readiness];
    $statusBadge = [
        'published' => ['label' => 'Published', 'class' => 'badge-primary'],
        'draft' => ['label' => 'Draft only (nothing published yet)', 'class' => 'badge-warning'],
        'none' => ['label' => 'No timetable yet', 'class' => 'badge-secondary'],
    ][$timetableStatus];
@endphp

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Timetable Editor</h1>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
    @endif

    <ul class="nav nav-tabs mb-4" id="workspaceTabs" role="tablist">
        <li class="nav-item"><a class="nav-link {{ $defaultTab === 'home' ? 'active' : '' }}" id="tab-home-link" data-bs-toggle="tab" href="#tab-home" role="tab">Home</a></li>
        <li class="nav-item"><a class="nav-link" id="tab-setup-link" data-bs-toggle="tab" href="#tab-setup" role="tab">Setup</a></li>
        <li class="nav-item"><a class="nav-link" id="tab-generate-link" data-bs-toggle="tab" href="#tab-generate" role="tab">Generate</a></li>
        <li class="nav-item"><a class="nav-link {{ $defaultTab === 'review' ? 'active' : '' }}" id="tab-review-link" data-bs-toggle="tab" href="#tab-review" role="tab">Review &amp; Edit</a></li>
        <li class="nav-item"><a class="nav-link" id="tab-conflicts-link" data-bs-toggle="tab" href="#tab-conflicts" role="tab">Conflicts</a></li>
        <li class="nav-item"><a class="nav-link" id="tab-suggestions-link" data-bs-toggle="tab" href="#tab-suggestions" role="tab">Suggestions</a></li>
        <li class="nav-item"><a class="nav-link" id="tab-autofix-link" data-bs-toggle="tab" href="#tab-autofix" role="tab">Auto-Fix</a></li>
        <li class="nav-item"><a class="nav-link" id="tab-rebalance-link" data-bs-toggle="tab" href="#tab-rebalance" role="tab">Rebalance</a></li>
        <li class="nav-item"><a class="nav-link" id="tab-publish-link" data-bs-toggle="tab" href="#tab-publish" role="tab">Publish</a></li>
    </ul>

    <div class="tab-content" id="workspaceTabContent">

        {{-- ============ HOME ============ --}}
        <div class="tab-pane fade {{ $defaultTab === 'home' ? 'show active' : '' }}" id="tab-home" role="tabpanel">
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="workspace-stat-card">
                        <div class="text-muted small font-weight-bold text-uppercase">Academic Year</div>
                        <div class="stat-value">{{ $academicYear ?? 'Not set' }}</div>
                        @if(!$currentSession)
                            <div class="small text-danger">No current academic session -- set one before generating.</div>
                        @endif
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="workspace-stat-card">
                        <div class="text-muted small font-weight-bold text-uppercase">Timetable Status</div>
                        <span class="badge {{ $statusBadge['class'] }} p-2">{{ $statusBadge['label'] }}</span>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="workspace-stat-card">
                        <div class="text-muted small font-weight-bold text-uppercase">Generation Readiness</div>
                        <span class="badge {{ $readinessBadge['class'] }} p-2">{{ $readinessBadge['label'] }}</span>
                        @if($readiness !== 'ready')
                            <div class="small text-muted mt-1">{{ count($report['conflicts'] ?? []) }} conflict(s), {{ count($report['class_teacher_readiness'] ?? []) }} readiness note(s)</div>
                        @endif
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="workspace-stat-card">
                        <div class="text-muted small font-weight-bold text-uppercase">Selected Scope</div>
                        <div class="stat-value" style="font-size:1.1rem;">
                            @if($schoolClassId)
                                {{ $classes->firstWhere('id', (int) $schoolClassId)?->name ?? 'Class #' . $schoolClassId }}
                                @if($sectionId) / {{ $sections->firstWhere('id', (int) $sectionId)?->name }} @endif
                            @else
                                <span class="text-muted">None selected</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-2 col-6 mb-3">
                    <div class="workspace-stat-card text-center">
                        <div class="stat-value">{{ $counts['classes'] }}</div>
                        <div class="small text-muted">Classes</div>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-3">
                    <div class="workspace-stat-card text-center">
                        <div class="stat-value">{{ $counts['sections'] }}</div>
                        <div class="small text-muted">Sections</div>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-3">
                    <div class="workspace-stat-card text-center">
                        <div class="stat-value">{{ $counts['teachers'] }}</div>
                        <div class="small text-muted">Teachers</div>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-3">
                    <div class="workspace-stat-card text-center">
                        <div class="stat-value">{{ $counts['subjects'] }}</div>
                        <div class="small text-muted">Subjects</div>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-3">
                    <div class="workspace-stat-card text-center">
                        <div class="stat-value">{{ $counts['bell_timings'] }}</div>
                        <div class="small text-muted">Active Periods</div>
                    </div>
                </div>
            </div>

            <div class="card glass-card mb-4">
                <div class="card-body">
                    <h6 class="font-weight-bold text-dark mb-3">Actions</h6>
                    <a href="{{ route('timetable.wizard.index') }}" class="btn btn-primary shadow-sm mr-2 mb-2">
                        <i class="fas fa-magic"></i> Setup
                    </a>
                    <a href="{{ route('timetable.feasibility') }}" class="btn btn-outline-secondary shadow-sm mr-2 mb-2">
                        <i class="fas fa-clipboard-check"></i> Check Readiness
                    </a>
                    @can('generate', \App\Models\TimetableSlot::class)
                    <a href="{{ route('timetable.generate.form') }}" class="btn btn-warning shadow-sm mb-2">
                        <i class="fas fa-cogs"></i> Generate Timetable
                    </a>
                    @endcan
                </div>
            </div>

            <div class="card glass-card mb-4">
                <div class="card-body">
                    <h6 class="font-weight-bold text-dark mb-3">Views &amp; Export</h6>
                    <a href="{{ route('timetable.view.teacher') }}" class="btn btn-outline-primary shadow-sm mr-2 mb-2">
                        <i class="fas fa-chalkboard-teacher"></i> Teacher Timetable
                    </a>
                    <a href="{{ route('timetable.view.room') }}" class="btn btn-outline-primary shadow-sm mr-2 mb-2">
                        <i class="fas fa-door-open"></i> Room Timetable
                    </a>
                    <a href="{{ route('timetable.index') }}" class="btn btn-outline-primary shadow-sm mr-2 mb-2">
                        <i class="fas fa-table"></i> Class Timetable
                    </a>
                    <a href="{{ route('timetable.export.master') }}" class="btn btn-outline-success shadow-sm mb-2">
                        <i class="fas fa-file-excel"></i> Master Timetable (Excel)
                    </a>
                </div>
            </div>
        </div>

        {{-- ============ SETUP ============ --}}
        <div class="tab-pane fade" id="tab-setup" role="tabpanel">
            <div class="card glass-card mb-4">
                <div class="card-body">
                    <h6 class="font-weight-bold text-dark">Guided Setup</h6>
                    <p class="text-muted">Walk through subjects, class teachers, and style for each class-section.</p>
                    <a href="{{ route('timetable.wizard.index') }}" class="btn btn-primary shadow-sm">
                        <i class="fas fa-magic"></i> Open Guided Setup Wizard
                    </a>
                </div>
            </div>

            <div class="card glass-card mb-4">
                <div class="card-body">
                    <h6 class="font-weight-bold text-dark mb-3">Underlying Configuration</h6>
                    <p class="text-muted small">These are the existing, dedicated screens the wizard above draws from -- open any of them directly for fine-grained edits.</p>
                    <div class="row">
                        @php
                            $setupLinks = [
                                ['admin.school-classes.index', 'Classes', 'fa-building'],
                                ['admin.sections.index', 'Sections', 'fa-sitemap'],
                                ['admin.subjects.index', 'Subjects', 'fa-book'],
                                ['admin.teacher-subject-assignments.index', 'Teacher-Subject Assignment', 'fa-exchange-alt'],
                                ['admin.teacher-class-assignments.index', 'Class Teacher Assignment', 'fa-user-check'],
                                ['teacher-availability.index', 'Teacher Availability', 'fa-calendar-week'],
                                ['bell-timing.index', 'Bell Timings', 'fa-bell'],
                                ['combined-class-groups.index', 'Combined Class Groups', 'fa-users'],
                            ];
                        @endphp
                        @foreach($setupLinks as [$routeName, $label, $icon])
                            @if(\Illuminate\Support\Facades\Route::has($routeName))
                            <div class="col-md-3 col-6 mb-3">
                                <a href="{{ route($routeName) }}" class="btn btn-outline-secondary btn-block text-left">
                                    <i class="fas {{ $icon }} mr-1"></i> {{ $label }}
                                </a>
                            </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>

            @if(!empty($report['class_teacher_readiness']))
            <div class="card glass-card mb-4">
                <div class="card-body">
                    <h6 class="font-weight-bold text-dark">Readiness notes</h6>
                    <ul class="list-group">
                        @foreach($report['class_teacher_readiness'] as $row)
                            <li class="list-group-item">{{ $row['sentence'] }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            @endif
        </div>

        {{-- ============ GENERATE ============ --}}
        <div class="tab-pane fade" id="tab-generate" role="tabpanel">
            <div class="card glass-card mb-4">
                <div class="card-body">
                    <h6 class="font-weight-bold text-dark">Generate Timetable</h6>
                    <p class="text-muted">
                        Generation, feasibility pre-checks, and the draft review/publish/discard flow all live on
                        the existing Generate page -- this reuses it directly rather than a second copy.
                    </p>
                    @if($schoolClassId)
                        <p>
                            Selected class: <strong>{{ $classes->firstWhere('id', (int) $schoolClassId)?->name }}</strong>
                            @if($hasDraft)
                                <span class="badge badge-warning ml-1">Has a pending draft</span>
                            @endif
                        </p>
                    @endif
                    @can('generate', \App\Models\TimetableSlot::class)
                        <a href="{{ route('timetable.generate.form') }}" class="btn btn-warning shadow-sm">
                            <i class="fas fa-cogs"></i> Open Generate Timetable
                        </a>
                    @else
                        <div class="alert alert-secondary mb-0">Generating a timetable is an admin-only action.</div>
                    @endcan
                </div>
            </div>
        </div>

        {{-- ============ REVIEW & EDIT ============ --}}
        <div class="tab-pane fade {{ $defaultTab === 'review' ? 'show active' : '' }}" id="tab-review" role="tabpanel">
            @if($schoolClassId)
                <div class="text-right mb-2">
                    <a href="{{ route('timetable.index', ['school_class_id' => $schoolClassId, 'section_id' => $sectionId, 'status' => $view]) }}" class="small text-muted">
                        Open in full-page grid <i class="fas fa-external-link-alt"></i>
                    </a>
                </div>
            @endif
            @include('admin.timetable.partials._review-edit', ['reviewEditRoute' => 'timetable.workspace'])
        </div>

        {{-- ============ CONFLICTS ============ --}}
        <div class="tab-pane fade" id="tab-conflicts" role="tabpanel">
            <div class="card glass-card mb-4">
                <div class="card-body">
                    <h6 class="font-weight-bold text-dark">Conflict Scan</h6>
                    <p class="text-muted small">
                        This is the same conflict scan FeasibilityService already runs for the Timetable Feasibility
                        report. Placement-time conflict checking (teacher/class double-booking, availability, daily
                        and weekly limits) happens live, per lesson, while adding or editing in
                        <strong>Review &amp; Edit</strong> -- powered by TimetableConflictResolver, the same engine.
                    </p>
                    @if(empty($report['conflicts']))
                        <p class="text-success mb-0"><i class="fas fa-check-circle"></i> No conflicts found.</p>
                    @else
                        <ul class="list-group">
                            @foreach($report['conflicts'] as $conflict)
                                <li class="list-group-item list-group-item-danger">{{ $conflict['sentence'] }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        {{-- ============ SUGGESTIONS ============ --}}
        <div class="tab-pane fade" id="tab-suggestions" role="tabpanel">
            <div class="card glass-card mb-4">
                <div class="card-body">
                    <h6 class="font-weight-bold text-dark">Suggestions</h6>
                    <div class="workspace-phase-note">
                        Ranked alternative-placement suggestions already appear automatically, inline, the moment a
                        scheduling conflict is detected while adding or editing a lesson in <strong>Review &amp; Edit</strong>
                        -- powered by the existing TimetableSuggestionService. A dedicated suggestions browser (viewing
                        and ranking alternatives outside of an active edit) is planned for a later phase and isn't
                        built yet, so nothing is shown here standalone.
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ AUTO-FIX ============ --}}
        <div class="tab-pane fade" id="tab-autofix" role="tabpanel">
            <div class="card glass-card mb-4">
                <div class="card-body">
                    <h6 class="font-weight-bold text-dark">Auto-Fix</h6>
                    <div class="workspace-phase-note">
                        Auto-Fix is live in <strong>Review &amp; Edit</strong>: when adding or editing a lesson hits a
                        scheduling conflict, a "Try Auto-Fix" button appears alongside the usual suggestions. It
                        searches for a chain of relocations (moving the blocking lesson, and if necessary whatever
                        blocks THAT lesson, up to a bounded depth) that frees the period you want, shows every step
                        before anything changes, and only writes once you confirm -- fully transactional, re-validated
                        against live data at apply time, and activity-logged the same way every other timetable edit
                        is. A dedicated standalone Auto-Fix browser (scanning the whole timetable for fixable
                        conflicts outside of an active edit) is planned for a later phase and isn't built yet.
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ REBALANCE ============ --}}
        <div class="tab-pane fade" id="tab-rebalance" role="tabpanel">
            <div class="card glass-card mb-4">
                <div class="card-body">
                    <h6 class="font-weight-bold text-dark">Rebalance</h6>
                    <p class="text-muted small">
                        Scans <strong>this class-section's</strong> current lesson placement for teacher gaps, uneven
                        daily workload, prefer-morning violations, excessive back-to-back periods, and (where room
                        data exists) room switching -- then proposes the smallest set of swaps/relocations that would
                        measurably improve it. Every proposed movement is validated by the same
                        TimetableConflictResolver / Swap Engine every other edit already goes through, so nothing
                        proposed can ever violate a hard constraint or move a locked lesson. Nothing changes until you
                        confirm.
                    </p>

                    @if(!$schoolClassId)
                        <div class="alert alert-secondary mb-0">Select a class (and optionally a section) in <strong>Review &amp; Edit</strong> first, then come back to this tab.</div>
                    @else
                        <p class="mb-3">
                            Scope: <strong>{{ $classes->firstWhere('id', (int) $schoolClassId)?->name }}</strong>
                            @if($sectionId) / {{ $sections->firstWhere('id', (int) $sectionId)?->name }} @endif
                            &mdash; {{ ucfirst($view) }} timetable
                        </p>

                        <button type="button" id="rebalanceAnalyzeBtn" class="btn btn-primary shadow-sm" onclick="runRebalanceAnalysis()">
                            <i class="fas fa-balance-scale"></i> Analyze / Preview Rebalance
                        </button>

                        <div id="rebalanceFeedback" class="mt-3"></div>

                        <div id="rebalanceResults" class="mt-3 d-none">
                            <div class="row mb-3">
                                <div class="col-md-4 mb-2">
                                    <div class="workspace-stat-card text-center">
                                        <div class="text-muted small font-weight-bold text-uppercase">Current Issue Score</div>
                                        <div class="stat-value" id="rebalanceBaselineScore">-</div>
                                        <div class="small text-muted">lower is better</div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <div class="workspace-stat-card text-center">
                                        <div class="text-muted small font-weight-bold text-uppercase">Proposed Issue Score</div>
                                        <div class="stat-value" id="rebalanceProposedScore">-</div>
                                        <div class="small text-muted" id="rebalanceImprovementSummary"></div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <div class="workspace-stat-card text-center">
                                        <div class="text-muted small font-weight-bold text-uppercase">Movements Proposed</div>
                                        <div class="stat-value" id="rebalanceMovementCount">0</div>
                                        <div class="small text-muted" id="rebalanceLockedNote"></div>
                                    </div>
                                </div>
                            </div>

                            <div id="rebalanceMovementListWrap" class="mb-3 d-none">
                                <h6 class="font-weight-bold text-dark">Proposed movements</h6>
                                <ul class="list-group" id="rebalanceMovementList"></ul>
                            </div>

                            <div id="rebalanceActions" class="d-none">
                                <button type="button" id="rebalanceConfirmBtn" class="btn btn-success shadow-sm" onclick="confirmRebalance()">
                                    <i class="fas fa-check"></i> Confirm Rebalance
                                </button>
                                <button type="button" class="btn btn-outline-secondary shadow-sm" onclick="cancelRebalancePreview()">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ============ PUBLISH ============ --}}
        <div class="tab-pane fade" id="tab-publish" role="tabpanel">
            <div class="card glass-card mb-4">
                <div class="card-body">
                    <h6 class="font-weight-bold text-dark">Publish</h6>
                    <p>
                        Live timetable status: <span class="badge {{ $statusBadge['class'] }}">{{ $statusBadge['label'] }}</span>
                    </p>
                    @if($schoolClassId && $activeGeneration && $activeGeneration->status === \App\Models\TimetableGeneration::STATUS_COMPLETED)
                        <p class="text-muted">
                            A draft is ready for review for
                            <strong>{{ $classes->firstWhere('id', (int) $schoolClassId)?->name }}</strong>
                            ({{ $activeGeneration->placed_count }} placed, {{ $activeGeneration->unplaced_count }} unplaced).
                        </p>
                        <a href="{{ route('timetable.generation.review', $activeGeneration) }}" class="btn btn-success shadow-sm">
                            <i class="fas fa-check"></i> Review &amp; Publish this Draft
                        </a>
                    @else
                        <p class="text-muted">
                            Publishing happens after a Generate run produces a draft -- review, publish, or discard it
                            from the existing Generate Review page. This reuses the same atomic publish/discard
                            actions, authorization, conflict validation, and activity logging as before; nothing here
                            bypasses them.
                        </p>
                        @can('generate', \App\Models\TimetableSlot::class)
                        <a href="{{ route('timetable.generate.form') }}" class="btn btn-outline-secondary shadow-sm">
                            <i class="fas fa-cogs"></i> Go to Generate
                        </a>
                        @endcan
                    @endif
                </div>
            </div>
        </div>

    </div>
</div>

@if($schoolClassId)
<script>
    let rebalanceLastMovements = [];

    function runRebalanceAnalysis() {
        const btn = document.getElementById('rebalanceAnalyzeBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyzing...';
        document.getElementById('rebalanceFeedback').innerHTML = '';
        document.getElementById('rebalanceResults').classList.add('d-none');

        const params = new URLSearchParams({
            school_class_id: '{{ $schoolClassId }}',
            status: '{{ $view }}',
        });
        @if($sectionId)
        params.set('section_id', '{{ $sectionId }}');
        @endif

        fetch(`{{ route('timetable.rebalance.preview') }}?${params.toString()}`, {
            headers: { 'Accept': 'application/json' },
        })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-balance-scale"></i> Analyze / Preview Rebalance';
                renderRebalanceResults(data);
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-balance-scale"></i> Analyze / Preview Rebalance';
                document.getElementById('rebalanceFeedback').innerHTML = '<div class="alert alert-danger">Could not reach the server. Please try again.</div>';
            });
    }

    function renderRebalanceResults(data) {
        if (!data.ok) {
            document.getElementById('rebalanceFeedback').innerHTML = `<div class="alert alert-secondary">${data.message}</div>`;
            document.getElementById('rebalanceResults').classList.add('d-none');
            rebalanceLastMovements = [];
            return;
        }

        rebalanceLastMovements = data.movements || [];

        document.getElementById('rebalanceResults').classList.remove('d-none');
        document.getElementById('rebalanceBaselineScore').innerText = data.baseline_score.total;
        document.getElementById('rebalanceProposedScore').innerText = data.proposed_score.total;

        const improvement = data.baseline_score.total - data.proposed_score.total;
        document.getElementById('rebalanceImprovementSummary').innerText = improvement > 0
            ? `-${improvement} point(s)`
            : (rebalanceLastMovements.length ? 'no net change' : 'already balanced');

        document.getElementById('rebalanceMovementCount').innerText = rebalanceLastMovements.length;
        document.getElementById('rebalanceLockedNote').innerText = data.locked_excluded_count > 0
            ? `${data.locked_excluded_count} locked lesson(s) protected, never proposed`
            : '';

        const listWrap = document.getElementById('rebalanceMovementListWrap');
        const list = document.getElementById('rebalanceMovementList');
        list.innerHTML = '';

        if (rebalanceLastMovements.length === 0) {
            listWrap.classList.add('d-none');
            document.getElementById('rebalanceActions').classList.add('d-none');
            document.getElementById('rebalanceFeedback').innerHTML = `<div class="alert alert-success">${data.message}</div>`;
            return;
        }

        listWrap.classList.remove('d-none');
        document.getElementById('rebalanceActions').classList.remove('d-none');

        rebalanceLastMovements.forEach(m => {
            const li = document.createElement('li');
            li.className = 'list-group-item';
            if (m.type === 'swap') {
                li.innerHTML = `<strong>Swap:</strong> ${m.a_subject} (${m.a_teacher}) ${m.a_from} &harr; ${m.b_subject} (${m.b_teacher}) ${m.b_from}<br><span class="small text-muted">${m.reason}</span>`;
            } else {
                li.innerHTML = `<strong>Relocate:</strong> ${m.subject} (${m.teacher}) from ${m.from} to ${m.to}<br><span class="small text-muted">${m.reason}</span>`;
            }
            list.appendChild(li);
        });

        document.getElementById('rebalanceFeedback').innerHTML = data.budget_exhausted
            ? '<div class="alert alert-warning">Search budget reached -- there may be further improvements beyond what is shown here.</div>'
            : '';
    }

    function confirmRebalance() {
        if (!rebalanceLastMovements.length) {
            return;
        }
        const btn = document.getElementById('rebalanceConfirmBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Applying...';

        const payload = rebalanceLastMovements.map(m => m.type === 'swap'
            ? { type: 'swap', slot_a_id: m.slot_a_id, slot_b_id: m.slot_b_id }
            : { type: 'relocate', slot_id: m.slot_id, to_bell_timing_id: m.to_bell_timing_id });

        fetch(@json(route('timetable.rebalance.apply')), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
            },
            body: JSON.stringify({ movements: payload }),
        })
            .then(res => res.json().then(data => ({ status: res.status, data })))
            .then(({ status, data }) => {
                if (status === 200 && data.applied) {
                    window.location.reload();
                } else {
                    document.getElementById('rebalanceFeedback').innerHTML = `<div class="alert alert-danger">Rebalance failed: ${data.message || 'Unknown error'}</div>`;
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check"></i> Confirm Rebalance';
                }
            })
            .catch(() => {
                document.getElementById('rebalanceFeedback').innerHTML = '<div class="alert alert-danger">Rebalance failed: could not reach the server.</div>';
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check"></i> Confirm Rebalance';
            });
    }

    function cancelRebalancePreview() {
        rebalanceLastMovements = [];
        document.getElementById('rebalanceResults').classList.add('d-none');
        document.getElementById('rebalanceFeedback').innerHTML = '';
    }
</script>
@endif
@endsection
