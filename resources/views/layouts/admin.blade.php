<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'HelpingHand ERP')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* ADMIN LAYOUT STYLES */
        body {
            overflow-x: hidden;
        }
        
        /* Fixed Top Navbar */
        .admin-navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background-color: #2c3e50;
            z-index: 1100;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Fixed Left Sidebar */
        .admin-sidebar {
            position: fixed;
            top: 60px;
            left: 0;
            width: 250px;
            height: calc(100vh - 60px);
            background-color: #212529;
            z-index: 1050;
            transition: all 0.3s ease;
            overflow-y: auto;
        }
        
        /* Main Content Area */
        .admin-main {
            margin-left: 250px;
            margin-top: 60px;
            padding: 20px;
            min-height: calc(100vh - 60px);
            transition: margin-left 0.3s ease;
        }
        
        /* Collapsed State */
        .admin-sidebar.collapsed {
            width: 70px;
        }
        
        .admin-main.sidebar-collapsed {
            margin-left: 70px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .admin-sidebar {
                width: 70px;
            }
            .admin-main {
                margin-left: 70px;
            }
        }
    </style>
</head>
<body>

<!-- Fixed Top Navbar -->
<nav class="admin-navbar navbar navbar-dark">
    <div class="container-fluid">
        <span class="navbar-brand">
            @if(Route::has('admin.dashboard'))
                <a href="{{ route('admin.dashboard') }}" class="text-white text-decoration-none">
                    <i class="bi bi-mortarboard-fill me-2"></i>HelpingHand ERP
                </a>
            @else
                <i class="bi bi-mortarboard-fill me-2"></i>HelpingHand ERP
            @endif
        </span>

        <ul class="navbar-nav ms-auto flex-row align-items-center">
            @auth
            <!-- Notifications (Optional) -->
            <li class="nav-item me-3">
                <a class="nav-link text-white" href="#" title="Notifications">
                    <i class="bi bi-bell fs-5"></i>
                    <span class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle" style="font-size: 0.6rem;">3</span>
                </a>
            </li>
            
            <!-- User Dropdown -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle text-white d-flex align-items-center" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle fs-4 me-1"></i>
                    <span class="d-none d-md-inline">{{ Auth::user()->name }}</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    @if(Route::has('users.show') && Auth::check())
                    <li><a class="dropdown-item" href="{{ route('users.show', Auth::id()) }}">
                        <i class="bi bi-person me-2"></i>Profile
                    </a></li>
                    @endif
                    @if(Route::has('profile.two-factor-authentication'))
                    <li><a class="dropdown-item" href="{{ route('profile.two-factor-authentication') }}">
                        <i class="bi bi-shield-lock me-2"></i>Security
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    @endif
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="dropdown-item" type="submit">
                                <i class="bi bi-box-arrow-right me-2"></i>Logout
                            </button>
                        </form>
                    </li>
                </ul>
            </li>
            @endauth
        </ul>
    </div>
</nav>

<!-- Fixed Left Sidebar -->
@include('layouts.sidebar')

<!-- Main Content Area -->
<main id="main-content" class="admin-main">
    @yield('content')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@yield('scripts')
</body>
</html>