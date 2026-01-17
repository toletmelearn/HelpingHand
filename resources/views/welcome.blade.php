<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🫱 HelpingHand - School Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #2c3e50;">
        <div class="container">
            <a class="navbar-brand" href="/">
                🫱 HelpingHand
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="/students">👨‍🎓 Students</a>
                <a class="nav-link" href="/students/create">➕ Add Student</a>
                <a class="nav-link" href="#features">✨ Features</a>
                <a class="nav-link btn btn-success ms-2" href="/students">Get Started</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section text-center">
        <div class="container">
            <h1 class="display-4 mb-4">Welcome to HelpingHand</h1>
            <p class="lead mb-4">Your complete school management solution built with ❤️</p>
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card shadow">
                        <div class="card-body p-4">
                            <h4 class="mb-3">🚀 Quick Actions</h4>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <a href="/students" class="btn btn-primary btn-lg w-100 py-3">
                                        👨‍🎓 View All Students
                                    </a>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <a href="/students/create" class="btn btn-success btn-lg w-100 py-3">
                                        ➕ Add New Student
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
            <h2 class="text-center mb-5">✨ Our Features</h2>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <div class="feature-icon">📚</div>
                            <h4>Student Management</h4>
                            <p>Complete student records with Aadhar verification and family details.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <div class="feature-icon">📊</div>
                            <h4>Attendance Tracking</h4>
                            <p>Daily attendance with reports and analytics (coming soon).</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <div class="feature-icon">💰</div>
                            <h4>Fee Management</h4>
                            <p>Fee structure, payment tracking and receipts (coming soon).</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Progress Section -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-4">📈 Your Project Progress</h2>
            <div class="card">
                <div class="card-body">
                    <div class="mb-3">
                        <h5>✅ Completed:</h5>
                        <ul>
                            <li>✅ Laravel Project Setup</li>
                            <li>✅ Database Migration for Students</li>
                            <li>✅ Student Model Created</li>
                            <li>✅ Add Student Form</li>
                            <li>✅ Students List View</li>
                        </ul>
                    </div>
                    <div>
                        <h5>⏳ Next Steps:</h5>
                        <ul>
                            <li>🔲 Edit Student Functionality</li>
                            <li>🔲 Delete Student Functionality</li>
                            <li>🔲 Search & Filter Students</li>
                            <li>🔲 Teachers Module</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container text-center">
            <p>🫱 HelpingHand School Management System</p>
            <p class="mb-0">Built with Laravel & Bootstrap | Version 1.0</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>