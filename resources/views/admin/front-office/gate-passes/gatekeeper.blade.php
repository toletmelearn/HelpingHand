@extends('layouts.admin')

@section('title', 'Gatekeeper Security Terminal')

@section('content')
<div class="container-fluid py-3">
    <!-- Top Terminal Header -->
    <div class="card border-0 shadow-sm bg-dark text-white mb-4">
        <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
            <div>
                <h2 class="fw-bold mb-1"><i class="bi bi-shield-check text-success me-2"></i>Security Gate Terminal</h2>
                <p class="mb-0 text-white-50 small">Verify active outbound gate passes and authorize campus releases.</p>
            </div>
            <div class="mt-3 mt-md-0 text-md-end">
                @if($myGate)
                    <div class="bg-success bg-opacity-25 border border-success rounded px-3 py-2 d-inline-block">
                        <span class="text-white-50 small d-block">ACTIVE POST LOCATION</span>
                        <h4 class="fw-bold text-success mb-0"><i class="bi bi-door-open-fill me-1"></i>{{ $myGate }}</h4>
                    </div>
                @else
                    <div class="bg-warning bg-opacity-25 border border-warning rounded px-3 py-2 d-inline-block">
                        <span class="text-white-50 small d-block">POST LOCATION STATUS</span>
                        <h4 class="fw-bold text-warning mb-0"><i class="bi bi-exclamation-triangle-fill me-1"></i>Unassigned Gate</h4>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        <!-- Main Panel: Active Gate Passes -->
        <div class="col-lg-8">
            <!-- Nav Tabs -->
            <ul class="nav nav-pills nav-fill mb-3 bg-white p-2 rounded shadow-sm" id="terminalTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold py-2.5" id="my-gate-tab" data-bs-toggle="tab" data-bs-target="#my-gate" type="button" role="tab">
                        <i class="bi bi-geo-alt-fill me-1"></i> My Gate Exits ({{ $myGatePasses->count() }})
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold py-2.5" id="lookup-tab" data-bs-toggle="tab" data-bs-target="#lookup" type="button" role="tab">
                        <i class="bi bi-search me-1"></i> Global Lookup / Override
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="terminalTabsContent">
                <!-- Tab 1: My Gate Exits -->
                <div class="tab-pane fade show active" id="my-gate" role="tabpanel">
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" style="font-size: 0.95rem;">
                                    <thead class="table-light">
                                        <tr class="text-uppercase small text-muted">
                                            <th>Pass Details</th>
                                            <th>Designated Exit</th>
                                            <th>Departure Slot</th>
                                            <th class="text-end">Verification</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($myGatePasses as $pass)
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2 mb-1">
                                                        @if($pass->pass_type === 'student' && $pass->student)
                                                            <img src="{{ $pass->student->photo_url }}" alt="{{ $pass->holder_name }}"
                                                                 class="rounded-circle" width="36" height="36" style="object-fit: cover;">
                                                        @endif
                                                        <div class="fw-bold text-dark fs-5">{{ $pass->holder_name }}</div>
                                                    </div>
                                                    @if($pass->pass_type === 'student' && $pass->student)
                                                        <small class="text-muted d-block">
                                                            Student | Class: {{ $pass->student->schoolClass ? $pass->student->schoolClass->name : 'N/A' }} | Roll: {{ $pass->student->roll_no ?: 'N/A' }}
                                                        </small>
                                                        <small class="text-muted d-block">Father: {{ $pass->student->father_name }} | Contact: {{ $pass->student->father_phone }}</small>
                                                    @else
                                                        <span class="badge bg-light text-dark border text-capitalize">{{ $pass->pass_type }} Pass</span>
                                                    @endif
                                                    <div class="mt-1 small text-secondary"><strong>Purpose:</strong> {{ $pass->purpose }}</div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary py-1.5 px-2.5"><i class="bi bi-door-open me-1"></i>{{ $pass->exit_gate }}</span>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold text-primary font-monospace">{{ \Carbon\Carbon::parse($pass->departure_time)->format('h:i A') }}</div>
                                                    <small class="text-muted">{{ $pass->request_date->format('M d, Y') }}</small>
                                                </td>
                                                <td class="text-end">
                                                    <form action="{{ route('admin.front-office.gate-passes.verify', $pass->id) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="btn btn-success btn-lg px-4 py-2.5 fw-bold shadow-sm">
                                                            <i class="bi bi-check-circle me-1"></i> Verify Exit & Let Go
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center py-5 text-muted">
                                                    <i class="bi bi-check-circle fs-1 text-success mb-2 d-block"></i>
                                                    No active departures scheduled for <strong>{{ $myGate ?: 'unassigned gate' }}</strong> today.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Global Lookup / Override -->
                <div class="tab-pane fade" id="lookup" role="tabpanel">
                    <!-- Search Form -->
                    <div class="card border-0 shadow-sm bg-white mb-3">
                        <div class="card-body">
                            <form action="{{ route('admin.front-office.gatekeeper') }}" method="GET" class="d-flex gap-2">
                                <input type="hidden" name="tab" value="lookup">
                                <input type="text" name="search" class="form-control form-control-lg border-2" placeholder="Search by Student Name, Vehicle No, or Pass ID..." value="{{ request('search') }}" autocomplete="off">
                                <button type="submit" class="btn btn-primary btn-lg px-4"><i class="bi bi-search"></i> Search</button>
                            </form>
                        </div>
                    </div>

                    <!-- Search Results -->
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr class="text-uppercase small text-muted">
                                            <th>Pass Details</th>
                                            <th>Designated Gate</th>
                                            <th>Requested Time</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($globalPasses as $pass)
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2 mb-1">
                                                        @if($pass->pass_type === 'student' && $pass->student)
                                                            <img src="{{ $pass->student->photo_url }}" alt="{{ $pass->holder_name }}"
                                                                 class="rounded-circle" width="36" height="36" style="object-fit: cover;">
                                                        @endif
                                                        <div class="fw-bold text-dark fs-5">{{ $pass->holder_name }}</div>
                                                    </div>
                                                    @if($pass->pass_type === 'student' && $pass->student)
                                                        <small class="text-muted d-block">
                                                            Student | Class: {{ $pass->student->schoolClass ? $pass->student->schoolClass->name : 'N/A' }}
                                                        </small>
                                                        <small class="text-muted d-block">Father: {{ $pass->student->father_name }} | Contact: {{ $pass->student->father_phone }}</small>
                                                    @endif
                                                    <div class="mt-1 small text-secondary"><strong>Purpose:</strong> {{ $pass->purpose }}</div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-warning text-dark py-1.5 px-2.5"><i class="bi bi-door-open me-1"></i>{{ $pass->exit_gate ?: 'Main Gate' }}</span>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold text-primary font-monospace">{{ \Carbon\Carbon::parse($pass->departure_time)->format('h:i A') }}</div>
                                                </td>
                                                <td class="text-end">
                                                    @if($myGate && $pass->exit_gate !== $myGate)
                                                        <!-- Guard is at a different gate: Allow Override -->
                                                        <button class="btn btn-warning btn-lg px-4 py-2.5 fw-bold shadow-sm" onclick="showOverrideModal({{ $pass->id }}, '{{ $pass->holder_name }}', '{{ $pass->exit_gate }}')">
                                                            <i class="bi bi-exclamation-triangle me-1"></i> Override & Release Here
                                                        </button>
                                                    @else
                                                        <!-- Same gate, or guard unassigned: Standard Release -->
                                                        <form action="{{ route('admin.front-office.gate-passes.verify', $pass->id) }}" method="POST">
                                                            @csrf
                                                            <button type="submit" class="btn btn-success btn-lg px-4 py-2.5 fw-bold shadow-sm">
                                                                <i class="bi bi-check-circle me-1"></i> Verify Exit & Let Go
                                                            </button>
                                                        </form>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center py-5 text-muted">
                                                    @if(request('search'))
                                                        <i class="bi bi-emoji-frown fs-1 text-secondary mb-2 d-block"></i>
                                                        No active or approved gate passes found matching your search.
                                                    @else
                                                        <i class="bi bi-search fs-1 text-secondary mb-2 d-block"></i>
                                                        Search above to lookup gate passes routed to other gates.
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar: Gate Duty Status & Recent Exit Logs -->
        <div class="col-lg-4">
            <!-- Active Assignment Info -->
            <div class="card border-0 shadow-sm bg-white mb-4">
                <div class="card-header bg-transparent border-0 pt-4">
                    <h5 class="fw-bold mb-0">Active Shift Duty Details</h5>
                </div>
                <div class="card-body">
                    @if($currentAssignment)
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar bg-success text-white rounded-circle p-2 me-3">
                                <i class="bi bi-shield-fill fs-4"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark fs-5">{{ Auth::user()->name }}</div>
                                <small class="text-muted">Assigned Guard Duty</small>
                            </div>
                        </div>
                        <hr>
                        <div class="row g-2 small text-secondary">
                            <div class="col-6">Duty Date:</div>
                            <div class="col-6 text-dark fw-bold">{{ $currentAssignment->duty_date->format('Y-m-d') }}</div>
                            <div class="col-6">Active Shift:</div>
                            <div class="col-6 text-dark fw-bold">{{ $currentAssignment->shift }}</div>
                            <div class="col-6">Assigned By:</div>
                            <div class="col-6 text-dark fw-bold">{{ $currentAssignment->assigner->name ?? 'System' }}</div>
                        </div>
                    @else
                        <div class="text-center py-3">
                            <i class="bi bi-exclamation-triangle-fill text-warning fs-1 mb-2 d-block"></i>
                            <p class="text-muted small">You are not dynamically assigned to a gate post today.</p>
                            <a href="{{ route('admin.front-office.duty-assignments.index') }}" class="btn btn-sm btn-outline-primary">Manage Duty Roster</a>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Recent Exit Logs at My Gate -->
            <div class="card border-0 shadow-sm bg-white">
                <div class="card-header bg-transparent border-0 pt-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Recent Releases Today</h5>
                    <span class="badge bg-success rounded-pill">{{ $verifiedPasses->count() }} verified</span>
                </div>
                <div class="card-body p-0 mt-2">
                    <div class="list-group list-group-flush" style="max-height: 380px; overflow-y: auto;">
                        @forelse($verifiedPasses as $log)
                            <div class="list-group-item p-3 border-0 border-bottom">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold text-dark small">{{ $log->holder_name }}</div>
                                        @if($log->override_reason)
                                            <span class="badge bg-warning text-dark px-2 py-0.5 mt-1" style="font-size: 0.72rem;">
                                                <i class="bi bi-exclamation-circle me-1"></i>Gate Override Exit
                                            </span>
                                            <small class="text-muted d-block mt-1 font-italic">Reason: {{ $log->override_reason }}</small>
                                        @else
                                            <span class="badge bg-success text-white px-2 py-0.5 mt-1" style="font-size: 0.72rem;">
                                                Standard Release
                                            </span>
                                        @endif
                                    </div>
                                    <span class="text-muted font-monospace small" style="font-size: 0.8rem;">
                                        {{ \Carbon\Carbon::parse($log->arrival_time)->format('h:i A') }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-4 text-muted small">
                                <i class="bi bi-clock-history fs-3 mb-2 d-block text-secondary"></i>
                                No student exits verified at this gate location today yet.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Gate Override Modal -->
<div class="modal fade" id="overrideModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="overrideForm" action="" method="POST" class="modal-content">
            @csrf
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Authorize Gate Pass Override</h5>
                <button type="button" class="btn-close" data-bs-dismiss="alert" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="bg-light p-3 rounded mb-3 small">
                    <p class="mb-1 text-secondary">Student Name:</p>
                    <h5 class="fw-bold text-dark mb-2" id="overrideStudentName">N/A</h5>
                    <p class="mb-0 text-secondary">Designated Route: <strong class="text-danger" id="overrideDesignatedGate">N/A</strong></p>
                    <p class="mb-0 text-secondary">Current Location: <strong class="text-success">{{ $myGate ?: 'Unassigned' }}</strong></p>
                </div>

                <!-- Hidden Input for Exit Gate -->
                <input type="hidden" name="exit_gate" value="{{ $myGate }}">

                <div class="mb-3">
                    <label class="form-label fw-bold">Select Reason for Gate Override <span class="text-danger">*</span></label>
                    <select name="override_reason" id="overrideReasonSelect" class="form-select border-2" onchange="toggleCustomReason()" required>
                        <option value="">-- Select Valid Reason --</option>
                        <option value="Parent vehicle parked at Gate 2 / nearest exit">Parent vehicle parked at this gate</option>
                        <option value="Parent uncooperative / refused to walk to designated gate">Parent uncooperative / refused to go to designated gate</option>
                        <option value="Medical emergency / physical handicap of parent or student">Medical emergency or physical handicap</option>
                        <option value="Authorized by Supervisor / Admin phone call">Authorized by supervisor / admin phone call</option>
                        <option value="Other / Custom Reason">Other (Write notes below)</option>
                    </select>
                </div>

                <div class="mb-3 d-none" id="customReasonWrapper">
                    <label class="form-label fw-bold">Explain Custom Reason <span class="text-danger">*</span></label>
                    <textarea name="custom_reason" id="customReasonText" class="form-control border-2" rows="3" placeholder="Explain why the gate check-out location was overridden..."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning fw-bold px-4"><i class="bi bi-check2-circle me-1"></i> Authorize & Let Go</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        // If search exists, show lookup tab by default
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('tab') === 'lookup' || urlParams.get('search')) {
            const lookupTab = new bootstrap.Tab(document.getElementById('lookup-tab'));
            lookupTab.show();
        }
    });

    function showOverrideModal(passId, studentName, designatedGate) {
        document.getElementById('overrideStudentName').innerText = studentName;
        document.getElementById('overrideDesignatedGate').innerText = designatedGate;
        
        // Dynamically set action URL
        const form = document.getElementById('overrideForm');
        form.action = `/admin/front-office/gate-passes/${passId}/verify`;
        
        // Show modal
        const myModal = new bootstrap.Modal(document.getElementById('overrideModal'));
        myModal.show();
    }

    function toggleCustomReason() {
        const select = document.getElementById('overrideReasonSelect');
        const customWrapper = document.getElementById('customReasonWrapper');
        const customText = document.getElementById('customReasonText');
        
        if (select.value === 'Other / Custom Reason') {
            customWrapper.classList.remove('d-none');
            customText.required = true;
        } else {
            customWrapper.classList.add('d-none');
            customText.required = false;
        }
    }

    // Handle form submit to append custom reason if selected
    document.getElementById('overrideForm').addEventListener('submit', function (e) {
        const select = document.getElementById('overrideReasonSelect');
        if (select.value === 'Other / Custom Reason') {
            const customText = document.getElementById('customReasonText').value;
            select.options[select.selectedIndex].value = "Other: " + customText;
        }
    });
</script>
@endsection
