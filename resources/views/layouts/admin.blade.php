<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'HelpingHand ERP')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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

        /* Admin-template utility classes used across many admin views
           (avatars, soft badges, page headers) — not part of Bootstrap 5,
           defined here once so every page that uses them renders correctly
           instead of falling back to unstyled/oversized default markup. */
        .avatar-xs { height: 1.5rem; width: 1.5rem; }
        .avatar-sm { height: 3rem; width: 3rem; }
        .avatar-md { height: 4.5rem; width: 4.5rem; }
        .avatar-lg { height: 6rem; width: 6rem; }
        .avatar-xl { height: 7.5rem; width: 7.5rem; }
        .avatar-title {
            align-items: center;
            background-color: var(--bs-primary);
            border-radius: 50%;
            color: #fff;
            display: flex;
            font-weight: 600;
            height: 100%;
            justify-content: center;
            width: 100%;
        }

        .bg-soft-primary { background-color: rgba(var(--bs-primary-rgb), 0.15) !important; }
        .bg-soft-secondary { background-color: rgba(var(--bs-secondary-rgb), 0.15) !important; }
        .bg-soft-success { background-color: rgba(var(--bs-success-rgb), 0.15) !important; }
        .bg-soft-danger { background-color: rgba(var(--bs-danger-rgb), 0.15) !important; }
        .bg-soft-warning { background-color: rgba(var(--bs-warning-rgb), 0.15) !important; }
        .bg-soft-info { background-color: rgba(var(--bs-info-rgb), 0.15) !important; }
        .bg-soft-light { background-color: rgba(var(--bs-light-rgb), 0.15) !important; }
        .bg-soft-dark { background-color: rgba(var(--bs-dark-rgb), 0.15) !important; }

        .font-size-11 { font-size: 11px !important; }
        .font-size-12 { font-size: 12px !important; }
        .font-size-13 { font-size: 13px !important; }
        .font-size-15 { font-size: 15px !important; }
        .font-size-18 { font-size: 18px !important; }
        .font-size-20 { font-size: 20px !important; }

        .page-title-box { padding-bottom: 1rem; }
        .page-title-box .breadcrumb { background-color: transparent; padding: 0; }

        .table-centered td, .table-centered th { vertical-align: middle; }
        .table-nowrap td, .table-nowrap th { white-space: nowrap; }
    </style>
</head>
<body>

<!-- Fixed Top Navbar -->
<nav class="admin-navbar navbar navbar-dark">
    <div class="container-fluid">
        <span class="navbar-brand">
            @if(Route::has('admin.dashboard'))
                <a href="{{ route('admin.dashboard') }}" class="text-white text-decoration-none d-flex align-items-center">
                    @if($logo = \App\Models\AdminConfiguration::get('general', 'school_logo'))
                        <img src="{{ asset('storage/' . $logo) }}" alt="Logo" class="me-2 rounded bg-white p-1" style="height: 30px; max-width: 120px; object-fit: contain;">
                    @else
                        <i class="bi bi-mortarboard-fill me-2"></i>
                    @endif
                    {{ \App\Models\AdminConfiguration::get('general', 'school_name', 'HelpingHand ERP') }}
                </a>
            @else
                <span class="d-flex align-items-center">
                    @if($logo = \App\Models\AdminConfiguration::get('general', 'school_logo'))
                        <img src="{{ asset('storage/' . $logo) }}" alt="Logo" class="me-2 rounded bg-white p-1" style="height: 30px; max-width: 120px; object-fit: contain;">
                    @else
                        <i class="bi bi-mortarboard-fill me-2"></i>
                    @endif
                    {{ \App\Models\AdminConfiguration::get('general', 'school_name', 'HelpingHand ERP') }}
                </span>
            @endif
        </span>

        <ul class="navbar-nav ms-auto flex-row align-items-center">
            @auth
            <!-- Notifications -->
            @php
                try {
                    $unreadNotifications = Auth::user()->unreadNotifications;
                } catch (\Throwable $e) {
                    $unreadNotifications = collect();
                }
            @endphp
            <li class="nav-item dropdown me-3">
                <a class="nav-link text-white position-relative" href="#" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                    <i class="bi bi-bell fs-5"></i>
                    @if($unreadNotifications->count() > 0)
                        <span class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle" style="font-size: 0.6rem;">{{ $unreadNotifications->count() > 9 ? '9+' : $unreadNotifications->count() }}</span>
                    @endif
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow" style="width: 340px; max-height: 400px; overflow-y: auto;" aria-labelledby="notificationsDropdown">
                    @forelse($unreadNotifications->take(8) as $notification)
                        <li>
                            <div class="dropdown-item-text small border-bottom py-2">
                                <div class="fw-bold">{{ $notification->data['title'] ?? 'Notification' }}</div>
                                <div class="text-muted">{{ $notification->data['message'] ?? '' }}</div>
                                <div class="text-muted" style="font-size: 0.7rem;">{{ $notification->created_at->diffForHumans() }}</div>
                            </div>
                        </li>
                    @empty
                        <li><span class="dropdown-item-text small text-muted">No new notifications</span></li>
                    @endforelse
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-center small" href="{{ route('notifications.index') }}">View all notifications</a></li>
                </ul>
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
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(isset($errors) && $errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @yield('content')
</main>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@yield('scripts')
</body>
</html>