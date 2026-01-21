<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🫱 HelpingHand - School Management System</title>
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
                <div class="navbar-nav ms-auto">
                    <!-- Students Dropdown -->
                    <div class="nav-item dropdown">
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
                            <li><a class="dropdown-item" href="#">
                                <i class="bi bi-calendar-check"></i> Attendance
                            </a></li>
                            <li><a class="dropdown-item" href="#">
                                <i class="bi bi-file-text"></i> Results
                            </a></li>
                        </ul>
                    </div>
                    
                    <!-- Teachers Dropdown -->
                    <div class="nav-item dropdown">
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
                            <li><a class="dropdown-item" href="#">
                                <i class="bi bi-cash-stack"></i> Salary Records
                            </a></li>
                            <li><a class="dropdown-item" href="#">
                                <i class="bi bi-calendar-check"></i> Attendance
                            </a></li>
                        </ul>
                    </div>
                    <!-- After existing dropdowns, add: -->
<div class="nav-item dropdown">
    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
        <i class="bi bi-graph-up"></i> Dashboards
    </a>
    <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="{{ url('/students-dashboard') }}">
            <i class="bi bi-people"></i> Students Dashboard
        </a></li>
        <li><a class="dropdown-item" href="{{ url('/teachers-dashboard') }}">
            <i class="bi bi-person-badge"></i> Teachers Dashboard
        </a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="{{ url('/students') }}">
            <i class="bi bi-list"></i> Students List
        </a></li>
        <li><a class="dropdown-item" href="{{ url('/teachers') }}">
            <i class="bi bi-list"></i> Teachers List
        </a></li>
    </ul>
</div>
                    <a class="nav-link" href="#features">
                        <i class="bi bi-stars"></i> Features
                    </a>
                    <a class="nav-link" href="#progress">
                        <i class="bi bi-graph-up"></i> Progress
                    </a>
                    <a class="nav-link btn btn-success ms-2" href="{{ url('/students/create') }}">
                        <i class="bi bi-rocket-takeoff"></i> Get Started
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section text-center">
        <div class="container">
            <h1 class="display-4 mb-4">Welcome to HelpingHand</h1>
            <p class="lead mb-4">Your complete school management solution built with ❤️</p>
            
            <!-- Live Stats -->
            <div class="row justify-content-center mb-5">
                <div class="col-md-10">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card bg-primary bg-opacity-10 border-primary">
                                <div class="card-body text-center">
                                    <h1 class="display-5 text-primary" id="studentCount">0</h1>
                                    <h6>Students</h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card bg-info bg-opacity-10 border-info">
                                <div class="card-body text-center">
                                    <h1 class="display-5 text-info" id="teacherCount">0</h1>
                                    <h6>Teachers</h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card bg-success bg-opacity-10 border-success">
                                <div class="card-body text-center">
                                    <h1 class="display-5 text-success" id="activeTeachers">0</h1>
                                    <h6>Active Staff</h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card bg-warning bg-opacity-10 border-warning">
                                <div class="card-body text-center">
                                    <h1 class="display-5 text-warning" id="totalSalary">₹0</h1>
                                    <h6>Monthly Salary</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row justify-content-center">
                <div class="col-md-10">
                    <div class="card shadow">
                        <div class="card-body p-4">
                            <h4 class="mb-4"><i class="bi bi-lightning"></i> Quick Actions</h4>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <a href="{{ url('/students') }}" class="btn btn-primary action-btn w-100 py-4">
                                        <i class="bi bi-people display-6 mb-2"></i>
                                        <div>View Students</div>
                                        <small class="opacity-75">All student records</small>
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="{{ url('/teachers') }}" class="btn btn-info action-btn w-100 py-4">
                                        <i class="bi bi-person-badge display-6 mb-2"></i>
                                        <div>View Teachers</div>
                                        <small class="opacity-75">Staff management</small>
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="{{ url('/students/create') }}" class="btn btn-success action-btn w-100 py-4">
                                        <i class="bi bi-person-plus display-6 mb-2"></i>
                                        <div>Add Student</div>
                                        <small class="opacity-75">New admission</small>
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="{{ url('/teachers/create') }}" class="btn btn-warning action-btn w-100 py-4">
                                        <i class="bi bi-person-plus display-6 mb-2"></i>
                                        <div>Add Teacher</div>
                                        <small class="opacity-75">New staff member</small>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5"><i class="bi bi-stars"></i> Our Features</h2>
            <div class="row">
                <div class="col-md-3 mb-4">
                    <div class="card h-100 text-center border-0 shadow-sm">
                        <div class="card-body">
                            <div class="feature-icon text-primary">📚</div>
                            <h4>Student Management</h4>
                            <p>Complete student records with Aadhar verification, family details, and academic history.</p>
                            <a href="{{ url('/students') }}" class="btn btn-outline-primary btn-sm">Explore →</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card h-100 text-center border-0 shadow-sm">
                        <div class="card-body">
                            <div class="feature-icon text-info">👨‍🏫</div>
                            <h4>Teacher Management</h4>
                            <p>Complete staff records, salary management, experience tracking, and document management.</p>
                            <a href="{{ url('/teachers') }}" class="btn btn-outline-info btn-sm">Explore →</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card h-100 text-center border-0 shadow-sm">
                        <div class="card-body">
                            <div class="feature-icon text-success">📊</div>
                            <h4>Attendance Tracking</h4>
                            <p>Daily student & staff attendance with biometric integration and detailed analytics reports.</p>
                            <button class="btn btn-outline-success btn-sm" disabled>Coming Soon</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card h-100 text-center border-0 shadow-sm">
                        <div class="card-body">
                            <div class="feature-icon text-danger">💰</div>
                            <h4>Fee Management</h4>
                            <p>Fee structure management, payment tracking, receipts generation, and financial reports.</p>
                            <button class="btn btn-outline-danger btn-sm" disabled>Coming Soon</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Progress Section -->
    <section id="progress" class="py-5">
        <div class="container">
            <h2 class="text-center mb-5"><i class="bi bi-graph-up"></i> Project Progress</h2>
            
            <!-- Progress Bars -->
            <div class="row mb-5">
                <div class="col-md-6 mb-4">
                    <h5>📋 Students Module</h5>
                    <div class="progress progress-bar-custom mb-2">
                        <div class="progress-bar bg-success" style="width: 90%">90% Complete</div>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between">
                            <span>✅ Student CRUD Operations</span>
                            <i class="bi bi-check-circle-fill text-success"></i>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>✅ Aadhar Validation</span>
                            <i class="bi bi-check-circle-fill text-success"></i>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>✅ Search & Filter</span>
                            <i class="bi bi-check-circle-fill text-success"></i>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>✅ CSV Import/Export</span>
                            <i class="bi bi-check-circle-fill text-success"></i>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>🔲 Bulk Operations</span>
                            <i class="bi bi-circle text-secondary"></i>
                        </li>
                    </ul>
                </div>
                
                <div class="col-md-6 mb-4">
                    <h5>👨‍🏫 Teachers Module</h5>
                    <div class="progress progress-bar-custom mb-2">
                        <div class="progress-bar bg-info" style="width: 85%">85% Complete</div>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between">
                            <span>✅ Teacher CRUD Operations</span>
                            <i class="bi bi-check-circle-fill text-success"></i>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>✅ Profile Image Upload</span>
                            <i class="bi bi-check-circle-fill text-success"></i>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>✅ Salary Management</span>
                            <i class="bi bi-check-circle-fill text-success"></i>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>🔲 Experience Calculator</span>
                            <i class="bi bi-circle text-secondary"></i>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Next Features -->
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-kanban"></i> Upcoming Features Timeline</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card border-primary">
                                <div class="card-body">
                                    <h6 class="text-primary">📅 Week 5-6</h6>
                                    <ul class="mb-0">
                                        <li>Attendance System</li>
                                        <li>Bell Timing Management</li>
                                        <li>Exam Paper Upload</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card border-info">
                                <div class="card-body">
                                    <h6 class="text-info">📅 Week 7-8</h6>
                                    <ul class="mb-0">
                                        <li>Fee Management</li>
                                        <li>Result Generation</li>
                                        <li>Admit Card System</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card border-success">
                                <div class="card-body">
                                    <h6 class="text-success">📅 Week 9-10</h6>
                                    <ul class="mb-0">
                                        <li>Teacher Substitution</li>
                                        <li>School Inventory</li>
                                        <li>Budget Tracking</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5>🫱 HelpingHand</h5>
                    <p>School Management System</p>
                    <p class="mb-0">Version 2.0</p>
                </div>
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="{{ url('/students') }}" class="text-white-50 text-decoration-none">Students</a></li>
                        <li><a href="{{ url('/teachers') }}" class="text-white-50 text-decoration-none">Teachers</a></li>
                        <li><a href="#features" class="text-white-50 text-decoration-none">Features</a></li>
                        <li><a href="#progress" class="text-white-50 text-decoration-none">Progress</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Technology Stack</h5>
                    <p class="mb-1"><small>Laravel 10 • Bootstrap 5 • MySQL</small></p>
                    <p class="mb-0"><small>Built with ❤️ for education</small></p>
                </div>
            </div>
            <hr class="bg-light my-3">
            <div class="text-center">
                <p class="mb-0">© 2024 HelpingHand School Management System</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Live statistics update - SAFE VERSION
    document.addEventListener('DOMContentLoaded', function() {
        // Simulate live data (in real app, fetch from API)
        setTimeout(() => {
            try {
                // Students count - always works
                document.getElementById('studentCount').textContent = '{{ \App\Models\Student::count() }}';
                
                // Teachers count - use withoutGlobalScopes to avoid SoftDeletes error
                document.getElementById('teacherCount').textContent = '{{ \App\Models\Teacher::withoutGlobalScopes()->count() }}';
                
                // Active teachers - check if status column exists first
                @php
                    // Check if status column exists
                    $hasStatusColumn = \Schema::hasColumn('teachers', 'status');
                @endphp
                
                @if($hasStatusColumn)
                    document.getElementById('activeTeachers').textContent = '{{ \App\Models\Teacher::withoutGlobalScopes()->where("status", "active")->count() }}';
                @else
                    document.getElementById('activeTeachers').textContent = '{{ \App\Models\Teacher::withoutGlobalScopes()->count() }}';
                @endif
                
                // Format salary with commas
                @if(\Schema::hasColumn('teachers', 'salary'))
                    const salary = {{ \App\Models\Teacher::withoutGlobalScopes()->sum('salary') }};
                    document.getElementById('totalSalary').textContent = '₹' + salary.toLocaleString('en-IN');
                @else
                    document.getElementById('totalSalary').textContent = '₹0';
                @endif
                
            } catch(error) {
                console.error('Error loading statistics:', error);
                // Set safe default values
                document.getElementById('studentCount').textContent = '0';
                document.getElementById('teacherCount').textContent = '0';
                document.getElementById('activeTeachers').textContent = '0';
                document.getElementById('totalSalary').textContent = '₹0';
            }
        }, 500);

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });

        // Animate stats cards on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate__animated', 'animate__fadeInUp');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.stat-card').forEach(card => {
            observer.observe(card);
        });
    });
</script>
</body>
</html>