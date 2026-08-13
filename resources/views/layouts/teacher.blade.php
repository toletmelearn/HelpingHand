<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Teacher Panel - HelpingHand ERP')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .teacher-header {
            background-color: #007bff;
            color: white;
            padding: 1rem 0;
        }
        .teacher-sidebar {
            background-color: #343a40;
            min-height: 100vh;
        }
        .teacher-content {
            padding: 20px;
        }
        .nav-link {
            color: rgba(255,255,255,0.8);
        }
        .nav-link:hover, .nav-link.active {
            color: white;
            background-color: rgba(255,255,255,0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Teacher Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block teacher-sidebar">
                <div class="position-sticky pt-3">
                    <div class="px-3 mb-4 text-white text-center">
                        @if($logo = \App\Models\AdminConfiguration::get('general', 'school_logo'))
                            <img src="{{ asset('storage/' . $logo) }}" alt="Logo" class="img-fluid rounded mb-2 bg-white p-1" style="max-height: 50px; max-width: 120px; object-fit: contain;">
                        @else
                            <i class="fas fa-graduation-cap fa-2x mb-2 text-primary"></i>
                        @endif
                        <h6 class="mb-0 text-white-50" style="font-size: 13px;">{{ \App\Models\AdminConfiguration::get('general', 'school_name', 'HelpingHand ERP') }}</h6>
                        <small class="text-muted" style="font-size: 10px;">Teacher Portal</small>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('teacher.dashboard') ? 'active' : '' }}" 
                               href="{{ route('teacher.dashboard') }}">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('teacher.timetable') ? 'active' : '' }}"
                               href="{{ route('teacher.timetable') }}">
                                <i class="fas fa-calendar-week me-2"></i>My Timetable
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('teacher.classes.*') ? 'active' : '' }}"
                               href="{{ route('teacher.classes') }}">
                                <i class="fas fa-users me-2"></i>My Classes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('teacher.exams.*') ? 'active' : '' }}" 
                               href="{{ route('teacher.exams.index') }}">
                                <i class="fas fa-file-alt me-2"></i>My Exams
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('teacher.marks.*') ? 'active' : '' }}" 
                               href="{{ route('teacher.marks.index') }}">
                                <i class="fas fa-chart-line me-2"></i>Marks Entry
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('teacher.homework.*') ? 'active' : '' }}" 
                               href="{{ route('teacher.homework.index') }}">
                                <i class="fas fa-book me-2"></i>Homework
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('teacher.lesson-plans.*') ? 'active' : '' }}" 
                               href="{{ route('teacher.lesson-plans.index') }}">
                                <i class="fas fa-calendar me-2"></i>Lesson Plans
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('teacher.exam-papers.*') ? 'active' : '' }}" 
                               href="{{ route('teacher.exam-papers.index') }}">
                                <i class="fas fa-file me-2"></i>Exam Papers
                            </a>
                        </li>
                        @if(Route::has('teacher.leaves.index'))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('teacher.leaves.*') ? 'active' : '' }}" 
                               href="{{ route('teacher.leaves.index') }}">
                                <i class="fas fa-calendar-alt me-2"></i>My Leaves
                            </a>
                        </li>
                        @endif
                        @if(Route::has('teacher.salaries.index'))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('teacher.salaries.*') ? 'active' : '' }}" 
                               href="{{ route('teacher.salaries.index') }}">
                                <i class="fas fa-wallet me-2"></i>My Salaries
                            </a>
                        </li>
                        @endif
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('teacher.profile') ? 'active' : '' }}" href="{{ route('teacher.profile') }}">
                                <i class="fas fa-user me-2"></i>Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <form method="POST" action="{{ route('teacher.logout') }}">
                                @csrf
                                <button type="submit" class="nav-link btn btn-link text-start w-100">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 teacher-content">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @yield('scripts')
</body>
</html>