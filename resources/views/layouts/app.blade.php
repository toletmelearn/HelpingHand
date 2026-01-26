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
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #2c3e50;">
        <div class="container">
            <a class="navbar-brand" href="{{ url('/') }}">
                🫱 HelpingHand
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

                    <!-- Bell Timing Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-alarm"></i> Bell Timing
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('bell-timing.index') }}">
                                <i class="bi bi-list"></i> Bell Schedules
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('bell-timing.create') }}">
                                <i class="bi bi-plus-circle"></i> Add Schedule
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('bell-timing.weekly') }}">
                                <i class="bi bi-calendar-week"></i> Weekly View
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
                            <li><hr class="dropdown-divider"></li>
                            <!-- Bell Schedule -->
                            <li><a class="dropdown-item" href="{{ route('admin.bell-schedules.index') }}">
                                <i class="bi bi-alarm"></i> Bell Schedule
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.special-day-overrides.index') }}">
                                <i class="bi bi-calendar-event"></i> Special Day Overrides
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.bell-schedules.live-monitor') }}">
                                <i class="bi bi-speedometer2"></i> Live Bell Monitor
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <!-- Class Teacher Control -->
                            <li><a class="dropdown-item" href="{{ route('admin.class-teacher-assignments.index') }}">
                                <i class="bi bi-chalkboard-teacher"></i> Class Teacher Control
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.field-permissions.index') }}">
                                <i class="bi bi-shield-lock"></i> Edit Permissions
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <!-- Teacher Substitution -->
                            <li><a class="dropdown-item" href="{{ route('admin.teacher-substitutions.index') }}">
                                <i class="bi bi-exchange"></i> Teacher Substitution
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.teacher-substitutions.today') }}">
                                <i class="bi bi-calendar-day"></i> Today's Substitutions
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.teacher-substitutions.absence-overview') }}">
                                <i class="bi bi-clipboard-data"></i> Absence Overview
                            </a></li>
                        </ul>
                    </li>

                    <!-- Admin Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-gear"></i> Admin
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('admin.audit-logs.index') }}">
                                <i class="bi bi-clipboard-data"></i> Class Teacher Logs
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.audit-logs.index') }}">
                                <i class="bi bi-journal-text"></i> Student Change History
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.field-permissions.index') }}">
                                <i class="bi bi-shield-lock"></i> Permission Manager
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('admin.field-permissions.index') }}">
                                <i class="bi bi-shield-check"></i> Audit & Compliance
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
                                <li><a class="dropdown-item" href="{{ route('profile.two-factor-authentication') }}">
                                    <i class="bi bi-shield-lock"></i> Two-Factor Auth
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