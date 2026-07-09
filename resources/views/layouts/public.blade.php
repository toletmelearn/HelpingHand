<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'HelpingHand')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    @stack('styles')
    <style>
        #publicNavbar-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            transition: background-color .35s ease, box-shadow .35s ease, padding .35s ease;
            background: transparent;
        }
        #publicNavbar-wrapper.scrolled {
            background: rgba(20, 20, 35, 0.92);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0,0,0,.2);
        }
        #publicNavbar-wrapper .navbar { padding-top: .75rem; padding-bottom: .75rem; }
    </style>
</head>
<body>

<!-- ✅ PUBLIC NAVBAR -->
<div id="publicNavbar-wrapper">
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="{{ url('/') }}">
                @if($logo = \App\Models\AdminConfiguration::get('general', 'school_logo'))
                    <img src="{{ asset('storage/' . $logo) }}" alt="Logo" class="me-2 rounded bg-white p-1" style="height: 30px; max-width: 120px; object-fit: contain;">
                @else
                    <i class="bi bi-mortarboard-fill me-2"></i>
                @endif
                {{ \App\Models\AdminConfiguration::get('general', 'school_name', 'HelpingHand') }}
            </a>

            <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#publicNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="publicNavbar">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admissions.apply') }}">Admissions</a>
                    </li>

                    @guest
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">Login</a>
                        </li>
                        <li class="nav-item ms-lg-2">
                            <a class="btn btn-primary btn-sm px-3" href="{{ route('admissions.apply') }}">
                                <i class="bi bi-send me-1"></i>Apply Now
                            </a>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link" href="{{ url('/admin/dashboard') }}">
                                Dashboard
                            </a>
                        </li>
                    @endguest
                </ul>
            </div>
        </div>
    </nav>
</div>

<!-- PAGE CONTENT -->
@yield('content')

<!-- ✅ PUBLIC FOOTER -->
<footer class="py-5" style="background: #14141f; color: rgba(255,255,255,.7);">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <h5 class="text-white fw-bold mb-3 d-flex align-items-center">
                    <i class="bi bi-mortarboard-fill me-2"></i>
                    {{ \App\Models\AdminConfiguration::get('general', 'school_name', 'HelpingHand') }}
                </h5>
                <p class="small mb-0">A complete school management platform — admissions, attendance, fees, exams, and parent engagement, all in one place.</p>
            </div>
            <div class="col-md-4">
                <h6 class="text-white fw-bold mb-3">Quick Links</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="{{ route('admissions.apply') }}" class="text-decoration-none" style="color: rgba(255,255,255,.7);">Apply for Admission</a></li>
                    <li class="mb-2"><a href="{{ url('/#features') }}" class="text-decoration-none" style="color: rgba(255,255,255,.7);">Features</a></li>
                    <li class="mb-2"><a href="{{ route('login') }}" class="text-decoration-none" style="color: rgba(255,255,255,.7);">Admin Login</a></li>
                </ul>
            </div>
            <div class="col-md-4">
                <h6 class="text-white fw-bold mb-3">Portals</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="{{ route('teacher.login') }}" class="text-decoration-none" style="color: rgba(255,255,255,.7);">Teacher Login</a></li>
                    <li class="mb-2"><a href="{{ route('parent.login') }}" class="text-decoration-none" style="color: rgba(255,255,255,.7);">Parent Login</a></li>
                </ul>
            </div>
        </div>
        <hr style="border-color: rgba(255,255,255,.1);" class="my-4">
        <p class="small mb-0 text-center" style="color: rgba(255,255,255,.5);">&copy; {{ date('Y') }} {{ \App\Models\AdminConfiguration::get('general', 'school_name', 'HelpingHand') }}. All rights reserved.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({ duration: 700, once: true, offset: 80 });

    (function () {
        const wrapper = document.getElementById('publicNavbar-wrapper');
        const toggle = () => {
            if (window.scrollY > 40) {
                wrapper.classList.add('scrolled');
            } else {
                wrapper.classList.remove('scrolled');
            }
        };
        window.addEventListener('scroll', toggle);
        toggle();
    })();
</script>
@stack('scripts')
</body>
</html>