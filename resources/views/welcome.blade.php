@extends('layouts.app')

@section('title', 'HelpingHand - School Management System')

@section('content')

<!-- Hero Section -->
<section class="hero-section text-center">
    <div class="container">
        <h1 class="display-4 mb-4">Welcome to HelpingHand</h1>
        <p class="lead mb-4">Your complete school management solution built with ❤️</p>
        
        <!-- Live Stats -->
        <div class="row justify-content-center mb-5">
            <div class="col-md-10">
                <div class="row g-4">
                    @isset($stats)
                    <div class="col-md-2">
                        <div class="card bg-primary text-white stat-card h-100">
                            <div class="card-body text-center">
                                <div class="feature-icon mb-3">
                                    <i class="bi bi-people"></i>
                                </div>
                                <h3 class="card-title">{{ $stats['students'] }}</h3>
                                <p class="card-text">Students</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-success text-white stat-card h-100">
                            <div class="card-body text-center">
                                <div class="feature-icon mb-3">
                                    <i class="bi bi-person-badge"></i>
                                </div>
                                <h3 class="card-title">{{ $stats['teachers'] }}</h3>
                                <p class="card-text">Teachers</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-info text-white stat-card h-100">
                            <div class="card-body text-center">
                                <div class="feature-icon mb-3">
                                    <i class="bi bi-calendar-check"></i>
                                </div>
                                <h3 class="card-title">{{ $stats['attendance'] }}</h3>
                                <p class="card-text">Attendance</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-warning text-white stat-card h-100">
                            <div class="card-body text-center">
                                <div class="feature-icon mb-3">
                                    <i class="bi bi-alarm"></i>
                                </div>
                                <h3 class="card-title">{{ $stats['bell_timing'] }}</h3>
                                <p class="card-text">Bell Timings</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-danger text-white stat-card h-100">
                            <div class="card-body text-center">
                                <div class="feature-icon mb-3">
                                    <i class="bi bi-journal-text"></i>
                                </div>
                                <h3 class="card-title">{{ $stats['exam_papers'] }}</h3>
                                <p class="card-text">Exam Papers</p>
                            </div>
                        </div>
                    </div>
                    @endisset
                    <div class="col-md-2">
                        <div class="card bg-secondary text-white stat-card h-100">
                            <div class="card-body text-center">
                                <div class="feature-icon mb-3">
                                    <i class="bi bi-emoji-smile"></i>
                                </div>
                                <h3 class="card-title">100%</h3>
                                <p class="card-text">Satisfaction</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        @guest
        <a href="{{ url('/students/create') }}" class="btn btn-light btn-lg">
            <i class="bi bi-rocket-takeoff me-2"></i>Get Started
        </a>
        @endguest
    </div>
</section>

<!-- Features Section -->
<section id="features" class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold">Amazing Features</h2>
            <p class="lead">Built with the latest technologies and best practices</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 text-center action-btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <div class="card-body">
                        <div class="feature-icon mx-auto">
                            <i class="bi bi-people"></i>
                        </div>
                        <h5 class="card-title">Student Management</h5>
                        <p class="card-text">Complete student information management with records, photos, and academic progress tracking.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 text-center action-btn" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                    <div class="card-body">
                        <div class="feature-icon mx-auto">
                            <i class="bi bi-person-badge"></i>
                        </div>
                        <h5 class="card-title">Teacher Management</h5>
                        <p class="card-text">Comprehensive teacher profiles with qualifications, experience, salary details, and assignments.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 text-center action-btn" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                    <div class="card-body">
                        <div class="feature-icon mx-auto">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <h5 class="card-title">Attendance Tracking</h5>
                        <p class="card-text">Daily attendance management for students and teachers with reporting and analytics.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 text-center action-btn" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                    <div class="card-body">
                        <div class="feature-icon mx-auto">
                            <i class="bi bi-alarm"></i>
                        </div>
                        <h5 class="card-title">Bell Timing System</h5>
                        <p class="card-text">Flexible bell scheduling system with different periods, breaks, and class transitions.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 text-center action-btn" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                    <div class="card-body">
                        <div class="feature-icon mx-auto">
                            <i class="bi bi-journal-text"></i>
                        </div>
                        <h5 class="card-title">Exam Paper Management</h5>
                        <p class="card-text">Digital exam paper upload, download, and management system with access controls.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 text-center action-btn" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333;">
                    <div class="card-body">
                        <div class="feature-icon mx-auto">
                            <i class="bi bi-speedometer2"></i>
                        </div>
                        <h5 class="card-title">Dashboard Analytics</h5>
                        <p class="card-text">Real-time statistics and analytics with customizable reports and visualizations.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Progress Section -->
<section id="progress" class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold">Project Progress</h2>
            <p class="lead text-muted">Continuous development and improvements</p>
        </div>
        
        <div class="row g-4">
            <div class="col-xl-3 col-md-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body text-center p-4">
                        <div class="position-relative d-inline-block mb-3">
                            <div class="circle-progress" style="width: 100px; height: 100px;">
                                <svg viewBox="0 0 36 36" class="circular-chart">
                                    <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                    <path class="circle" stroke="#28a745" stroke-width="3" stroke-dasharray="100, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" />
                                </svg>
                                <div class="chart-label position-absolute top-50 start-50 translate-middle">
                                    <span class="fw-bold">95%</span>
                                </div>
                            </div>
                        </div>
                        <h5 class="card-title mt-3">Overall Progress</h5>
                        <p class="text-muted small">Completed modules and features</p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body text-center p-4">
                        <div class="position-relative d-inline-block mb-3">
                            <div class="circle-progress" style="width: 100px; height: 100px;">
                                <svg viewBox="0 0 36 36" class="circular-chart">
                                    <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                    <path class="circle" stroke="#007bff" stroke-width="3" stroke-dasharray="100, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" />
                                </svg>
                                <div class="chart-label position-absolute top-50 start-50 translate-middle">
                                    <span class="fw-bold">100%</span>
                                </div>
                            </div>
                        </div>
                        <h5 class="card-title mt-3">Student Module</h5>
                        <p class="text-muted small">Complete with all features</p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body text-center p-4">
                        <div class="position-relative d-inline-block mb-3">
                            <div class="circle-progress" style="width: 100px; height: 100px;">
                                <svg viewBox="0 0 36 36" class="circular-chart">
                                    <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                    <path class="circle" stroke="#28a745" stroke-width="3" stroke-dasharray="100, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" />
                                </svg>
                                <div class="chart-label position-absolute top-50 start-50 translate-middle">
                                    <span class="fw-bold">100%</span>
                                </div>
                            </div>
                        </div>
                        <h5 class="card-title mt-3">Teacher Module</h5>
                        <p class="text-muted small">Fully operational</p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body text-center p-4">
                        <div class="position-relative d-inline-block mb-3">
                            <div class="circle-progress" style="width: 100px; height: 100px;">
                                <svg viewBox="0 0 36 36" class="circular-chart">
                                    <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                    <path class="circle" stroke="#ffc107" stroke-width="3" stroke-dasharray="90, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" />
                                </svg>
                                <div class="chart-label position-absolute top-50 start-50 translate-middle">
                                    <span class="fw-bold">90%</span>
                                </div>
                            </div>
                        </div>
                        <h5 class="card-title mt-3">Advanced Features</h5>
                        <p class="text-muted small">Ongoing enhancements</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Detailed Progress Bars -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-4">Module Development Status</h5>
                        
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Student Management</span>
                                    <span class="text-success fw-bold">100%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 100%"></div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Teacher Management</span>
                                    <span class="text-success fw-bold">100%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 100%"></div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Attendance System</span>
                                    <span class="text-success fw-bold">100%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 100%"></div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Exam Management</span>
                                    <span class="text-success fw-bold">100%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 100%"></div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Syllabus & Daily Teaching</span>
                                    <span class="text-success fw-bold">100%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 100%"></div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Admission Process</span>
                                    <span class="text-primary fw-bold">95%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: 95%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.circular-chart {
    width: 100%;
    height: 100%;
}

.circle-bg {
    fill: none;
    stroke: #eee;
    stroke-width: 3;
}

.circle {
    fill: none;
    stroke-width: 3;
    stroke-linecap: round;
    animation: progress 1s ease-out forwards;
}

@keyframes progress {
    0% {
        stroke-dasharray: 0 100;
    }
}

.chart-label {
    font-size: 0.5em;
    font-weight: bold;
    text-anchor: middle;
}

.progress-bar {
    transition: width 0.6s ease;
}
</style>

<!-- CTA Section -->
<section class="py-5 bg-primary text-white text-center">
    <div class="container">
        <h2 class="display-5 fw-bold mb-3">Ready to Transform Your School?</h2>
        <p class="lead mb-4">Join hundreds of schools already using HelpingHand to streamline their operations</p>
        <a href="{{ url('/students/create') }}" class="btn btn-light btn-lg">
            <i class="bi bi-rocket-takeoff me-2"></i>Start Free Trial
        </a>
    </div>
</section>
@endsection