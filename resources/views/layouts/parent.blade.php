<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Parent Dashboard - HelpingHand School ERP')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="{{ route('parent.dashboard') }}">
                @if($logo = \App\Models\AdminConfiguration::get('general', 'school_logo'))
                    <img src="{{ asset('storage/' . $logo) }}" alt="Logo" class="me-2 rounded bg-white p-1" style="height: 30px; max-width: 120px; object-fit: contain;">
                @else
                    <i class="fas fa-graduation-cap me-2"></i>
                @endif
                {{ \App\Models\AdminConfiguration::get('general', 'school_name', 'HelpingHand School ERP') }} - Parent Portal
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-user me-1"></i>Welcome, {{ Auth::guard('parent')->user()->name ?? 'Parent' }}
                </span>
                <a class="btn btn-outline-light btn-sm" href="{{ route('parent.logout') }}">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Parent Sidebar -->
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Navigation</h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item {{ request()->routeIs('parent.dashboard') ? 'active' : '' }}">
                                <a href="{{ route('parent.dashboard') }}" class="text-decoration-none">
                                    <i class="fas fa-home me-2"></i>Dashboard
                                </a>
                            </li>
                            <li class="list-group-item {{ request()->routeIs('parent.lesson-plans.*') ? 'active' : '' }}">
                                <a href="{{ route('parent.lesson-plans.index') }}" class="text-decoration-none">
                                    <i class="fas fa-book me-2"></i>Lesson Plans
                                </a>
                            </li>
                            <li class="list-group-item {{ request()->routeIs('parent.homework.*') ? 'active' : '' }}">
                                <a href="{{ route('parent.homework.index') }}" class="text-decoration-none">
                                    <i class="fas fa-tasks me-2"></i>Homework
                                </a>
                            </li>
                            <li class="list-group-item {{ request()->routeIs('parent.payments.pay-fees') ? 'active' : '' }}">
                                <a href="{{ route('parent.payments.pay-fees') }}" class="text-decoration-none">
                                    <i class="fas fa-qrcode me-2"></i>Pay Fees
                                </a>
                            </li>
                            <li class="list-group-item {{ request()->routeIs('parent.payment.history') ? 'active' : '' }}">
                                <a href="{{ route('parent.payment.history') }}" class="text-decoration-none">
                                    <i class="fas fa-receipt me-2"></i>Payment History
                                </a>
                            </li>
                            <li class="list-group-item {{ request()->routeIs('parent.fee.structure') ? 'active' : '' }}">
                                <a href="{{ route('parent.fee.structure') }}" class="text-decoration-none">
                                    <i class="fas fa-file-invoice me-2"></i>Fee Structure
                                </a>
                            </li>
                            <li class="list-group-item {{ request()->routeIs('parent.exam-papers.*') ? 'active' : '' }}">
                                <a href="{{ route('parent.exam-papers.index') }}" class="text-decoration-none">
                                    <i class="fas fa-file me-2"></i>Exam Papers
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