<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'HelpingHand - School Management System')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 100px 0;
        }
        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        .navbar-brand {
            font-size: 1.8rem;
            font-weight: bold;
        }
        .stat-card {
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .progress-bar-custom {
            height: 25px;
            border-radius: 12px;
        }
        .action-btn {
            min-height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            transition: all 0.3s;
        }
        .action-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .navbar-nav .nav-link {
            transition: color 0.2s;
        }
        .navbar-nav .nav-link:hover {
            color: #f8f9fa !important;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #2c3e50;">
        <div class="container">
            <a class="navbar-brand" href="{{ url('/') }}">
                📚 HelpingHand
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ url('/') }}">Home</a>
                    </li>
                    @auth
                    <!-- Students Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-people-fill"></i> Students
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ url('/students') }}">
                                <i class="bi bi-list"></i> All Students
                            </a></li>
                            <li><a class="dropdown-item" href="{{ url('/students/create') }}">
                                <i class="bi bi-person-plus"></i> Add Student
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ url('/students-dashboard') }}">
                                <i class="bi bi-graph-up"></i> Students Dashboard
                            </a></li>
                        </ul>
                    </li>
                    
                    <!-- Teachers Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-badge"></i> Teachers
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ url('/teachers') }}">
                                <i class="bi bi-list"></i> All Teachers
                            </a></li>
                            <li><a class="dropdown-item" href="{{ url('/teachers/create') }}">
                                <i class="bi bi-person-plus"></i> Add Teacher
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ url('/teachers-dashboard') }}">
                                <i class="bi bi-graph-up"></i> Teachers Dashboard
                            </a></li>
                        </ul>
                    </li>
                    
                    <!-- Attendance Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-calendar-check"></i> Attendance
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('attendance.index') }}">
                                <i class="bi bi-list"></i> Attendance Records
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('attendance.create') }}">
                                <i class="bi bi-plus-circle"></i> Mark Attendance
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('attendance.reports') }}">
                                <i class="bi bi-bar-chart"></i> Attendance Reports
                            </a></li>
                        </ul>
                    </li>

                    <!-- Academics Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-mortarboard"></i> Academics
                        </a>
                        <ul class="dropdown-menu">
                            <!-- Exam Papers -->
                            <li><a class="dropdown-item" href="{{ route('exam-papers.index') }}">
                                <i class="bi bi-journal-text"></i> Exam Papers
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('exam-papers.create') }}">
                                <i class="bi bi-upload"></i> Upload Paper
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <!-- Daily Teaching Work -->
                            <li><a class="dropdown-item" href="{{ route('admin.daily-teaching-work.index') }}">
                                <i class="bi bi-journal"></i> Daily Teaching Work
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.daily-teaching-work.create') }}">
                                <i class="bi bi-plus-circle"></i> Add Daily Work
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <!-- Syllabus -->
                            <li><a class="dropdown-item" href="{{ route('admin.syllabi.index') }}">
                                <i class="bi bi-book"></i> Syllabus
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.syllabi.create') }}">
                                <i class="bi bi-plus-circle"></i> Add Syllabus
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.syllabi.progress-report') }}">
                                <i class="bi bi-bar-chart"></i> Progress Report
                            </a></li>
                        </ul>
                    </li>
                    
                    <!-- Biometric System -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-fingerprint"></i> Biometric
                        </a>
                        <ul class="dropdown-menu">
                            <!-- Basic Biometric Management -->
                            <li><a class="dropdown-item" href="{{ route('admin.teacher-biometrics.index') }}">
                                <i class="bi bi-speedometer2"></i> Dashboard Overview
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.teacher-biometrics.create') }}">
                                <i class="bi bi-plus-circle"></i> Add Manual Record
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.teacher-biometrics.settings') }}">
                                <i class="bi bi-gear"></i> Rule Configuration
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.teacher-biometrics.reports') }}">
                                <i class="bi bi-bar-chart"></i> Reports & Analytics
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <!-- Device Management -->
                            <li><a class="dropdown-item" href="{{ route('admin.biometric-devices.index') }}">
                                <i class="bi bi-hdd-network"></i> Device Management
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.sync-monitor.index') }}">
                                <i class="bi bi-wifi"></i> Live Sync Monitor
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <!-- Advanced Features -->
                            <li><a class="dropdown-item" href="{{ route('admin.analytics.index') }}">
                                <i class="bi bi-bar-chart-line"></i> Analytics Dashboard
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.performance.index') }}">
                                <i class="bi bi-award"></i> Performance Scores
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <!-- Reports & Configuration -->
                            <li><a class="dropdown-item" href="{{ route('admin.reports.index') }}">
                                <i class="bi bi-file-earmark-pdf"></i> Reports & Exports
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.notifications.index') }}">
                                <i class="bi bi-bell"></i> Notification Rules
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <!-- Data Upload -->
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#uploadModalHeader">
                                <i class="bi bi-upload"></i> Upload CSV Data
                            </a></li>
                        </ul>
                    </li>
                    
                    <!-- Budget Management -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-currency-dollar"></i> Budget
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('budget.index') }}">
                                <i class="bi bi-speedometer2"></i> Budget Dashboard
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('budgets.index') }}">
                                <i class="bi bi-wallet2"></i> Budget Plans
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('budget-categories.index') }}">
                                <i class="bi bi-tags"></i> Categories
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('expenses.index') }}">
                                <i class="bi bi-receipt"></i> Expenses
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('budget.reports') }}">
                                <i class="bi bi-file-bar-graph"></i> Budget Reports
                            </a></li>
                        </ul>
                    </li>
                    
                    <!-- Inventory Management -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-box-seam"></i> Inventory
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('admin.inventory.index') }}">
                                <i class="bi bi-speedometer2"></i> Inventory Dashboard
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.assets.index') }}">
                                <i class="bi bi-list"></i> Asset Master
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.inventory.furniture') }}">
                                <i class="bi bi-chair"></i> Furniture
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.inventory.lab-equipment') }}">
                                <i class="bi bi-flask"></i> Lab Equipment
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.inventory.electronics') }}">
                                <i class="bi bi-laptop"></i> Electronics
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('admin.inventory.reports') }}">
                                <i class="bi bi-file-bar-graph"></i> Inventory Reports
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.inventory.audit-logs') }}">
                                <i class="bi bi-journal-text"></i> Audit Logs
                            </a></li>
                        </ul>
                    </li>
                    
                    <!-- Certificate Management -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-award"></i> Certificates
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('admin.certificates.index') }}">
                                <i class="bi bi-file-earmark-text"></i> Certificate Management
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.certificates.create') }}">
                                <i class="bi bi-plus-circle"></i> Create Certificate
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('admin.certificate-templates.index') }}">
                                <i class="bi bi-layout-wtf"></i> Certificate Templates
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.certificate-templates.create') }}">
                                <i class="bi bi-file-earmark-plus"></i> Create Template
                            </a></li>
                        </ul>
                    </li>
                    
                    <!-- Backup Management -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-cloud-arrow-up"></i> Backup
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('admin.backups.index') }}">
                                <i class="bi bi-cloud-download"></i> Backup Management
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.backups.index') }}">
                                <i class="bi bi-plus-circle"></i> Create Backup
                            </a></li>
                        </ul>
                    </li>
                    @endauth
                </ul>
                
                <ul class="navbar-nav ms-auto">
                    @guest
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">Login</a>
                        </li>
                    @else
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> {{ Auth::user()->name }}
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('home') }}">
                                    <i class="bi bi-house-door"></i> Dashboard
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="{{ route('profile.two-factor-authentication') }}">
                                    <i class="bi bi-shield-lock"></i> Security Settings
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="{{ route('logout') }}" 
                                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    <i class="bi bi-box-arrow-right"></i> Logout
                                </a></li>
                            </ul>
                        </li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <main>
        @yield('content')
    </main>

    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container text-center">
            <p>&copy; 2026 HelpingHand School Management System. All rights reserved.</p>
        </div>
    </footer>

    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
        @csrf
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @yield('scripts')
</body>
</html>

<!-- Quick Upload Modal -->
<div class="modal fade" id="uploadModalHeader" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Biometric Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.teacher-biometrics.upload') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="quickFile" class="form-label">Select CSV/XLSX File</label>
                        <input type="file" class="form-control" id="quickFile" name="file" accept=".csv,.xlsx,.xls" required>
                        <div class="form-text">File should contain columns: Teacher ID, Date, First In Time, Last Out Time, Remarks</div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="quickOverwrite" name="overwrite" value="1">
                        <label class="form-check-label" for="quickOverwrite">Overwrite existing records</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>