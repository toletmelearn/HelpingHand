@extends('layouts.admin')

@section('title', 'Defaulter Registry')

@section('content')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');
    
    .registry-container {
        font-family: 'Outfit', sans-serif;
        background-color: #f8fafc;
        border-radius: 16px;
    }
    
    .page-title {
        font-weight: 700;
        color: #1e293b;
    }
    
    .card-premium {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    
    .table-premium th {
        background-color: #f1f5f9;
        color: #475569;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        padding: 14px 16px;
    }
    
    .table-premium td {
        padding: 14px 16px;
        border-bottom: 1px solid #e2e8f0;
        color: #334155;
    }
    
    .badge-stage {
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 9999px;
        font-size: 0.8rem;
    }
</style>

<div class="container-fluid py-4 registry-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="page-title mb-1"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Defaulter Registry</h3>
            <p class="text-muted mb-0">Monitor overdue outstanding balances, dispatch communications, enforce restrictions, and audit call logs.</p>
        </div>
        <div class="d-flex gap-2">
            @if(Auth::user()->hasPermission('view-defaulters') || Auth::user()->hasPermission('manage-defaulters'))
                <a href="{{ route('admin.fees.defaulters.export', array_merge(request()->all(), ['format' => 'excel'])) }}" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
                <a href="{{ route('admin.fees.defaulters.export', array_merge(request()->all(), ['format' => 'pdf'])) }}" class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i> Export PDF</a>
            @endif
            <a href="{{ route('admin.fees.defaulters.dashboard') }}" class="btn btn-outline-danger"><i class="bi bi-speedometer2"></i> Dashboard Analytics</a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card card-premium p-4 mb-4">
        <form action="{{ route('admin.fees.defaulters.index') }}" method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="stage" class="form-label small fw-bold text-muted">Workflow Stage</label>
                <select name="stage" id="stage" class="form-select">
                    <option value="">All Stages</option>
                    @foreach($stages as $stg)
                        <option value="{{ $stg }}" {{ request('stage') === $stg ? 'selected' : '' }}>{{ $stg }}</option>
                    @endforeach
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="class_id" class="form-label small fw-bold text-muted">Class</label>
                <select name="class_id" id="class_id" class="form-select">
                    <option value="">All Classes</option>
                    @foreach($classes as $cls)
                        <option value="{{ $cls->id }}" {{ request('class_id') == $cls->id ? 'selected' : '' }}>{{ $cls->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label for="section_id" class="form-label small fw-bold text-muted">Section</label>
                <select name="section_id" id="section_id" class="form-select">
                    <option value="">All Sections</option>
                    @foreach($sections as $sec)
                        <option value="{{ $sec->id }}" {{ request('section_id') == $sec->id ? 'selected' : '' }}>{{ $sec->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label for="min_amount" class="form-label small fw-bold text-muted">Min Outstanding (₹)</label>
                <input type="number" name="min_amount" id="min_amount" value="{{ request('min_amount') }}" step="0.01" class="form-control" placeholder="0.00">
            </div>

            <div class="col-md-3">
                <label for="month" class="form-label small fw-bold text-muted">Month-wise (unpaid due in)</label>
                <select name="month" id="month" class="form-select">
                    <option value="">All Months</option>
                    @foreach(['1'=>'January','2'=>'February','3'=>'March','4'=>'April','5'=>'May','6'=>'June','7'=>'July','8'=>'August','9'=>'September','10'=>'October','11'=>'November','12'=>'December'] as $val => $label)
                        <option value="{{ $val }}" {{ (string) request('month') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label for="quarter" class="form-label small fw-bold text-muted">Quarter-wise (unpaid due in)</label>
                <select name="quarter" id="quarter" class="form-select">
                    <option value="">All Quarters</option>
                    <option value="Q1" {{ request('quarter') === 'Q1' ? 'selected' : '' }}>Q1 (Apr - Jun)</option>
                    <option value="Q2" {{ request('quarter') === 'Q2' ? 'selected' : '' }}>Q2 (Jul - Sep)</option>
                    <option value="Q3" {{ request('quarter') === 'Q3' ? 'selected' : '' }}>Q3 (Oct - Dec)</option>
                    <option value="Q4" {{ request('quarter') === 'Q4' ? 'selected' : '' }}>Q4 (Jan - Mar)</option>
                </select>
            </div>

            <div class="col-md-3">
                <label for="ageing" class="form-label small fw-bold text-muted">Ageing (days overdue)</label>
                <select name="ageing" id="ageing" class="form-select">
                    <option value="">All</option>
                    <option value="1_30" {{ request('ageing') === '1_30' ? 'selected' : '' }}>1 - 30 days</option>
                    <option value="31_60" {{ request('ageing') === '31_60' ? 'selected' : '' }}>31 - 60 days</option>
                    <option value="61_90" {{ request('ageing') === '61_90' ? 'selected' : '' }}>61 - 90 days</option>
                    <option value="90_plus" {{ request('ageing') === '90_plus' ? 'selected' : '' }}>90+ days</option>
                </select>
            </div>

            <div class="col-md-3 d-flex align-items-end gap-2">
                <a href="{{ route('admin.fees.defaulters.index') }}" class="btn btn-outline-secondary w-50">Reset</a>
                <button type="submit" class="btn btn-danger w-50">Filter</button>
            </div>
        </form>
    </div>

    <!-- Alerts -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Bulk Actions Panel -->
    <div class="card card-premium p-3 mb-4 bg-light border-danger">
        <form action="{{ route('admin.fees.defaulters.bulk-action') }}" method="POST" id="bulkActionForm">
            @csrf
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-lightning-fill text-danger"></i>
                    <strong class="text-danger">Bulk Action Panel:</strong>
                    <span class="text-muted small" id="selectedCount">0 students selected</span>
                </div>
                <div class="d-flex gap-2">
                    <select name="channel" class="form-select form-select-sm" style="width: 150px;" required>
                        <option value="">Select Channel</option>
                        <option value="sms">Send SMS</option>
                        <option value="whatsapp">Send WhatsApp</option>
                        <option value="email">Send Email</option>
                        <option value="notification">Send Notice</option>
                    </select>
                    
                    <div class="form-check form-check-inline d-flex align-items-center mb-0 ms-2">
                        <input class="form-check-input" type="checkbox" name="promote" id="bulkPromote" value="1">
                        <label class="form-check-label small fw-bold ms-1" for="bulkPromote">Advance Stage?</label>
                    </div>

                    <button type="submit" class="btn btn-sm btn-danger px-3" id="bulkSubmitBtn" disabled>Run Bulk Dispatch</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Main Defaulter Registry Table -->
    <div class="card card-premium overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table table-premium mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" id="selectAllCheckbox">
                        </th>
                        <th>Admission No</th>
                        <th>Student Name</th>
                        <th>Class & Section</th>
                        <th>Workflow Stage</th>
                        <th class="text-end">Outstanding Balance</th>
                        <th>Last Action Date</th>
                        <th class="text-center">Workflow Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($defaulters as $def)
                        @php
                            $badgeMap = [
                                'Reminder' => 'bg-info text-white',
                                'Phone Call' => 'bg-primary text-white',
                                'Warning' => 'bg-warning text-dark',
                                'Principal Notice' => 'bg-dark text-white',
                                'Exam Restriction' => 'bg-danger text-white',
                                'Result Hold' => 'bg-danger text-white',
                                'TC Hold' => 'bg-danger text-white',
                            ];
                            $badge = $badgeMap[$def->stage] ?? 'bg-secondary';
                        @endphp
                        <tr>
                            <td>
                                <input type="checkbox" class="student-select" name="student_ids[]" value="{{ $def->student_id }}" form="bulkActionForm">
                            </td>
                            <td><strong>{{ $def->student->admission_no }}</strong></td>
                            <td>{{ $def->student->name }}</td>
                            <td>{{ $def->student->schoolClass->name ?? 'N/A' }} - {{ $def->student->section->name ?? 'N/A' }}</td>
                            <td>
                                <span class="badge badge-stage {{ $badge }}">{{ $def->stage }}</span>
                            </td>
                            <td class="text-end fw-bold text-danger">₹{{ number_format($def->outstanding_amount, 2) }}</td>
                            <td>{{ $def->last_action_date ? $def->last_action_date->format('Y-m-d H:i') : 'No action yet' }}</td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <!-- Send Notice & Promote -->
                                    <form action="{{ route('admin.fees.defaulters.action', $def->student_id) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="stage" value="{{ $def->stage }}">
                                        <input type="hidden" name="promote" value="1">
                                        <select name="channel" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                                            <option value="">Dispatch Stage Action...</option>
                                            <option value="sms">SMS Notice</option>
                                            <option value="whatsapp">WhatsApp Notice</option>
                                            <option value="email">Email Notice</option>
                                            <option value="notification">System Notice</option>
                                            <option value="phone call">Log Phone Call</option>
                                        </select>
                                    </form>

                                    <!-- Override / Remarks -->
                                    <button class="btn btn-sm btn-outline-secondary" onclick="openOverrideModal({{ $def->student_id }}, '{{ $def->stage }}')"><i class="bi bi-pencil-square"></i> Override</button>

                                    <!-- Exam Restriction Exception (Admin / Principal / Accountant only) -->
                                    @if($canOverrideExam && in_array($def->stage, ['Exam Restriction', 'Result Hold', 'TC Hold']))
                                        @if(in_array($def->student_id, $overriddenStudentIds))
                                            <form action="{{ route('admin.fees.defaulters.exam-override.revoke', $def->student_id) }}" method="POST" onsubmit="return confirm('Revoke this student\'s exam exception? Their admit card will be re-restricted if still overdue.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Exam exception active -- click to revoke"><i class="bi bi-shield-x"></i> Revoke Exception</button>
                                            </form>
                                        @else
                                            <button class="btn btn-sm btn-outline-success" onclick="openExamOverrideModal({{ $def->student_id }})" title="Allow this student to sit the exam despite the outstanding balance"><i class="bi bi-shield-check"></i> Allow to Sit Exam</button>
                                        @endif
                                    @endif

                                    <!-- History -->
                                    <button class="btn btn-sm btn-outline-info" onclick="viewHistory({{ $def->student_id }})"><i class="bi bi-clock-history"></i> History</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-shield-check fs-2 text-success"></i>
                                <p class="mt-2 mb-0">No outstanding fee defaulters found.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-between align-items-center">
        <p class="text-muted small mb-0">
            Showing {{ $defaulters->firstItem() ?? 0 }} to {{ $defaulters->lastItem() ?? 0 }} of {{ $defaulters->total() }} records
        </p>
        <div>
            {{ $defaulters->links() }}
        </div>
    </div>
</div>

<!-- Manual Override Modal -->
<div class="modal fade" id="overrideModal" tabindex="-1" aria-labelledby="overrideModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="overrideForm" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="overrideModalLabel">Manual Stage Override</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="override_stage" class="form-label fw-bold">Set Stage Override</label>
                        <select name="stage" id="override_stage" class="form-select" required>
                            @foreach($stages as $stg)
                                <option value="{{ $stg }}">{{ $stg }}</option>
                            @endforeach
                            <option value="Cleared">Cleared</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="override_remarks" class="form-label fw-bold">Override Reason / Remarks</label>
                        <textarea name="remarks" id="override_remarks" rows="3" class="form-control" placeholder="E.g., Parent requested extension till 15th..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger">Apply Override</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Exam Restriction Exception Modal -->
<div class="modal fade" id="examOverrideModal" tabindex="-1" aria-labelledby="examOverrideModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="examOverrideForm" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="examOverrideModalLabel">Allow Student to Sit Exam</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">This grants a manual exception so the student's admit card is restored despite the outstanding balance. The exception stays in effect until you revoke it.</p>
                    <div class="mb-3">
                        <label for="exam_override_reason" class="form-label fw-bold">Reason (optional)</label>
                        <textarea name="reason" id="exam_override_reason" rows="3" class="form-control" placeholder="E.g., Principal approved -- parent has committed to clearing dues by 25th..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success">Grant Exception</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="historyModalLabel">Action History Trail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6 class="fw-bold mb-3">Logs for <span id="historyStudentName" class="text-danger">Student</span></h6>
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Stage</th>
                                <th>Action Channel</th>
                                <th>Status</th>
                                <th>Details / Remarks</th>
                                <th>By</th>
                            </tr>
                        </thead>
                        <tbody id="historyLogsBody">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAll = document.getElementById('selectAllCheckbox');
        const checkboxes = document.querySelectorAll('.student-select');
        const selectedCount = document.getElementById('selectedCount');
        const bulkSubmitBtn = document.getElementById('bulkSubmitBtn');

        function updatePanel() {
            const checkedCount = document.querySelectorAll('.student-select:checked').length;
            selectedCount.innerText = checkedCount + ' students selected';
            bulkSubmitBtn.disabled = checkedCount === 0;
        }

        if (selectAll) {
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(cb => cb.checked = selectAll.checked);
                updatePanel();
            });
        }

        checkboxes.forEach(cb => {
            cb.addEventListener('change', updatePanel);
        });
    });

    function openOverrideModal(studentId, currentStage) {
        const modal = new bootstrap.Modal(document.getElementById('overrideModal'));
        const form = document.getElementById('overrideForm');
        form.action = `/admin/fees/defaulters/${studentId}/override`;
        document.getElementById('override_stage').value = currentStage;
        document.getElementById('override_remarks').value = '';
        modal.show();
    }

    function openExamOverrideModal(studentId) {
        const modal = new bootstrap.Modal(document.getElementById('examOverrideModal'));
        const form = document.getElementById('examOverrideForm');
        form.action = `/admin/fees/defaulters/${studentId}/exam-override`;
        document.getElementById('exam_override_reason').value = '';
        modal.show();
    }

    function viewHistory(studentId) {
        fetch(`/admin/fees/defaulters/${studentId}/history`)
            .then(res => res.json())
            .then(data => {
                document.getElementById('historyStudentName').innerText = data.student;
                const body = document.getElementById('historyLogsBody');
                body.innerHTML = '';
                
                if (data.logs.length === 0) {
                    body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No actions logged yet.</td></tr>';
                } else {
                    data.logs.forEach(log => {
                        const date = new Date(log.created_at).toLocaleString();
                        const statusClass = log.status === 'Sent' || log.status === 'Logged' ? 'text-success' : 'text-danger';
                        const user = log.performed_by && log.performed_by.name ? log.performed_by.name : (log.performed_by_name || 'System');
                        
                        body.innerHTML += `
                            <tr>
                                <td>${date}</td>
                                <td><span class="badge bg-secondary">${log.stage}</span></td>
                                <td><strong>${log.action_type}</strong></td>
                                <td class="${statusClass} fw-bold">${log.status}</td>
                                <td class="small">${log.details || ''}</td>
                                <td>${user}</td>
                            </tr>
                        `;
                    });
                }
                
                const modal = new bootstrap.Modal(document.getElementById('historyModal'));
                modal.show();
            });
    }
</script>
@endsection
