@extends('layouts.admin')

@section('title', 'Data Management Dashboard')

@section('content')
<style>
    /* Glassmorphic card theme */
    .glass-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 12px;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.08);
    }
    .glass-header {
        background: linear-gradient(135deg, #1e3a8a, #0f172a);
        color: white;
        border-top-left-radius: 12px;
        border-top-right-radius: 12px;
    }
    .import-btn {
        transition: all 0.2s ease;
    }
    .import-btn:hover {
        transform: translateY(-2px);
    }
    .status-dot {
        font-size: 0.75rem;
        margin-right: 0.5rem;
    }
</style>

<div class="container-fluid py-4">
    <!-- Page Header & Breadcrumbs -->
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Data Management</li>
                </ol>
            </nav>
            <h3 class="fw-bold text-dark"><i class="bi bi-database-fill-gear text-primary me-2"></i> Data Management Dashboard</h3>
            <p class="text-muted">Orchestrate database ingestion, predefined migration mappings, and view execution tracking histories.</p>
        </div>
    </div>

    <!-- Aggregate Analytics Cards -->
    <div class="row g-3 mb-4">
        <!-- Card 1: Session Activity Summary -->
        <div class="col-xl-3 col-md-6 col-12">
            <div class="card glass-card border-0 p-3 h-100 d-flex align-items-center justify-content-between flex-row">
                <div>
                    <h5 class="text-muted small text-uppercase mb-1">Total Imports</h5>
                    <h3 class="fw-bold mb-0 text-dark">{{ $stats['total_imports'] }}</h3>
                    <small class="text-info font-semibold"><i class="bi bi-cpu-fill me-1"></i>{{ $stats['running_jobs'] }} running jobs</small>
                </div>
                <div class="bg-primary-subtle text-primary p-3 rounded-3" style="font-size: 1.5rem;"><i class="bi bi-cloud-arrow-up-fill"></i></div>
            </div>
        </div>
        <!-- Card 2: Success & Rollbacks -->
        <div class="col-xl-3 col-md-6 col-12">
            <div class="card glass-card border-0 p-3 h-100 d-flex align-items-center justify-content-between flex-row">
                <div>
                    <h5 class="text-muted small text-uppercase mb-1">Success Rate</h5>
                    <h3 class="fw-bold mb-0 text-success">{{ $stats['successful_imports'] }}</h3>
                    <small class="text-warning font-semibold"><i class="bi bi-arrow-counterclockwise me-1"></i>{{ $stats['rollbacks_available'] }} rollbacks ready</small>
                </div>
                <div class="bg-success-subtle text-success p-3 rounded-3" style="font-size: 1.5rem;"><i class="bi bi-check-circle-fill"></i></div>
            </div>
        </div>
        <!-- Card 3: Ingestion Speed & Storage -->
        <div class="col-xl-3 col-md-6 col-12">
            <div class="card glass-card border-0 p-3 h-100 d-flex align-items-center justify-content-between flex-row">
                <div>
                    <h5 class="text-muted small text-uppercase mb-1">Avg Process Speed</h5>
                    <h3 class="fw-bold mb-0 text-dark">{{ $stats['average_speed'] }} <small style="font-size: 0.8rem;">r/s</small></h3>
                    <small class="text-muted"><i class="bi bi-database me-1"></i>{{ $stats['storage_usage'] }} total size</small>
                </div>
                <div class="bg-info-subtle text-info p-3 rounded-3" style="font-size: 1.5rem;"><i class="bi bi-speedometer"></i></div>
            </div>
        </div>
        <!-- Card 4: Last Ingestion Event -->
        <div class="col-xl-3 col-md-6 col-12">
            <div class="card glass-card border-0 p-3 h-100 d-flex align-items-center justify-content-between flex-row">
                <div>
                    <h5 class="text-muted small text-uppercase mb-1">Last Imported Module</h5>
                    <h4 class="fw-bold mb-0 text-dark text-capitalize" style="font-size: 1.25rem;">{{ $stats['last_imported_module'] }}</h4>
                    <small class="text-muted"><i class="bi bi-person-fill me-1"></i>By {{ $stats['last_imported_user'] }}</small>
                </div>
                <div class="bg-warning-subtle text-warning p-3 rounded-3" style="font-size: 1.5rem;"><i class="bi bi-clock-history"></i></div>
            </div>
        </div>
    </div>

    @php
        $importEngine = app(\App\Services\Imports\ImportEngine::class);
        $statusLabels = [
            'green' => ['label' => 'Configured', 'badge' => 'bg-success'],
            'yellow' => ['label' => 'Empty', 'badge' => 'bg-warning text-dark'],
            'red' => ['label' => 'Soon', 'badge' => 'bg-danger']
        ];
    @endphp

    <div class="row g-4 mb-4">
        <!-- Left: Launch Guided Wizards -->
        <div class="col-xl-8 col-12">
            <div class="card glass-card border-0 h-100">
                <div class="card-header bg-light border-0 p-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-rocket-takeoff-fill me-1 text-primary"></i> Launch Guided Import Wizard</h6>
                </div>
                <div class="card-body p-3">
                    <div class="row g-3">
                        <!-- Students -->
                        <div class="col-md-6 col-12">
                            <div class="card p-3 border-0 bg-light h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="bg-info text-white p-2 rounded me-2" style="font-size: 1.1rem;"><i class="bi bi-mortarboard-fill"></i></span>
                                        <strong class="text-dark">Student Ingestion</strong>
                                    </div>
                                    <small class="text-muted d-block mb-3">Upload student profiles, link parents, and allocate sections.</small>
                                </div>
                                <a href="{{ route('imports.wizard', ['module' => 'students']) }}" class="btn btn-sm btn-info text-white w-100 import-btn">Start Wizard</a>
                            </div>
                        </div>

                        <!-- Teachers -->
                        <div class="col-md-6 col-12">
                            <div class="card p-3 border-0 bg-light h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="bg-success text-white p-2 rounded me-2" style="font-size: 1.1rem;"><i class="bi bi-person-badge-fill"></i></span>
                                        <strong class="text-dark">Teacher Ingestion</strong>
                                    </div>
                                    <small class="text-muted d-block mb-3">Import staff designations, qualification levels, and salaries.</small>
                                </div>
                                <a href="{{ route('imports.wizard', ['module' => 'teachers']) }}" class="btn btn-sm btn-success w-100 import-btn">Start Wizard</a>
                            </div>
                        </div>

                        <!-- Parents -->
                        <div class="col-md-6 col-12">
                            <div class="card p-3 border-0 bg-light h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="bg-warning text-dark p-2 rounded me-2" style="font-size: 1.1rem;"><i class="bi bi-people-fill"></i></span>
                                        <strong class="text-dark">Parent Ingestion</strong>
                                    </div>
                                    <small class="text-muted d-block mb-3">Map parent accounts, guardian parameters, and matching keys.</small>
                                </div>
                                <a href="{{ route('imports.wizard', ['module' => 'parents']) }}" class="btn btn-sm btn-warning text-dark w-100 import-btn">Start Wizard</a>
                            </div>
                        </div>

                        <!-- Class & Sections -->
                        <div class="col-md-6 col-12">
                            <div class="card p-3 border-0 bg-light h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="bg-primary text-white p-2 rounded me-2" style="font-size: 1.1rem;"><i class="bi bi-building"></i></span>
                                        <strong class="text-dark">Classes & Sections</strong>
                                    </div>
                                    <small class="text-muted d-block mb-3">Batch import classes, divisions, and section bounds.</small>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('imports.wizard', ['module' => 'classes']) }}" class="btn btn-sm btn-outline-primary flex-fill">Classes</a>
                                    <a href="{{ route('imports.wizard', ['module' => 'sections']) }}" class="btn btn-sm btn-outline-primary flex-fill">Sections</a>
                                </div>
                            </div>
                        </div>

                        <!-- Subjects -->
                        <div class="col-md-6 col-12">
                            <div class="card p-3 border-0 bg-light h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="bg-secondary text-white p-2 rounded me-2" style="font-size: 1.1rem;"><i class="bi bi-book-half"></i></span>
                                        <strong class="text-dark">Subject Curriculums</strong>
                                    </div>
                                    <small class="text-muted d-block mb-3">Import core subjects, codes, and scholastic classifications.</small>
                                </div>
                                <a href="{{ route('imports.wizard', ['module' => 'subjects']) }}" class="btn btn-sm btn-secondary w-100 import-btn">Start Wizard</a>
                            </div>
                        </div>

                        <!-- Fee Opening Balance -->
                        <div class="col-md-6 col-12">
                            <div class="card p-3 border-0 bg-light h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="bg-danger text-white p-2 rounded me-2" style="font-size: 1.1rem;"><i class="bi bi-clock-history"></i></span>
                                        <strong class="text-dark">Fee Opening Balance</strong>
                                    </div>
                                    <small class="text-muted d-block mb-3">Record fees a student already paid before onboarding mid-session (monthly, quarterly, or full year).</small>
                                </div>
                                <a href="{{ route('imports.wizard', ['module' => 'fee_opening_balance']) }}" class="btn btn-sm btn-danger w-100 import-btn">Start Wizard</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Module Readiness Indicators -->
        <div class="col-xl-4 col-12">
            <div class="card glass-card border-0 h-100">
                <div class="card-header bg-light border-0 p-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-check2-circle me-1 text-primary"></i> Module Configuration Readiness</h6>
                </div>
                <div class="card-body p-0" style="max-height: 480px; overflow-y: auto;">
                    <div class="list-group list-group-flush">
                        @foreach([
                            'academic-sessions' => 'Academic Sessions',
                            'classes' => 'Classes',
                            'sections' => 'Sections',
                            'subjects' => 'Subjects',
                            'students' => 'Students',
                            'teachers' => 'Teachers',
                            'parents' => 'Parents',
                            'fee-structures' => 'Fee Structures',
                            'fee-heads' => 'Fee Heads',
                            'discounts' => 'Discounts',
                            'scholarships' => 'Scholarships',
                            'staff' => 'Staff Directory',
                            'users' => 'Users'
                        ] as $modKey => $modName)
                            @php
                                $status = $importEngine->getModuleStatus($modKey);
                                $badgeInfo = $statusLabels[$status];
                            @endphp
                            <div class="list-group-item d-flex align-items-center justify-content-between p-3 border-0 border-bottom">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-circle-fill status-dot text-{{ $status === 'green' ? 'success' : ($status === 'yellow' ? 'warning' : 'danger') }}"></i>
                                    <span class="fw-semibold">{{ $modName }}</span>
                                </div>
                                <span class="badge {{ $badgeInfo['badge'] }} px-2 py-1 rounded-pill small">{{ $badgeInfo['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Session Activity Log -->
    <div class="card glass-card border-0">
        <div class="card-header bg-light border-0 p-3 d-flex align-items-center justify-content-between">
            <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history me-1 text-primary"></i> Recent Ingestion Activity</h6>
            <a href="{{ route('imports.history') }}" class="btn btn-xs btn-outline-primary py-0">View All History</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Module</th>
                            <th>Status</th>
                            <th>Successfully Processed</th>
                            <th>Errors</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stats['recent_activity'] as $activity)
                            <tr>
                                <td>{{ $activity->created_at->format('Y-m-d H:i') }}</td>
                                <td><span class="badge bg-secondary text-capitalize">{{ $activity->module }}</span></td>
                                <td>
                                    @if($activity->status === 'completed')
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Completed</span>
                                    @elseif($activity->status === 'failed')
                                        <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Failed</span>
                                    @elseif($activity->status === 'rolled_back')
                                        <span class="badge bg-warning text-dark"><i class="bi bi-arrow-counterclockwise me-1"></i>Undone (Rolled Back)</span>
                                    @else
                                        <span class="badge bg-info text-white"><i class="bi bi-arrow-repeat spin me-1"></i>{{ ucfirst($activity->status) }}</span>
                                    @endif
                                </td>
                                <td>{{ $activity->success_rows }} / {{ $activity->total_rows }} rows</td>
                                <td>
                                    @if($activity->error_rows > 0)
                                        <span class="text-danger fw-semibold"><i class="bi bi-x-octagon-fill me-1"></i>{{ $activity->error_rows }} errors</span>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($activity->status === 'completed')
                                        <button class="btn btn-sm btn-outline-danger" onclick="triggerRollback('{{ $activity->uuid }}', '{{ $activity->module }}')"><i class="bi bi-arrow-counterclockwise"></i> Rollback</button>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No recent import sessions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function triggerRollback(uuid, module) {
    if (confirm('Are you sure you want to rollback this session? This will undo all changes and delete all newly created profiles.')) {
        fetch(`/admin/imports/wizard/${module}/rollback`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ session_uuid: uuid })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Session rolled back successfully.');
                window.location.reload();
            } else {
                alert('Rollback failed: ' + data.message);
            }
        });
    }
}
</script>
@endsection
