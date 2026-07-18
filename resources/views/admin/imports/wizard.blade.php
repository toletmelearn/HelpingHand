@extends('layouts.admin')

@section('title', $title)

@php
    // Maps each import module to where its records are managed one-at-a-time,
    // so staff can jump straight to individual add/edit instead of a spreadsheet upload.
    $individualManagementRoutes = [
        'students' => ['route' => 'admin.students.index', 'label' => 'Students'],
        'teachers' => ['route' => 'admin.teachers.index', 'label' => 'Teachers'],
        'parents' => ['route' => 'admin.parents.index', 'label' => 'Parents'],
        'classes' => ['route' => 'admin.classes.index', 'label' => 'Classes'],
        'sections' => ['route' => 'admin.sections.index', 'label' => 'Sections'],
        'subjects' => ['route' => 'admin.subjects.index', 'label' => 'Subjects'],
        'fee-structures' => ['route' => 'admin.fee-structures.index', 'label' => 'Fee Structures'],
    ];
    $individualManagement = $individualManagementRoutes[$module] ?? null;
    $wizardBackRoute = ($individualManagement && \Illuminate\Support\Facades\Route::has($individualManagement['route']))
        ? route($individualManagement['route'])
        : route('imports.dashboard');
@endphp

@section('content')
<style>
    /* Glassmorphism Styles */
    .glass-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 12px;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.08);
    }
    
    .glass-header {
        background: linear-gradient(135deg, #3498db, #2c3e50);
        color: white;
        border-top-left-radius: 12px;
        border-top-right-radius: 12px;
    }

    /* Stepper Styles */
    .stepper {
        display: flex;
        justify-content: space-between;
        margin-bottom: 2rem;
        position: relative;
    }

    .stepper::before {
        content: "";
        position: absolute;
        top: 20px;
        left: 0;
        right: 0;
        height: 4px;
        background-color: #e9ecef;
        z-index: 1;
    }

    .step-item {
        position: relative;
        z-index: 2;
        text-align: center;
        flex: 1;
    }

    .step-circle {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background-color: #fff;
        border: 3px solid #dee2e6;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 8px;
        font-weight: bold;
        transition: all 0.3s ease;
    }

    .step-item.active .step-circle {
        border-color: #3498db;
        background-color: #3498db;
        color: white;
        box-shadow: 0 0 10px rgba(52, 152, 219, 0.5);
    }

    .step-item.completed .step-circle {
        border-color: #2ecc71;
        background-color: #2ecc71;
        color: white;
    }

    .step-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: #6c757d;
    }

    .step-item.active .step-label {
        color: #2c3e50;
    }

    /* File upload area */
    .upload-area {
        border: 2px dashed #cbd5e1;
        border-radius: 8px;
        padding: 3rem 1.5rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s ease;
        background: rgba(248, 250, 252, 0.5);
    }

    .upload-area:hover, .upload-area.dragover {
        border-color: #3498db;
        background: rgba(241, 245, 249, 0.8);
    }
</style>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-xl-10 col-lg-11 col-12">
            
            <!-- Universal Import Wizard Card -->
            <div class="card glass-card border-0 mb-4">
                <div class="card-header glass-header p-4 border-0">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <h4 class="mb-0 fw-bold"><i class="bi bi-cloud-upload-fill me-2"></i> {{ $title }}</h4>
                            <small class="opacity-75">Guided multi-step data ingestion engine &mdash; use this to add many records at once from a spreadsheet.</small>
                        </div>
                        <div class="d-flex gap-2">
                            @if($individualManagement)
                                <a href="{{ route($individualManagement['route']) }}" class="btn btn-sm btn-outline-dark">
                                    <i class="bi bi-pencil-square me-1"></i> Manage {{ $individualManagement['label'] }} Individually Instead
                                </a>
                            @endif
                            <a href="{{ $wizardBackRoute }}" class="btn btn-sm btn-light text-dark"><i class="bi bi-arrow-left me-1"></i> Back</a>
                        </div>
                    </div>
                </div>
                
                <div class="card-body p-4">
                    @if($module === 'fee_opening_balance')
                        <div class="alert alert-warning d-flex align-items-start gap-2 mb-4">
                            <i class="bi bi-signpost-split-fill fs-4"></i>
                            <div>
                                <strong>Uploading a real historical fee register</strong> (one row per student, a
                                single total-paid figure, no fee-head breakdown -- e.g. an accounts office Excel
                                sheet)? This page is the wrong one and its template won't match. Use
                                <a href="{{ route('imports.wizard', ['module' => 'fee_opening_balance_summary']) }}" class="fw-bold">Upload Opening Balance (Fee Register Summary)</a>
                                instead. This page is only for when you already know exactly which fee head and
                                period (e.g. "Tuition Fee, April") each payment covered.
                            </div>
                        </div>
                    @elseif($module === 'fee_opening_balance_summary')
                        <div class="alert alert-info d-flex align-items-start gap-2 mb-4">
                            <i class="bi bi-signpost-split-fill fs-4"></i>
                            <div>
                                Already know exactly which fee head and period each payment covered (e.g. "Tuition
                                Fee, April")? Use
                                <a href="{{ route('imports.wizard', ['module' => 'fee_opening_balance']) }}" class="fw-bold">Upload Opening Balance (Per Fee-Head Detail)</a>
                                instead for a more precise, line-by-line record. This page is for a real historical
                                register with just a total-paid figure per student, no breakdown.
                            </div>
                        </div>
                    @endif

                    <!-- Stepper Progress Header -->
                    <div class="stepper">
                        <div class="step-item active" id="step-header-1">
                            <div class="step-circle">1</div>
                            <div class="step-label">Upload File</div>
                        </div>
                        <div class="step-item" id="step-header-2">
                            <div class="step-circle">2</div>
                            <div class="step-label">Map Columns</div>
                        </div>
                        <div class="step-item" id="step-header-3">
                            <div class="step-circle">3</div>
                            <div class="step-label">Verify Data</div>
                        </div>
                        <div class="step-item" id="step-header-4">
                            <div class="step-circle">4</div>
                            <div class="step-label">Processing</div>
                        </div>
                        <div class="step-item" id="step-header-5">
                            <div class="step-circle">5</div>
                            <div class="step-label">Summary</div>
                        </div>
                    </div>

                    <!-- Alert Container -->
                    <div id="alert-container"></div>

                    <!-- ================= STEP 1: UPLOAD FILE ================= -->
                    <div class="step-content" id="step-content-1">
                        <div class="text-center mb-4">
                            <h5>Upload CSV or Excel Template</h5>
                            <p class="text-muted">Ingest rosters with standard columns. Required columns for {{ $module }}: 
                                <span class="badge bg-secondary">{{ implode(', ', $requiredFields) }}</span>
                            </p>
                        </div>

                        <!-- Predefined Migration Sources -->
                        <div class="card border-0 bg-light p-3 mb-4 rounded-3">
                            <div class="row align-items-center">
                                <div class="col-md-4 col-12">
                                    <label class="form-label fw-bold text-dark mb-md-0" for="migration-source">
                                        <i class="bi bi-box-arrow-in-right me-1 text-primary"></i> Migration Data Source
                                    </label>
                                </div>
                                <div class="col-md-8 col-12">
                                    <select class="form-select border-0 shadow-sm" id="migration-source">
                                        <option value="generic">Generic Excel / CSV Template</option>
                                        <option value="entab">Entab CampusCare Export</option>
                                        <option value="fedena">Fedena Migration CSV</option>
                                        <option value="myclasscampus">MyClassCampus Format</option>
                                        <option value="erpnext">ERPNext Education Bulk Import</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="upload-area mb-4" id="drop-zone">
                            <i class="bi bi-file-earmark-spreadsheet-fill text-muted mb-3" style="font-size: 3.5rem;"></i>
                            <h6 class="fw-bold">Drag and drop file here, or click to browse</h6>
                            <small class="text-muted d-block mb-3">Accepts CSV, XLSX, XLS (Max 15MB)</small>
                            <input type="file" id="file-input" class="d-none" accept=".csv,.xlsx,.xls">
                            <button class="btn btn-outline-primary btn-sm px-4">Browse Local File</button>
                        </div>

                        <div class="text-center">
                            <small class="text-muted">Please download the template if you do not have one configured:
                                <a href="{{ route('imports.download-template', ['module' => $module]) }}" class="fw-semibold text-decoration-none"><i class="bi bi-download me-1"></i>Download CSV Template</a>
                            </small>
                            @if($templateFieldsConfigurable ?? false)
                                <br>
                                <small class="text-muted">
                                    <a href="{{ route('imports.wizard.template-fields', ['module' => $module]) }}" class="fw-semibold text-decoration-none"><i class="bi bi-pencil-square me-1"></i>Manage Template Fields</a>
                                </small>
                            @endif
                        </div>
                    </div>

                    <!-- ================= STEP 2: MAP COLUMNS ================= -->
                    <div class="step-content d-none" id="step-content-2">
                        <div class="mb-4">
                            <h5>Map Columns</h5>
                            <p class="text-muted mb-3">Confirm mapping from your uploaded file columns to standard ERP Database fields.</p>
                        </div>

                        <div class="table-responsive mb-4">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 40%;">Standard Database Field</th>
                                        <th style="width: 60%;">Mapped Column from File</th>
                                    </tr>
                                </thead>
                                <tbody id="mapping-rows">
                                    <!-- Dynamic Rows Populated via JavaScript -->
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button class="btn btn-light" id="btn-back-2"><i class="bi bi-arrow-left me-1"></i> Back</button>
                            <button class="btn btn-primary" id="btn-next-2">Dry Run & Validate <i class="bi bi-arrow-right ms-1"></i></button>
                        </div>
                    </div>

                    <!-- ================= STEP 3: PREVIEW & VERIFY ================= -->
                    <div class="step-content d-none" id="step-content-3">
                        <div class="mb-4">
                            <h5>Data Pre-Verification Results</h5>
                            <p class="text-muted">A dry run was executed on database transactions. Review validation metrics, duplicates, or format warnings.</p>
                        </div>

                        <!-- Summary cards -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <div class="card bg-light border-0 text-center p-3">
                                    <h3 class="fw-bold mb-0 text-dark" id="txt-verify-total">0</h3>
                                    <small class="text-muted">Total Rows</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success-subtle border-0 text-center p-3">
                                    <h3 class="fw-bold mb-0 text-success" id="txt-verify-valid">0</h3>
                                    <small class="text-success-emphasis">Valid Rows</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-danger-subtle border-0 text-center p-3">
                                    <h3 class="fw-bold mb-0 text-danger" id="txt-verify-errors">0</h3>
                                    <small class="text-danger-emphasis">Validation Errors</small>
                                </div>
                            </div>
                        </div>

                        <!-- Validation Errors Detail Panel -->
                        <div class="card border-0 bg-danger-subtle p-3 mb-4 d-none" id="verify-errors-container">
                            <h6 class="fw-bold mb-3 text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> Validation Errors Detail</h6>
                            <small class="text-danger-emphasis d-block mb-3"><i class="bi bi-info-circle me-1"></i> Click on any row to toggle its full columns details view.</small>
                            <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                                <table class="table table-sm table-hover bg-white mb-0">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th style="width: 12%;">Row</th>
                                            <th style="width: 38%;">Record Identity</th>
                                            <th style="width: 50%;">Error Details</th>
                                        </tr>
                                    </thead>
                                    <tbody id="verify-errors-list">
                                        <!-- Error rows populated via JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Settings & Resolutions options -->
                        <div class="card border-0 bg-light p-3 mb-4">
                            <h6 class="fw-bold mb-3"><i class="bi bi-sliders me-1"></i> Conflict Resolution settings</h6>
                            <div class="row align-items-center">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label class="form-label fw-semibold">Duplicate Check strategy</label>
                                    <select class="form-select" id="resolution-strategy">
                                        <option value="skip">Skip duplicates (Ignore existing records)</option>
                                        <option value="overwrite">Overwrite properties (Update existing records)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button class="btn btn-light" id="btn-back-3"><i class="bi bi-arrow-left me-1"></i> Adjust Mapping</button>
                            <button class="btn btn-success" id="btn-execute-3" disabled><i class="bi bi-check-circle me-1"></i> Apply & Execute Import</button>
                        </div>
                    </div>

                    <!-- ================= STEP 4: PROCESSING ================= -->
                    <div class="step-content d-none" id="step-content-4">
                        <div class="text-center py-4">
                            <h5 class="mb-3">Applying Import Records...</h5>
                            <p class="text-muted">Please wait while the rows are committed in atomic transactions. Do not close this window.</p>
                            
                            <div class="progress mb-3 mx-auto" style="height: 25px; max-width: 600px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" id="import-progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                            </div>

                            <div class="text-muted mb-4" id="import-progress-status">Processing: 0 / 0 rows</div>
                            
                            <button class="btn btn-outline-danger btn-sm" id="btn-cancel-import" disabled>Cancel Import Queue</button>
                        </div>
                    </div>

                    <!-- ================= STEP 5: REPORT SUMMARY ================= -->
                    <div class="step-content d-none" id="step-content-5">
                        <div class="text-center py-4">
                            <i class="bi bi-check-circle-fill text-success mb-3" style="font-size: 4rem;"></i>
                            <h4 class="fw-bold mb-3">Import Session Finished</h4>
                            <p class="text-muted" id="txt-summary-desc">Roster records processed successfully.</p>

                            <div class="row justify-content-center g-3 mb-4">
                                <div class="col-md-3 col-6">
                                    <div class="card border border-success-subtle p-3">
                                        <h4 class="text-success fw-bold mb-0" id="txt-summary-success">0</h4>
                                        <small class="text-muted">Imported</small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="card border border-danger-subtle p-3">
                                        <h4 class="text-danger fw-bold mb-0" id="txt-summary-failed">0</h4>
                                        <small class="text-muted">Failed</small>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-center gap-3">
                                <button class="btn btn-outline-primary" id="btn-download-errors" style="display: none;"><i class="bi bi-cloud-arrow-down-fill me-1"></i> Download Failed Rows CSV</button>
                                <button class="btn btn-danger" id="btn-rollback-5"><i class="bi bi-arrow-counterclockwise me-1"></i> Undo / Rollback Import</button>
                                <a href="{{ route('students.index') }}" class="btn btn-primary">Done & Exit</a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const module = '{{ $module }}';
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    // UI step states
    let activeStep = 1;
    let sessionUuid = null;
    let fileHeaders = [];
    let requiredFields = @json($requiredFields);
    let mandatoryFields = @json($mandatoryFields ?? []);
    let suggestedMappings = {};
    let pollingInterval = null;

    // Helper to extract a friendly row identifier from the raw data
    function getRowIdentifier(rowData, mappings, fileHeaders) {
        if (!rowData || !fileHeaders || !mappings) return '';
        
        let parsedRow = [];
        try {
            parsedRow = typeof rowData === 'string' ? JSON.parse(rowData) : rowData;
        } catch (e) {
            parsedRow = rowData;
        }
        
        if (!Array.isArray(parsedRow)) return '';
        
        const getValueOfField = (dbField) => {
            const headerName = mappings[dbField];
            if (!headerName) return null;
            const index = fileHeaders.indexOf(headerName);
            if (index === -1) return null;
            return parsedRow[index];
        };
        
        const parts = [];
        
        const name = getValueOfField('name') || getValueOfField('title') || getValueOfField('subject_name') || getValueOfField('route_name');
        if (name) {
            parts.push(`<strong>${name}</strong>`);
        }
        
        const admissionNo = getValueOfField('admission_no') || getValueOfField('employee_id');
        if (admissionNo) {
            parts.push(`ID/Adm: ${admissionNo}`);
        }
        
        const fatherName = getValueOfField('father_name');
        if (fatherName) {
            parts.push(`Father: ${fatherName}`);
        }
        
        const mobile = getValueOfField('mobile');
        if (mobile) {
            parts.push(`Mobile: ${mobile}`);
        }

        const className = getValueOfField('class');
        const sectionName = getValueOfField('section');
        if (className) {
            let clsSec = className;
            if (sectionName) {
                clsSec += ` - ${sectionName}`;
            }
            parts.push(`Class: ${clsSec}`);
        }
        
        return parts.join(' | ');
    }

    // Elements
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('file-input');
    const alertContainer = document.getElementById('alert-container');
    const mappingRows = document.getElementById('mapping-rows');
    const resolutionStrategy = document.getElementById('resolution-strategy');

    // 1. File Upload Drop Zone Interactions
    dropZone.addEventListener('click', () => fileInput.click());
    
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('dragover');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        if (e.dataTransfer.files.length) {
            handleFileUpload(e.dataTransfer.files[0]);
        }
    });

    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length) {
            handleFileUpload(e.target.files[0]);
        }
    });

    function showAlert(type, message) {
        alertContainer.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show border-0 shadow-sm" role="alert">
                <div>${message}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
    }

    function switchStep(step) {
        activeStep = step;
        document.querySelectorAll('.step-content').forEach(el => el.classList.add('d-none'));
        document.getElementById(`step-content-${step}`).classList.remove('d-none');

        document.querySelectorAll('.step-item').forEach((el, index) => {
            el.classList.remove('active', 'completed');
            if (index + 1 < step) {
                el.classList.add('completed');
            } else if (index + 1 === step) {
                el.classList.add('active');
            }
        });
    }

    // 2. Upload file handler
    function handleFileUpload(file) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('_token', csrfToken);

        showAlert('info', 'Uploading file, parsing metadata headers...');

        fetch(`/imports/wizard/${module}/upload`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                sessionUuid = data.session_uuid;
                fileHeaders = data.headers;
                suggestedMappings = data.suggested_mappings;
                
                populateMappingRows();
                showAlert('success', 'File parsed successfully! Map column relations below.');
                switchStep(2);
            } else {
                showAlert('danger', data.message || 'File upload failed.');
            }
        })
        .catch(err => {
            showAlert('danger', 'Error processing file.');
            console.error(err);
        });
    }

    const vendorMappingProfiles = {
        entab: {
            name: ['Student Name', 'Name', 'Staff Name', 'Teacher Name'],
            father_name: ['Father Name', 'Father\'s Name', 'Father'],
            mother_name: ['Mother Name', 'Mother\'s Name', 'Mother'],
            date_of_birth: ['DOB', 'Date of Birth', 'Date Of Birth', 'Birth Date'],
            gender: ['Gender', 'Sex'],
            mobile: ['Father Mobile', 'Mobile', 'Mobile Phone', 'Primary Contact'],
            phone: ['Father Mobile', 'Phone', 'Landline', 'Primary Contact'],
            address: ['Correspondence Address', 'Permanent Address', 'Address'],
            email: ['Parent Email', 'Email', 'Email Address'],
            employee_id: ['Staff Code', 'Employee ID', 'Emp ID', 'Teacher ID'],
            designation: ['Role', 'Designation'],
            qualification: ['Degree', 'Qualification'],
            monthly_fare: ['Fare', 'Monthly Fare', 'Route Fee'],
            class: ['Standard', 'Class', 'Course'],
            section: ['Division', 'Section', 'Batch']
        },
        fedena: {
            name: ['First Name', 'Name', 'Employee Name'],
            father_name: ['Father/Guardian Name', 'Father Name', 'Guardian Name'],
            mother_name: ['Mother Name', 'Mother\'s Name'],
            date_of_birth: ['Date of Birth', 'DOB', 'Birth Date'],
            gender: ['Gender'],
            mobile: ['Mobile Phone', 'Mobile', 'Phone', 'Contact Number'],
            phone: ['Mobile Phone', 'Phone'],
            address: ['Address Line 1', 'Address Line 2', 'Address'],
            email: ['Email Address', 'Email', 'Parent Email'],
            employee_id: ['Employee Number', 'Employee ID', 'Emp ID'],
            designation: ['Designation'],
            qualification: ['Qualification'],
            monthly_fare: ['Monthly Fare', 'Fare'],
            class: ['Course Name', 'Class'],
            section: ['Batch Name', 'Section']
        },
        myclasscampus: {
            name: ['Full Name', 'Name', 'Staff Name'],
            father_name: ['Father Name', 'Father'],
            mother_name: ['Mother Name', 'Mother'],
            date_of_birth: ['Birth Date', 'DOB', 'Date of Birth'],
            gender: ['Gender'],
            mobile: ['Parent Phone', 'Mobile', 'Phone', 'Contact'],
            phone: ['Parent Phone', 'Phone'],
            address: ['Address', 'Permanent Address'],
            email: ['Email', 'Email ID'],
            employee_id: ['Emp ID', 'Employee ID', 'Staff ID'],
            designation: ['Designation'],
            qualification: ['Qualification'],
            monthly_fare: ['Route Fare', 'Fare'],
            class: ['Class Name', 'Class'],
            section: ['Section Name', 'Section']
        },
        erpnext: {
            name: ['student_name', 'name', 'employee_name'],
            father_name: ['guardian_name', 'father_name'],
            mother_name: ['mother_name'],
            date_of_birth: ['date_of_birth', 'dob'],
            gender: ['gender'],
            mobile: ['mobile_number', 'mobile', 'phone'],
            phone: ['mobile_number', 'phone'],
            address: ['address'],
            email: ['email_id', 'email', 'parent_email'],
            employee_id: ['employee_id', 'employee', 'emp_id'],
            designation: ['designation'],
            qualification: ['qualification'],
            monthly_fare: ['fare', 'monthly_fare'],
            class: ['class'],
            section: ['section']
        }
    };

    // 3. Populate mappings UI dynamically
    function populateMappingRows() {
        mappingRows.innerHTML = '';
        const standardFields = requiredFields;
        const sourceVal = document.getElementById('migration-source').value;

        standardFields.forEach(field => {
            const tr = document.createElement('tr');
            
            // Database Field name
            const tdField = document.createElement('td');
            const isMandatory = mandatoryFields.includes(field);
            tdField.innerHTML = `<span class="fw-semibold text-capitalize">${field.replace(/_/g, ' ')}</span> ${isMandatory ? '<span class="text-danger">*</span>' : ''}`;
            
            // Dropdown option select
            const tdSelect = document.createElement('td');
            const select = document.createElement('select');
            select.className = 'form-select mapping-select';
            select.dataset.field = field;

            select.innerHTML = '<option value="">-- Do Not Import --</option>';
            
            // Determine default selected candidate header
            let selectedHeader = suggestedMappings[field] || '';
            const profile = vendorMappingProfiles[sourceVal];
            if (profile && profile[field]) {
                const match = fileHeaders.find(h => profile[field].includes(h));
                if (match) {
                    selectedHeader = match;
                }
            }

            fileHeaders.forEach(header => {
                const selected = selectedHeader === header ? 'selected' : '';
                select.innerHTML += `<option value="${header}" ${selected}>${header}</option>`;
            });

            tdSelect.appendChild(select);
            tr.appendChild(tdField);
            tr.appendChild(tdSelect);
            mappingRows.appendChild(tr);
        });
    }

    // Adjust steps buttons
    document.getElementById('btn-back-2').addEventListener('click', () => switchStep(1));
    
    function runDryRun(showNotification = true) {
        const mappings = {};
        document.querySelectorAll('.mapping-select').forEach(select => {
            if (select.value) {
                mappings[select.dataset.field] = select.value;
            }
        });

        // Reset and clear errors container/list
        const errorsContainer = document.getElementById('verify-errors-container');
        if (errorsContainer) {
            errorsContainer.classList.add('d-none');
        }
        const errorsList = document.getElementById('verify-errors-list');
        if (errorsList) {
            errorsList.innerHTML = '';
        }

        if (showNotification) {
            showAlert('info', 'Executing validation dry-run on transactions...');
        }
        
        fetch(`/imports/wizard/${module}/dry-run`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                session_uuid: sessionUuid,
                mappings: mappings
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('txt-verify-total').textContent = data.total;
                document.getElementById('txt-verify-valid').textContent = data.success_count !== undefined ? data.success_count : data.success;
                document.getElementById('txt-verify-errors').textContent = data.errors;

                const btnExecute = document.getElementById('btn-execute-3');
                if (data.errors > 0) {
                    if (showNotification) {
                        showAlert('warning', 'Validation failures detected during dry run. Adjust column mapping or correct data.');
                    }
                    btnExecute.disabled = true;

                    if (errorsContainer && errorsList && data.error_details && data.error_details.length > 0) {
                        errorsContainer.classList.remove('d-none');
                        data.error_details.forEach(err => {
                            const recordId = getRowIdentifier(err.raw_row_data, mappings, fileHeaders);
                            
                            const tr = document.createElement('tr');
                            tr.className = 'align-middle';
                            tr.style.cursor = 'pointer';
                            tr.title = 'Click to toggle full row details';
                            tr.innerHTML = `
                                <td class="fw-bold text-danger">Row ${err.row_number}</td>
                                <td class="text-dark text-nowrap">${recordId || '<span class="text-muted small">No identity fields</span>'}</td>
                                <td class="text-danger">${err.error_message}</td>
                            `;
                            errorsList.appendChild(tr);

                            // Append details row if raw row data is present
                            let parsedRow = [];
                            try {
                                parsedRow = typeof err.raw_row_data === 'string' ? JSON.parse(err.raw_row_data) : err.raw_row_data;
                            } catch (e) {
                                parsedRow = err.raw_row_data;
                            }

                            if (parsedRow && Array.isArray(parsedRow) && parsedRow.length > 0) {
                                const detailTr = document.createElement('tr');
                                detailTr.className = 'd-none bg-light';
                                
                                let gridHtml = `
                                    <div class="p-3 border-start border-3 border-danger bg-light-subtle rounded">
                                        <h6 class="fw-bold mb-2 text-dark"><i class="bi bi-pencil-square me-1"></i> Correct Row ${err.row_number} Data</h6>
                                        <div class="text-muted small mb-3">Fix inputs below and click "Save & Re-verify Row" to update.</div>
                                        <form class="row correction-form g-2" data-row="${err.row_number}">
                                `;
                                
                                fileHeaders.forEach((header, hIdx) => {
                                    const val = parsedRow[hIdx] !== null && parsedRow[hIdx] !== undefined ? parsedRow[hIdx] : '';
                                    
                                    // Highlight if it failed validation
                                    // Check if the error message contains the column name
                                    let isInvalid = false;
                                    const cleanHeader = header.replace(/'/g, '');
                                    if (err.error_message.includes(`column: '${cleanHeader}'`) || 
                                        err.error_message.includes(`column: &apos;${cleanHeader}&apos;`) || 
                                        err.error_message.includes(`column: &#039;${cleanHeader}&#039;`)) {
                                        isInvalid = true;
                                    }

                                    const inputClass = isInvalid ? 'form-control form-control-sm border-danger bg-danger-subtle fw-medium' : 'form-control form-control-sm';
                                    const labelClass = isInvalid ? 'text-danger fw-bold d-block small mb-1' : 'text-muted d-block small mb-1';

                                    gridHtml += `
                                        <div class="col-md-4 col-sm-6">
                                            <div class="p-1">
                                                <label class="${labelClass}">${header} ${isInvalid ? '<i class="bi bi-exclamation-circle-fill text-danger"></i>' : ''}</label>
                                                <input type="text" class="form-control form-control-sm correction-input ${isInvalid ? 'is-invalid border-danger bg-danger-subtle' : ''}" data-header="${header.replace(/"/g, '&quot;')}" value="${String(val).replace(/"/g, '&quot;')}">
                                            </div>
                                        </div>
                                    `;
                                });

                                gridHtml += `
                                            <div class="col-12 text-end mt-3">
                                                <button type="button" class="btn btn-sm btn-primary btn-save-correction" data-row="${err.row_number}">
                                                    <i class="bi bi-check-circle me-1"></i> Save & Re-verify Row
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                `;
                                
                                detailTr.innerHTML = `
                                    <td colspan="3">${gridHtml}</td>
                                `;
                                
                                tr.addEventListener('click', (e) => {
                                    if (e.target.closest('.correction-form')) {
                                        return;
                                    }
                                    detailTr.classList.toggle('d-none');
                                });
                                
                                errorsList.appendChild(detailTr);

                                // Add Save button click event handler
                                const saveBtn = detailTr.querySelector('.btn-save-correction');
                                saveBtn.addEventListener('click', () => {
                                    const rowNum = saveBtn.dataset.row;
                                    const corrections = {};
                                    detailTr.querySelectorAll('.correction-input').forEach(input => {
                                        corrections[input.dataset.header] = input.value;
                                    });

                                    saveBtn.disabled = true;
                                    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...';

                                    fetch(`/imports/wizard/${module}/correct-row`, {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'Accept': 'application/json',
                                            'X-CSRF-TOKEN': csrfToken
                                        },
                                        body: JSON.stringify({
                                            session_uuid: sessionUuid,
                                            row_number: rowNum,
                                            corrections: corrections
                                        })
                                    })
                                    .then(res => res.json())
                                    .then(saveData => {
                                        if (saveData.success) {
                                            showAlert('success', saveData.message);
                                            // Re-run validation without moving steps
                                            runDryRun(false);
                                        } else {
                                            showAlert('danger', saveData.message || 'Error saving correction.');
                                            saveBtn.disabled = false;
                                            saveBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Save & Re-verify Row';
                                        }
                                    })
                                    .catch(err => {
                                        showAlert('danger', 'Error updating corrections.');
                                        console.error(err);
                                        saveBtn.disabled = false;
                                        saveBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Save & Re-verify Row';
                                    });
                                });
                            }
                        });
                    }
                } else {
                    showAlert('success', 'Dry run passed with 0 errors! Apply changes to execute database writes.');
                    btnExecute.disabled = false;
                    if (errorsContainer) {
                        errorsContainer.classList.add('d-none');
                    }
                }
                
                switchStep(3);
            } else {
                showAlert('danger', data.message || 'Validation failed.');
            }
        })
        .catch(err => {
            showAlert('danger', 'Error validating dry-run.');
            console.error(err);
        });
    }

    document.getElementById('btn-next-2').addEventListener('click', () => {
        runDryRun(true);
    });

    document.getElementById('btn-back-3').addEventListener('click', () => switchStep(2));

    // 4. Run Apply execution
    document.getElementById('btn-execute-3').addEventListener('click', () => {
        switchStep(4);
        
        // Start Ajax Batch execution
        fetch(`/imports/wizard/${module}/execute`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                session_uuid: sessionUuid,
                resolution_strategy: resolutionStrategy.value
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Done
                document.getElementById('txt-summary-success').textContent = data.success_count !== undefined ? data.success_count : data.success;
                document.getElementById('txt-summary-failed').textContent = data.errors;
                
                if (data.errors > 0) {
                    const btnDownload = document.getElementById('btn-download-errors');
                    btnDownload.style.display = 'inline-block';
                    btnDownload.onclick = () => {
                        window.location.href = `/imports/wizard/${module}/errors/${sessionUuid}/download`;
                    };
                }

                switchStep(5);
            } else {
                showAlert('danger', data.message || 'Execution error.');
                switchStep(3);
            }
        })
        .catch(err => {
            showAlert('danger', 'Error processing database commits.');
            switchStep(3);
            console.error(err);
        });

        // Start polling progress bar
        pollingInterval = setInterval(pollProgress, 1000);
    });

    function pollProgress() {
        fetch(`/imports/wizard/${module}/progress/${sessionUuid}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const total = data.total_rows;
                const processed = data.processed_rows;
                const percent = total > 0 ? Math.round((processed / total) * 100) : 0;

                const progressBar = document.getElementById('import-progress-bar');
                progressBar.style.width = `${percent}%`;
                progressBar.textContent = `${percent}%`;
                progressBar.setAttribute('aria-valuenow', percent);

                document.getElementById('import-progress-status').textContent = `Processing: ${processed} / ${total} rows`;

                if (data.status === 'completed' || data.status === 'failed') {
                    clearInterval(pollingInterval);
                }
            }
        });
    }

    // 5. Undo / Rollback import handler
    document.getElementById('btn-rollback-5').addEventListener('click', () => {
        if (confirm('Are you sure you want to rollback? This will delete all student records newly created in this import session.')) {
            showAlert('info', 'Rolling back database transactions...');
            fetch(`/imports/wizard/${module}/rollback`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    session_uuid: sessionUuid
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Successfully rolled back the session records.');
                    window.location.href = '/admin/students';
                } else {
                    showAlert('danger', data.message || 'Rollback failed.');
                }
            });
        }
    });
    // 6. Source Selector Change Listener
    document.getElementById('migration-source').addEventListener('change', () => {
        if (sessionUuid) {
            populateMappingRows();
        }
    });
});
</script>
@endsection
