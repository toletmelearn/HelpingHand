<!-- Fixed Left Vertical Sidebar -->
<nav id="sidebar" class="admin-sidebar">
    <style>
        :root {
            --sidebar-bg: #1f2937;
            --sidebar-header-bg: #111827;
            --sidebar-section-bg: #374151;
            --sidebar-text: #e5e7eb;
            --sidebar-muted: #9ca3af;
            --sidebar-active-bg: #2563eb;
            --sidebar-hover-bg: #1e40af;
            --sidebar-border: #4b5563;
        }
        
        .admin-sidebar {
            background-color: var(--sidebar-bg);
            color: var(--sidebar-text);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 3px 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .admin-sidebar.collapsed {
            width: 70px;
        }
        
        .sidebar-header {
            background-color: var(--sidebar-header-bg);
            border-bottom: 1px solid var(--sidebar-border);
        }
        
        .sidebar-content {
            background-color: var(--sidebar-bg);
        }
        
        .nav-header {
            background-color: var(--sidebar-section-bg);
            color: var(--sidebar-muted);
            border-bottom: 1px solid var(--sidebar-border);
            transition: all 0.2s ease;
            cursor: pointer;
            position: relative;
        }
        
        .nav-header:hover {
            background-color: var(--sidebar-hover-bg);
            color: var(--sidebar-text);
        }
        
        .nav-header::after {
            content: '▼';
            position: absolute;
            right: 15px;
            font-size: 0.8rem;
            transition: transform 0.3s ease;
        }

        .nav-subtitle {
            font-size: 0.68rem;
            font-weight: 400;
            letter-spacing: normal;
            color: var(--sidebar-muted);
            opacity: 0.85;
            margin-top: 2px;
            padding-right: 20px;
            white-space: normal;
            line-height: 1.3;
        }
        
        .sidebar-section.open .nav-header::after {
            transform: rotate(180deg);
        }
        
        .nav-link {
            color: var(--sidebar-text) !important;
            padding: 12px 20px;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }
        
        .nav-link:hover {
            background-color: var(--sidebar-hover-bg) !important;
            color: white !important;
            border-left: 3px solid var(--sidebar-active-bg);
        }
        
        .nav-link.active {
            background-color: var(--sidebar-active-bg) !important;
            color: white !important;
            border-left: 3px solid white;
        }
        
        .nav-link i {
            color: var(--sidebar-muted);
            transition: color 0.2s ease;
        }
        
        .nav-link:hover i,
        .nav-link.active i {
            color: white !important;
        }
        
        .sidebar-section .nav-collapse {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        
        .sidebar-section.open .nav-collapse {
            max-height: 1000px;
        }
        
        .sidebar-footer {
            background-color: var(--sidebar-header-bg);
            border-top: 1px solid var(--sidebar-border);
        }
        
        .btn-outline-light {
            border-color: var(--sidebar-border);
            color: var(--sidebar-text);
        }
        
        .btn-outline-light:hover {
            background-color: var(--sidebar-hover-bg);
            border-color: var(--sidebar-hover-bg);
        }
    </style>
    
    <div class="sidebar-header p-3 border-bottom border-secondary">
        <h5 class="mb-0">
            <i class="bi bi-mortarboard-fill me-2"></i>
            HelpingHand ERP
        </h5>
        <small class="text-muted">Production System</small>
    </div>
    
    <div class="sidebar-content overflow-y-auto" id="sidebar-content" style="height: calc(100vh - 140px);">
        <ul class="nav flex-column">
            @if(Auth::check())
            
            <!-- 🏫 1. DASHBOARD -->
            @if(Route::has('admin.dashboard'))
            <li class="nav-item">
                <a class="nav-link text-white {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" 
                   href="{{ route('admin.dashboard') }}">
                    <i class="bi bi-speedometer2 me-2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            @endif

            <!-- 📞 1.5 FRONT OFFICE / ENQUIRIES -->
            @if(Auth::user()->hasRole('admin') || Auth::user()->hasRole('super-admin') || Auth::user()->hasRole('receptionist') || Auth::user()->hasRole('teacher') || Auth::user()->hasRole('clerk'))
            <li class="nav-item">
                <a class="nav-link text-white {{ request()->routeIs('admin.front-office.enquiries.*') ? 'active' : '' }}"
                   href="{{ route('admin.front-office.enquiries.index') }}">
                    <i class="bi bi-telephone-inbound-fill me-2"></i>
                    <span>Front Office / Enquiries</span>
                </a>
            </li>
            @endif

            <!-- 🛡️ 1.6 GATEKEEPER / SECURITY TERMINAL -->
            @if(Auth::user()->hasRole('admin') || Auth::user()->hasRole('super-admin') || Auth::user()->hasRole('receptionist') || Auth::user()->hasRole('guard') || Auth::user()->hasRole('teacher') || Auth::user()->hasRole('clerk'))
            <li class="nav-item">
                <a class="nav-link text-white {{ request()->routeIs('admin.front-office.gatekeeper') ? 'active' : '' }}" 
                   href="{{ route('admin.front-office.gatekeeper') }}">
                    <i class="bi bi-shield-check-fill me-2"></i>
                    <span>Gatekeeper Terminal</span>
                </a>
            </li>
            @endif

            <!-- 📋 1.7 GUARD DUTY ROSTER -->
            @if(Auth::user()->hasRole('admin') || Auth::user()->hasRole('super-admin') || Auth::user()->hasRole('receptionist'))
            <li class="nav-item">
                <a class="nav-link text-white {{ request()->routeIs('admin.front-office.duty-assignments.*') ? 'active' : '' }}" 
                   href="{{ route('admin.front-office.duty-assignments.index') }}">
                    <i class="bi bi-calendar-check-fill me-2"></i>
                    <span>Guard Duty Roster</span>
                </a>
            </li>
            @endif
            
            <!-- 🎓 2. STUDENT MANAGEMENT -->
            @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-students'))
            <li class="nav-item sidebar-section mt-3" data-section="students">
                <div class="nav-header text-uppercase small px-3 py-2">
                    <i class="bi bi-people me-1"></i> Student Management
                </div>
                <div class="nav-collapse">
                    <ul class="nav flex-column">
                        @if(Route::has('admin.students.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.students.*') ? 'active' : '' }}" 
                               href="{{ route('admin.students.index') }}">
                                <i class="bi bi-person-lines-fill me-2"></i>
                                <span>Students</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.parents.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.parents.*') ? 'active' : '' }}" 
                               href="{{ route('admin.parents.index') }}">
                                <i class="bi bi-person-fill-gear me-2"></i>
                                <span>Parents</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.student-promotions.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.student-promotions.*') ? 'active' : '' }}" 
                               href="{{ route('admin.student-promotions.index') }}">
                                <i class="bi bi-arrow-up-circle me-2"></i>
                                <span>Student Promotion</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.student-statuses.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.student-statuses.*') ? 'active' : '' }}" 
                               href="{{ route('admin.student-statuses.index') }}">
                                <i class="bi bi-person-x me-2"></i>
                                <span>Student Records</span>
                            </a>
                        </li>
                        @endif
                    </ul>
                </div>
            </li>
            @endif
                 <!-- 👨‍🏫 3. TEACHER MANAGEMENT -->
            @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-teachers'))
            <li class="nav-item sidebar-section mt-3" data-section="teachers">
                <div class="nav-header text-uppercase small px-3 py-2">
                    <i class="bi bi-person-badge me-1"></i> Teacher Management
                </div>
                <div class="nav-collapse">
                    <ul class="nav flex-column">
                        @if(Route::has('admin.teachers.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.teachers.*') ? 'active' : '' }}" 
                                href="{{ route('admin.teachers.index') }}">
                                <i class="bi bi-people me-2"></i>
                                <span>Teachers</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.teacher-substitutions.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.teacher-substitutions.*') ? 'active' : '' }}" 
                               href="{{ route('admin.teacher-substitutions.index') }}">
                                <i class="bi bi-arrow-left-right me-2"></i>
                                <span>Teacher Substitution</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.teacher-biometrics.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.teacher-biometrics.*') ? 'active' : '' }}" 
                               href="{{ route('admin.teacher-biometrics.index') }}">
                                <i class="bi bi-fingerprint me-2"></i>
                                <span>Teacher Biometric</span>
                            </a>
                        </li>
                        @endif
                    </ul>
                </div>
            </li>
            @endif

            <!-- 👥 HR & PAYROLL -->
            @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('can-view-salary-data'))
            <li class="nav-item sidebar-section mt-3" data-section="hr-payroll">
                <div class="nav-header text-uppercase small px-3 py-2">
                    <i class="bi bi-person-workspace me-1"></i> HR & Payroll
                </div>
                <div class="nav-collapse">
                    <ul class="nav flex-column">
                        @if(Route::has('admin.leaves.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.leaves.*') ? 'active' : '' }}" 
                               href="{{ route('admin.leaves.index') }}">
                                <i class="bi bi-calendar-range me-2"></i>
                                <span>Teacher Leaves</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.hr.payroll.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.hr.payroll.*') ? 'active' : '' }}" 
                               href="{{ route('admin.hr.payroll.index') }}">
                                <i class="bi bi-wallet2 me-2"></i>
                                <span>Payroll & Salaries</span>
                            </a>
                        </li>
                        @endif
                    </ul>
                </div>
            </li>
            @endif

            <!-- 🗄️ BULK IMPORT (was "Data Management" — renamed for clarity, see nav-subtitle) -->
            @if(Auth::user()->hasRole(['admin', 'super-admin']))
            <li class="nav-item sidebar-section mt-3" data-section="imports">
                <div class="nav-header text-uppercase small px-3 py-2">
                    <i class="bi bi-database-fill-gear me-1"></i> Bulk Import
                    <div class="nav-subtitle text-lowercase">Upload a spreadsheet to add many records at once</div>
                </div>
                <div class="nav-collapse">
                    <ul class="nav flex-column">
                        @php
                            $importEngine = app(\App\Services\Imports\ImportEngine::class);
                            $statusColors = [
                                'green' => 'text-success',
                                'yellow' => 'text-warning',
                                'red' => 'text-danger'
                            ];
                        @endphp
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('imports.dashboard') ? 'active' : '' }}" 
                               href="{{ route('imports.dashboard') }}">
                                <i class="bi bi-speedometer2 me-2"></i>
                                <span>Import Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->is('admin/imports/wizard/students') ? 'active' : '' }}" 
                               href="{{ route('imports.wizard', ['module' => 'students']) }}">
                                <i class="bi bi-mortarboard-fill me-2"></i>
                                <span>Student Import</span>
                                <span class="badge float-end mt-1 {{ $statusColors[$importEngine->getModuleStatus('students')] }}">●</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->is('admin/imports/wizard/teachers') ? 'active' : '' }}" 
                               href="{{ route('imports.wizard', ['module' => 'teachers']) }}">
                                <i class="bi bi-person-badge me-2"></i>
                                <span>Teacher Import</span>
                                <span class="badge float-end mt-1 {{ $statusColors[$importEngine->getModuleStatus('teachers')] }}">●</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->is('admin/imports/wizard/parents') ? 'active' : '' }}" 
                               href="{{ route('imports.wizard', ['module' => 'parents']) }}">
                                <i class="bi bi-people me-2"></i>
                                <span>Parent Import</span>
                                <span class="badge float-end mt-1 {{ $statusColors[$importEngine->getModuleStatus('parents')] }}">●</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->is('admin/imports/wizard/classes') ? 'active' : '' }}" 
                               href="{{ route('imports.wizard', ['module' => 'classes']) }}">
                                <i class="bi bi-building me-2"></i>
                                <span>Class Import</span>
                                <span class="badge float-end mt-1 {{ $statusColors[$importEngine->getModuleStatus('classes')] }}">●</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->is('admin/imports/wizard/sections') ? 'active' : '' }}" 
                               href="{{ route('imports.wizard', ['module' => 'sections']) }}">
                                <i class="bi bi-diagram-3 me-2"></i>
                                <span>Section Import</span>
                                <span class="badge float-end mt-1 {{ $statusColors[$importEngine->getModuleStatus('sections')] }}">●</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->is('admin/imports/wizard/subjects') ? 'active' : '' }}" 
                               href="{{ route('imports.wizard', ['module' => 'subjects']) }}">
                                <i class="bi bi-book-half me-2"></i>
                                <span>Subject Import</span>
                                <span class="badge float-end mt-1 {{ $statusColors[$importEngine->getModuleStatus('subjects')] }}">●</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->is('admin/imports/wizard/routes') ? 'active' : '' }}"
                               href="{{ route('imports.wizard', ['module' => 'routes']) }}">
                                <i class="bi bi-bus-front me-2"></i>
                                <span>Transport Route Import</span>
                                <span class="badge float-end mt-1 {{ $statusColors[$importEngine->getModuleStatus('routes')] }}">●</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('imports.history') ? 'active' : '' }}"
                               href="{{ route('imports.history') }}">
                                <i class="bi bi-clock-history me-2"></i>
                                <span>Import History</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('imports.mapping-profiles') ? 'active' : '' }}" 
                               href="{{ route('imports.mapping-profiles') }}">
                                <i class="bi bi-file-earmark-diff me-2"></i>
                                <span>Mapping Profiles</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('imports.templates') ? 'active' : '' }}" 
                               href="{{ route('imports.templates') }}">
                                <i class="bi bi-file-earmark-spreadsheet me-2"></i>
                                <span>Import Templates</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            @endif

            <!-- 🛠️ OPERATIONS CONTROL -->
            @if(Auth::user()->hasRole(['admin', 'super-admin']))
            <li class="nav-item sidebar-section mt-3" data-section="operations">
                <div class="nav-header text-uppercase small px-3 py-2">
                    <i class="bi bi-gear-wide-connected me-1"></i> Operations Control
                </div>
                <div class="nav-collapse">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('operations.health') ? 'active' : '' }}" 
                               href="{{ route('operations.health') }}">
                                <i class="bi bi-heart-pulse me-2"></i>
                                <span>System Health</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('operations.settings') ? 'active' : '' }}" 
                               href="{{ route('operations.settings') }}">
                                <i class="bi bi-sliders me-2"></i>
                                <span>ERP Config Center</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            @endif
            
            <!-- 🧑‍🏫 4. ACADEMIC MANAGEMENT -->
            @if(Auth::user()->hasRole(['admin', 'staff']) || Auth::user()->hasPermission('view-syllabi') || Auth::user()->hasPermission('view-daily-teaching-work'))
            <li class="nav-item sidebar-section mt-3" data-section="academic">
                <div class="nav-header text-uppercase small px-3 py-2">
                    <i class="bi bi-mortarboard me-1"></i> Academic Management
                </div>
                <div class="nav-collapse">
                    <ul class="nav flex-column">
                        @if(Route::has('admin.classes.index') && (Auth::user()->hasRole(['admin', 'staff']) || Auth::user()->hasPermission('view-classes')))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.classes.*') ? 'active' : '' }}" 
                               href="{{ route('admin.classes.index') }}">
                                <i class="bi bi-building me-2"></i>
                                <span>Classes</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.sections.index') && (Auth::user()->hasRole(['admin', 'staff']) || Auth::user()->hasPermission('view-sections')))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.sections.*') ? 'active' : '' }}" 
                               href="{{ route('admin.sections.index') }}">
                                <i class="bi bi-diagram-3 me-2"></i>
                                <span>Sections</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.subjects.index') && (Auth::user()->hasRole(['admin', 'staff']) || Auth::user()->hasPermission('view-subjects')))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.subjects.*') ? 'active' : '' }}" 
                               href="{{ route('admin.subjects.index') }}">
                                <i class="bi bi-book-half me-2"></i>
                                <span>Subjects</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.academic-sessions.index') && (Auth::user()->hasRole(['admin', 'staff']) || Auth::user()->hasPermission('view-academic-sessions')))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.academic-sessions.*') ? 'active' : '' }}" 
                               href="{{ route('admin.academic-sessions.index') }}">
                                <i class="bi bi-calendar-check me-2"></i>
                                <span>Academic Sessions</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.syllabi.index') && (Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-syllabi')))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.syllabi.*') ? 'active' : '' }}" 
                               href="{{ route('admin.syllabi.index') }}">
                                <i class="bi bi-journal-bookmark me-2"></i>
                                <span>Syllabus</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.daily-teaching-work.index') && (Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-daily-teaching-work')))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.daily-teaching-work.*') ? 'active' : '' }}" 
                               href="{{ route('admin.daily-teaching-work.index') }}">
                                <i class="bi bi-journal-text me-2"></i>
                                <span>Daily Teaching Work</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.lesson-plans.index') && (Auth::user()->hasRole('admin') || Auth::user()->hasRole('teacher')))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.lesson-plans.*') ? 'active' : '' }}" 
                               href="{{ route('admin.lesson-plans.index') }}">
                                <i class="bi bi-bookmarks me-2"></i>
                                <span>Lesson Plans</span>
                            </a>
                        </li>
                        @endif
                    </ul>
                </div>
            </li>
            @endif
            
            <!-- 📅 5. ATTENDANCE SYSTEM -->
            @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-attendance'))
            <li class="nav-item sidebar-section mt-3" data-section="attendance">
                <div class="nav-header text-uppercase small px-3 py-2">
                    <i class="bi bi-calendar-check me-1"></i> Attendance System
                </div>
                <div class="nav-collapse">
                    <ul class="nav flex-column">
                        @if(Route::has('admin.attendance.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.attendance.*') ? 'active' : '' }}" 
                               href="{{ route('admin.attendance.index') }}">
                                <i class="bi bi-person-check me-2"></i>
                                <span>Student Attendance</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.teacher-attendance.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.teacher-attendance.*') ? 'active' : '' }}" 
                               href="{{ route('admin.teacher-attendance.index') }}">
                                <i class="bi bi-people me-2"></i>
                                <span>Teacher Attendance</span>
                            </a>
                        </li>
                        @endif
                    </ul>
                </div>
            </li>
            @endif
            
            <!-- 🎓 6. EXAMINATION SYSTEM -->
            @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-exams') || Auth::user()->hasPermission('view-results') || Auth::user()->hasPermission('view-exam-papers'))
            <li class="nav-item sidebar-section mt-3" data-section="exams">
                <div class="nav-header text-uppercase small px-3 py-2">
                    <i class="bi bi-clipboard me-1"></i> Examination System
                </div>
                <div class="nav-collapse">
                    <ul class="nav flex-column">
                        @if(Route::has('admin.exams.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.exams.*') && !request()->routeIs('exams.arrangements.*') ? 'active' : '' }}" 
                               href="{{ route('admin.exams.index') }}">
                                <i class="bi bi-clipboard me-2"></i>
                                <span>Exams</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.exams.arrangements.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.exams.arrangements.*') ? 'active' : '' }}"
                               href="{{ route('admin.exams.arrangements.index') }}">
                                <i class="bi bi-grid-3x3-gap me-2"></i>
                                <span>Exam Arrangements</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.exam-papers.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.exam-papers.*') ? 'active' : '' }}" 
                               href="{{ route('admin.exam-papers.index') }}">
                                <i class="bi bi-file-earmark-text me-2"></i>
                                <span>Exam Papers</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.exam-paper-templates.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.exam-paper-templates.*') ? 'active' : '' }}" 
                               href="{{ route('admin.exam-paper-templates.index') }}">
                                <i class="bi bi-file-earmark-medical me-2"></i>
                                <span>Exam Paper Templates</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.results.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.results.*') ? 'active' : '' }}" 
                               href="{{ route('admin.results.index') }}">
                                <i class="bi bi-award me-2"></i>
                                <span>Results</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.result-formats.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.result-formats.*') ? 'active' : '' }}" 
                               href="{{ route('admin.result-formats.index') }}">
                                <i class="bi bi-layout-text-window-reverse me-2"></i>
                                <span>Result Formats</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.admit-cards.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.admit-cards.*') ? 'active' : '' }}" 
                               href="{{ route('admin.admit-cards.index') }}">
                                <i class="bi bi-card-checklist me-2"></i>
                                <span>Admit Cards</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.admit-card-formats.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.admit-card-formats.*') ? 'active' : '' }}" 
                               href="{{ route('admin.admit-card-formats.index') }}">
                                <i class="bi bi-card-heading me-2"></i>
                                <span>Admit Card Formats</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.configurations.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->getRequestUri() == '/admin/configurations#config-card-exam' ? 'active' : '' }}" 
                               href="{{ route('admin.configurations.index') }}#config-card-exam">
                                <i class="bi bi-gear me-2"></i>
                                <span>Exam Settings</span>
                            </a>
                        </li>
                        @endif
                    </ul>
                </div>
            </li>
            @endif
            
            <!-- 💰 7. FEE MANAGEMENT -->
            @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-fees') || Auth::user()->hasPermission('can-manage-fees'))
            <li class="nav-item sidebar-section mt-3" data-section="fees">
                <div class="nav-header text-uppercase small px-3 py-2">
                    <i class="bi bi-currency-dollar me-1"></i> Fee Management
                </div>
                <div class="nav-collapse">
                    <ul class="nav flex-column">
                        @if(Route::has('admin.fees.dashboard'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.fees.dashboard') ? 'active' : '' }}" 
                               href="{{ route('admin.fees.dashboard') }}">
                                <i class="bi bi-speedometer2 me-2"></i>
                                <span>Accountant Dashboard</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.fees.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.fees.*') ? 'active' : '' }}" 
                               href="{{ route('admin.fees.index') }}">
                                <i class="bi bi-wallet2 me-2"></i>
                                <span>Fees</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.fee-structures.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.fee-structures.*') ? 'active' : '' }}" 
                               href="{{ route('admin.fee-structures.index') }}">
                                <i class="bi bi-calculator me-2"></i>
                                <span>Fee Structures</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.fees.demand-register'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.fees.demand-register') ? 'active' : '' }}" 
                               href="{{ route('admin.fees.demand-register') }}">
                                <i class="bi bi-journal-text me-2"></i>
                                <span>Fee Demand Register</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.fees.collection-register'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.fees.collection-register') ? 'active' : '' }}" 
                               href="{{ route('admin.fees.collection-register') }}">
                                <i class="bi bi-cash-stack me-2"></i>
                                <span>Daily Collection Register</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.fees.cashier-closings.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.fees.cashier-closings.*') ? 'active' : '' }}" 
                               href="{{ route('admin.fees.cashier-closings.index') }}">
                                <i class="bi bi-lock me-2"></i>
                                <span>Cashier Closing</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.fees.defaulters.dashboard'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.fees.defaulters.dashboard') ? 'active' : '' }}" 
                               href="{{ route('admin.fees.defaulters.dashboard') }}">
                                <i class="bi bi-speedometer2 me-2"></i>
                                <span>Defaulter Dashboard</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.fees.defaulters.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.fees.defaulters.index') ? 'active' : '' }}" 
                               href="{{ route('admin.fees.defaulters.index') }}">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <span>Defaulter Registry</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.fees.year-closing.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.fees.year-closing.index') ? 'active' : '' }}" 
                               href="{{ route('admin.fees.year-closing.index') }}">
                                <i class="bi bi-calendar-check me-2"></i>
                                <span>Year-End Closing</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.fees.reports.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.fees.reports.index') ? 'active' : '' }}"
                               href="{{ route('admin.fees.reports.index') }}">
                                <i class="bi bi-file-earmark-bar-graph me-2"></i>
                                <span>Finance Reports</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.finance.reconciliation.overpayments'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.finance.reconciliation.*') ? 'active' : '' }}"
                               href="{{ route('admin.finance.reconciliation.overpayments') }}">
                                <i class="bi bi-arrow-left-right me-2"></i>
                                <span>Reconciliation Center</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.security-deposits.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.security-deposits.*') ? 'active' : '' }}"
                               href="{{ route('admin.security-deposits.index') }}">
                                <i class="bi bi-shield-lock me-2"></i>
                                <span>Security Deposits</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('imports.wizard'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('imports.wizard') && request('module') === 'bank_statement' ? 'active' : '' }}"
                               href="{{ route('imports.wizard', ['module' => 'bank_statement']) }}">
                                <i class="bi bi-cloud-arrow-up me-2"></i>
                                <span>Upload Bank Statement</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.payment-claims.queue'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.payment-claims.*') ? 'active' : '' }}"
                               href="{{ route('admin.payment-claims.queue') }}">
                                <i class="bi bi-qr-code me-2"></i>
                                <span>UPI Payment Matching</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.payment-info.show'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.payment-info.show') ? 'active' : '' }}"
                               href="{{ route('admin.payment-info.show') }}">
                                <i class="bi bi-qr-code-scan me-2"></i>
                                <span>Payment Info (QR & Bank)</span>
                            </a>
                        </li>
                        @endif
                    </ul>
                </div>
            </li>
            @endif

            <!-- 📊 8. BUDGET & EXPENSES -->
            @if(Auth::user()->hasRole('admin') || Auth::user()->hasRole('accountant') || Auth::user()->hasPermission('view-budgets'))
            <li class="nav-item sidebar-section mt-3" data-section="budget">
                <div class="nav-header text-uppercase small px-3 py-2">
                    <i class="bi bi-pie-chart me-1"></i> Budget & Expenses
                </div>
                <div class="nav-collapse">
                    <ul class="nav flex-column">
                        @if(Route::has('admin.budgets.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.budgets.*') ? 'active' : '' }}" 
                               href="{{ route('admin.budgets.index') }}">
                                <i class="bi bi-pie-chart me-2"></i>
                                <span>Budget</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.expenses.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.expenses.*') ? 'active' : '' }}" 
                               href="{{ route('admin.expenses.index') }}">
                                <i class="bi bi-receipt me-2"></i>
                                <span>Expenses</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.budget-categories.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.budget-categories.*') ? 'active' : '' }}" 
                               href="{{ route('admin.budget-categories.index') }}">
                                <i class="bi bi-tag me-2"></i>
                                <span>Budget Categories</span>
                            </a>
                        </li>
                        @endif
                    </ul>
                </div>
            </li>
            @endif
            
            <!-- 📚 9. LIBRARY MANAGEMENT -->
            @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('manage-library'))
            <li class="nav-item sidebar-section mt-3" data-section="library">
                <div class="nav-header text-uppercase small px-3 py-2">
                    <i class="bi bi-book me-1"></i> Library Management
                </div>
                <div class="nav-collapse">
                    <ul class="nav flex-column">
                        @if(Route::has('admin.books.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.books.*') ? 'active' : '' }}" 
                               href="{{ route('admin.books.index') }}">
                                <i class="bi bi-book-half me-2"></i>
                                <span>Books</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.book-issues.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.book-issues.*') ? 'active' : '' }}" 
                               href="{{ route('admin.book-issues.index') }}">
                                <i class="bi bi-arrow-left-right me-2"></i>
                                <span>Book Issue/Return</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.library-settings.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.library-settings.*') ? 'active' : '' }}" 
                               href="{{ route('admin.library-settings.index') }}">
                                <i class="bi bi-gear me-2"></i>
                                <span>Library Settings</span>
                            </a>
                        </li>
                        @endif
                    </ul>
                </div>
            </li>
            @endif
            
            <!-- 📦 10. INVENTORY MANAGEMENT -->
            @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-inventory'))
            <li class="nav-item sidebar-section mt-3" data-section="inventory">
                <div class="nav-header text-uppercase small px-3 py-2">
                    <i class="bi bi-box-seam me-1"></i> Inventory Management
                </div>
                <div class="nav-collapse">
                    <ul class="nav flex-column">
                        @if(Route::has('admin.inventory.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.inventory.*') ? 'active' : '' }}" 
                               href="{{ route('admin.inventory.index') }}">
                                <i class="bi bi-boxes me-2"></i>
                                <span>Inventory Dashboard</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.assets.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.assets.*') ? 'active' : '' }}" 
                               href="{{ route('admin.assets.index') }}">
                                <i class="bi bi-laptop me-2"></i>
                                <span>Assets</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.inventory.categories.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.inventory.categories.*') ? 'active' : '' }}" 
                               href="{{ route('admin.inventory.categories.index') }}">
                                <i class="bi bi-folder me-2"></i>
                                <span>Asset Categories</span>
                            </a>
                        </li>
                        @endif
                    </ul>
                </div>
            </li>
            @endif
            
            <!-- 📄 11. CERTIFICATE MANAGEMENT -->
            @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('view-certificates'))
            <li class="nav-item sidebar-section mt-3" data-section="certificates">
                <div class="nav-header text-uppercase small px-3 py-2">
                    <i class="bi bi-award me-1"></i> Certificate Management
                </div>
                <div class="nav-collapse">
                    <ul class="nav flex-column">
                        @if(Route::has('admin.certificates.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.certificates.*') ? 'active' : '' }}" 
                               href="{{ route('admin.certificates.index') }}">
                                <i class="bi bi-award-fill me-2"></i>
                                <span>Certificates</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.certificate-templates.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.certificate-templates.*') ? 'active' : '' }}" 
                               href="{{ route('admin.certificate-templates.index') }}">
                                <i class="bi bi-file-earmark-medical me-2"></i>
                                <span>Certificate Templates</span>
                            </a>
                        </li>
                        @endif
                    </ul>
                </div>
            </li>
            @endif
            
            <!-- 🔧 12. SYSTEM CONFIGURATION -->
            @if(Auth::user()->hasRole('admin'))
            <li class="nav-item sidebar-section mt-3" data-section="settings">
                <div class="nav-header text-uppercase small px-3 py-2">
                    <i class="bi bi-gear me-1"></i> System Configuration
                </div>
                <div class="nav-collapse">
                    <ul class="nav flex-column">
                        @if(Route::has('admin.configurations.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.configurations.*') ? 'active' : '' }}" 
                               href="{{ route('admin.configurations.index') }}">
                                <i class="bi bi-sliders me-2"></i>
                                <span>System Settings</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.language-settings.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.language-settings.*') ? 'active' : '' }}" 
                               href="{{ route('admin.language-settings.index') }}">
                                <i class="bi bi-translate me-2"></i>
                                <span>Language Settings</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.notification-settings.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.notification-settings.*') ? 'active' : '' }}" 
                               href="{{ route('admin.notification-settings.index') }}">
                                <i class="bi bi-bell me-2"></i>
                                <span>Notification Settings</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('users.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('users.*') ? 'active' : '' }}" 
                               href="{{ route('users.index') }}">
                                <i class="bi bi-people me-2"></i>
                                <span>User Management</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.accounts.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.accounts.*') ? 'active' : '' }}" 
                               href="{{ route('admin.accounts.index') }}">
                                <i class="bi bi-shield-lock-fill me-2"></i>
                                <span>Account Management</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.role-permissions.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.role-permissions.*') ? 'active' : '' }}" 
                               href="{{ route('admin.role-permissions.index') }}">
                                <i class="bi bi-shield-lock me-2"></i>
                                <span>Role & Permissions</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.field-permissions.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.field-permissions.*') ? 'active' : '' }}" 
                               href="{{ route('admin.field-permissions.index') }}">
                                <i class="bi bi-lock me-2"></i>
                                <span>Field Permissions</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.class-teacher-control.student-records'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.class-teacher-control.*') ? 'active' : '' }}" 
                               href="{{ route('admin.class-teacher-control.student-records') }}">
                                <i class="bi bi-person-check me-2"></i>
                                <span>Class Teacher Control</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.teacher-subject-assignments.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.teacher-subject-assignments.*') ? 'active' : '' }}" 
                               href="{{ route('admin.teacher-subject-assignments.index') }}">
                                <i class="bi bi-arrow-left-right me-2"></i>
                                <span>Teacher-Subject Assignment</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.teacher-class-assignments.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.teacher-class-assignments.*') ? 'active' : '' }}" 
                               href="{{ route('admin.teacher-class-assignments.index') }}">
                                <i class="bi bi-arrow-down-up me-2"></i>
                                <span>Teacher-Class Assignment</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.grading-systems.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.grading-systems.*') ? 'active' : '' }}" 
                               href="{{ route('admin.grading-systems.index') }}">
                                <i class="bi bi-bar-chart me-2"></i>
                                <span>Grading Systems</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.examination-patterns.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.examination-patterns.*') ? 'active' : '' }}" 
                               href="{{ route('admin.examination-patterns.index') }}">
                                <i class="bi bi-layout-three-columns me-2"></i>
                                <span>Examination Patterns</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.document-formats.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.document-formats.*') ? 'active' : '' }}" 
                               href="{{ route('admin.document-formats.index') }}">
                                <i class="bi bi-file-earmark-text me-2"></i>
                                <span>Document Formats</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.permissions.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.permissions.*') ? 'active' : '' }}" 
                               href="{{ route('admin.permissions.index') }}">
                                <i class="bi bi-key me-2"></i>
                                <span>Permissions</span>
                            </a>
                        </li>
                        @endif
                    </ul>
                </div>
            </li>
            @endif
            
            <!-- 🔍 13. REPORTS & AUDIT -->
            @if(Auth::user()->hasRole('admin') || Auth::user()->hasPermission('access-audit-logs'))
            <li class="nav-item sidebar-section mt-3" data-section="reports">
                <div class="nav-header text-uppercase small px-3 py-2">
                    <i class="bi bi-file-bar-graph me-1"></i> Reports & Audit
                </div>
                <div class="nav-collapse">
                    <ul class="nav flex-column">
                        @if(Route::has('admin.reports.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}" 
                               href="{{ route('admin.reports.index') }}">
                                <i class="bi bi-file-bar-graph me-2"></i>
                                <span>All Reports</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.advanced-reports.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.advanced-reports.*') ? 'active' : '' }}" 
                               href="{{ route('admin.advanced-reports.index') }}">
                                <i class="bi bi-graph-up me-2"></i>
                                <span>Advanced Reports</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.audit-logs.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}" 
                               href="{{ route('admin.audit-logs.index') }}">
                                <i class="bi bi-shield-lock me-2"></i>
                                <span>Audit Logs</span>
                            </a>
                        </li>
                        @endif

                    </ul>
                </div>
            </li>
            @endif
            
            <!-- ⚙️ 14. SYSTEM TOOLS -->
            @if(Auth::user()->hasRole('admin'))
            <li class="nav-item sidebar-section mt-3" data-section="tools">
                <div class="nav-header text-uppercase small px-3 py-2">
                    <i class="bi bi-tools me-1"></i> System Tools
                </div>
                <div class="nav-collapse">
                    <ul class="nav flex-column">
                        @if(Route::has('admin.backups.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.backups.*') ? 'active' : '' }}" 
                               href="{{ route('admin.backups.index') }}">
                                <i class="bi bi-cloud-arrow-up me-2"></i>
                                <span>Backup Management</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.bell-schedules.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.bell-schedules.*') ? 'active' : '' }}" 
                               href="{{ route('admin.bell-schedules.index') }}">
                                <i class="bi bi-bell me-2"></i>
                                <span>Bell Schedules</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.special-day-overrides.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.special-day-overrides.*') ? 'active' : '' }}" 
                               href="{{ route('admin.special-day-overrides.index') }}">
                                <i class="bi bi-calendar-x me-2"></i>
                                <span>Special Day Overrides</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.biometric-devices.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.biometric-devices.*') ? 'active' : '' }}" 
                               href="{{ route('admin.biometric-devices.index') }}">
                                <i class="bi bi-fingerprint me-2"></i>
                                <span>Biometric Devices</span>
                            </a>
                        </li>
                        @endif
                        @if(Route::has('admin.sync-monitor.index'))
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('admin.sync-monitor.*') ? 'active' : '' }}" 
                               href="{{ route('admin.sync-monitor.index') }}">
                                <i class="bi bi-arrow-repeat me-2"></i>
                                <span>Sync Monitor</span>
                            </a>
                        </li>
                        @endif
                    </ul>
                </div>
            </li>
            @endif
            @endif

            <!-- Dynamic Registry Sidebar Sections -->
            @php
                $registry = app(\App\Services\Registry\ErpRegistry::class);
            @endphp
            @if(Auth::check())
            @foreach($registry->getSidebarEntries() as $sectionKey => $section)
                @if(Auth::user()->hasRole($section['roles'] ?? ['admin', 'super-admin']))
                <li class="nav-item sidebar-section mt-3" data-section="{{ $sectionKey }}">
                    <div class="nav-header text-uppercase small px-3 py-2">
                        <i class="bi {{ $section['icon'] ?? 'bi-box' }} me-1"></i> {{ $section['title'] }}
                    </div>
                    <div class="nav-collapse">
                        <ul class="nav flex-column">
                            @foreach($section['links'] as $link)
                                @if(!isset($link['permission']) || Auth::user()->hasPermission($link['permission']))
                                <li class="nav-item">
                                    <a class="nav-link text-white {{ request()->getRequestUri() === $link['url'] || request()->path() === ltrim($link['url'], '/') ? 'active' : '' }}" href="{{ $link['url'] }}">
                                        <i class="bi {{ $link['icon'] ?? 'bi-link' }} me-2"></i>
                                        <span>{{ $link['title'] }}</span>
                                    </a>
                                </li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                </li>
                @endif
            @endforeach
            @endif
        </ul>
    </div>
    
    <!-- Collapsible Toggle -->
    <div class="sidebar-footer border-top border-secondary p-2">
        <button id="sidebar-toggle" class="btn btn-sm btn-outline-light w-100">
            <i class="bi bi-arrow-bar-left"></i>
            <span>Collapse</span>
        </button>
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const main = document.getElementById('main-content');
    const toggle = document.getElementById('sidebar-toggle');
    const toggleIcon = toggle.querySelector('i');
    const toggleText = toggle.querySelector('span');
    const sidebarContent = document.getElementById('sidebar-content');
    const sections = document.querySelectorAll('.sidebar-section');
    
    // 🔧 SCROLL POSITION PERSISTENCE
    const savedScroll = localStorage.getItem('sidebarScroll');
    if (savedScroll) {
        sidebarContent.scrollTop = parseInt(savedScroll);
    }
    
    sidebarContent.addEventListener('scroll', () => {
        localStorage.setItem('sidebarScroll', sidebarContent.scrollTop);
    });
    
    // 🎯 COLLAPSE/EXPAND TOGGLE
    toggle.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        main.classList.toggle('sidebar-collapsed');
        
        if (sidebar.classList.contains('collapsed')) {
            toggleIcon.className = 'bi bi-arrow-bar-right';
            toggleText.textContent = 'Expand';
        } else {
            toggleIcon.className = 'bi bi-arrow-bar-left';
            toggleText.textContent = 'Collapse';
        }
    });
    
    // 📂 ACCORDION BEHAVIOR - SINGLE SECTION EXPANSION
    let activeSection = null;
    
    sections.forEach(section => {
        const header = section.querySelector('.nav-header');
        const collapse = section.querySelector('.nav-collapse');
        const links = section.querySelectorAll('.nav-link');
        
        // Check if any link in this section is active
        let sectionIsActive = false;
        links.forEach(link => {
            if (link.classList.contains('active')) {
                sectionIsActive = true;
            }
        });
        
        // Auto-expand active section
        if (sectionIsActive) {
            section.classList.add('open');
            activeSection = section;
        }
        
        // Add click handler for section header
        header.addEventListener('click', (e) => {
            e.preventDefault();
            
            // Close all other sections
            sections.forEach(s => {
                if (s !== section) {
                    s.classList.remove('open');
                }
            });
            
            // Toggle current section
            section.classList.toggle('open');
            activeSection = section.classList.contains('open') ? section : null;
        });
    });
    
    // 🔗 ACTIVE STATE MANAGEMENT - Route-based highlighting
    const currentPath = window.location.pathname;
    const allLinks = document.querySelectorAll('.nav-link');
    
    allLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && currentPath.includes(href.replace('{{ url('/') }}', ''))) {
            link.classList.add('active');
            
            // Find parent section and expand it
            const parentSection = link.closest('.sidebar-section');
            if (parentSection) {
                parentSection.classList.add('open');
                activeSection = parentSection;
            }
        }
    });
    
    // 🎯 AUTO-SCROLL TO ACTIVE SECTION
    if (activeSection) {
        setTimeout(() => {
            const header = activeSection.querySelector('.nav-header');
            if (header) {
                header.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }, 100);
    }
});
</script>

<style>
/* COLLAPSED STATE STYLING */
.admin-sidebar.collapsed .nav-link span {
    display: none;
}

.admin-sidebar.collapsed .nav-header {
    display: none;
}

.admin-sidebar.collapsed .sidebar-header h5,
.admin-sidebar.collapsed .sidebar-header small {
    font-size: 0.75rem;
    text-align: center;
}

.admin-sidebar.collapsed .sidebar-footer {
    text-align: center;
}

.admin-sidebar.collapsed .sidebar-footer span {
    display: none;
}
</style>