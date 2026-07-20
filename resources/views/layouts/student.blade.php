<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Student Portal - HelpingHand School ERP')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="{{ route('student.dashboard') }}">
                @if($logo = \App\Models\AdminConfiguration::get('general', 'school_logo'))
                    <img src="{{ asset('storage/' . $logo) }}" alt="Logo" class="me-2 rounded bg-white p-1" style="height: 30px; max-width: 120px; object-fit: contain;">
                @else
                    <i class="fas fa-graduation-cap me-2"></i>
                @endif
                {{ \App\Models\AdminConfiguration::get('general', 'school_name', 'HelpingHand School ERP') }} - Student Portal
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-user me-1"></i>Welcome, {{ Auth::user()->name ?? 'Student' }}
                </span>
                <a class="btn btn-outline-light btn-sm" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                    @csrf
                </form>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Student Sidebar -->
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Navigation</h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item {{ request()->routeIs('student.dashboard') ? 'active' : '' }}">
                                <a href="{{ route('student.dashboard') }}" class="text-decoration-none">
                                    <i class="fas fa-home me-2"></i>Dashboard
                                </a>
                            </li>
                            <li class="list-group-item {{ request()->routeIs('student.exam-papers.*') ? 'active' : '' }}">
                                <a href="{{ route('student.exam-papers.index') }}" class="text-decoration-none">
                                    <i class="fas fa-file me-2"></i>Exam Papers
                                </a>
                            </li>
                            <li class="list-group-item {{ request()->routeIs('student.results.*') ? 'active' : '' }}">
                                <a href="{{ route('student.results.index') }}" class="text-decoration-none">
                                    <i class="fas fa-chart-line me-2"></i>Results
                                </a>
                            </li>
                            <li class="list-group-item {{ request()->routeIs('student.admit-cards.*') ? 'active' : '' }}">
                                <a href="{{ route('student.admit-cards.index') }}" class="text-decoration-none">
                                    <i class="fas fa-id-card me-2"></i>Admit Cards
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Main Content Area -->
            <div class="col-md-9">
                @yield('content')
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>