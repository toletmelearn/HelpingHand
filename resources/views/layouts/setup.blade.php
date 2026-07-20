@php
    $step = $step ?? 1;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'System Onboarding Wizard') - HelpingHand ERP</title>

    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Outfit Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-hsl: 245, 75%, 60%;
            --primary-light-hsl: 245, 75%, 95%;
            --dark-hsl: 224, 25%, 12%;
            --light-bg-hsl: 220, 33%, 97%;
            
            --primary-color: hsl(var(--primary-hsl));
            --primary-hover: hsl(245, 75%, 52%);
            --dark-color: hsl(var(--dark-hsl));
            --light-bg: hsl(var(--light-bg-hsl));
            
            font-family: 'Outfit', sans-serif;
        }

        body {
            background-color: var(--light-bg);
            background-image: 
                radial-gradient(at 0% 0%, hsla(245, 75%, 60%, 0.08) 0px, transparent 50%),
                radial-gradient(at 100% 100%, hsla(280, 80%, 50%, 0.08) 0px, transparent 50%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark-color);
        }

        .setup-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.06);
            width: 100%;
            max-width: 850px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .step-progress-container {
            background: #fafbfe;
            border-right: 1px solid rgba(0, 0, 0, 0.05);
            padding: 2.5rem 2rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        @media (max-width: 768px) {
            .setup-card {
                margin: 1rem;
            }
            .step-progress-container {
                border-right: none;
                border-bottom: 1px solid rgba(0, 0, 0, 0.05);
                padding: 1.5rem;
                flex-direction: row;
                overflow-x: auto;
            }
        }

        .step-item {
            display: flex;
            align-items: center;
            margin-bottom: 1.8rem;
            position: relative;
        }

        @media (max-width: 768px) {
            .step-item {
                margin-bottom: 0;
                margin-right: 1.5rem;
                flex-shrink: 0;
            }
        }

        .step-icon {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: #eef1f6;
            color: #7d8b9f;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.95rem;
            margin-right: 1rem;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .step-item.active .step-icon {
            background: var(--primary-color);
            color: #fff;
            box-shadow: 0 4px 10px rgba(var(--primary-hsl), 0.3);
        }

        .step-item.completed .step-icon {
            background: #e2f9ec;
            color: #1f9d55;
        }

        .step-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #7d8b9f;
            transition: all 0.3s ease;
        }

        .step-item.active .step-title {
            color: var(--primary-color);
            font-weight: 700;
        }

        .step-item.completed .step-title {
            color: #1f9d55;
        }

        .wizard-content {
            padding: 3rem;
        }

        @media (max-width: 768px) {
            .wizard-content {
                padding: 1.5rem;
            }
        }

        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 10px;
            padding: 0.6rem 1.8rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            border-color: var(--primary-hover);
            transform: translateY(-1px);
        }

        .form-control, .form-select {
            border-radius: 10px;
            padding: 0.65rem 0.9rem;
            border: 1px solid #d2dbe5;
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px hsla(var(--primary-hsl), 0.15);
        }

        /* Classes/sections selection grid styles */
        .class-grid-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1rem;
            transition: all 0.2s ease;
            background: #fff;
        }
        
        .class-grid-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 5px 15px rgba(0,0,0,0.02);
        }

        .class-checkbox:checked ~ .class-grid-card {
            border-color: var(--primary-color);
            background: hsl(var(--primary-hsl), 1.5%);
        }

        .section-badge-input {
            display: none;
        }

        .section-badge-label {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-right: 0.3rem;
            margin-bottom: 0.3rem;
            user-select: none;
        }

        .section-badge-input:checked + .section-badge-label {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: #fff;
        }
    </style>
</head>
<body>

    <div class="container py-5 d-flex justify-content-center">
        <div class="setup-card">
            <div class="row g-0">
                <!-- Step progress sidebar -->
                <div class="col-md-4 col-12 step-progress-container">
                    <div>
                        <div class="d-flex align-items-center mb-4 pb-2 px-1">
                            <span class="fs-4 fw-extrabold text-primary"><i class="bi bi-gear-wide-connected me-2"></i>Setup Wizard</span>
                        </div>
                        
                        <div class="step-item {{ $step == 1 ? 'active' : ($step > 1 ? 'completed' : '') }}">
                            <div class="step-icon">
                                @if($step > 1) <i class="bi bi-check-lg"></i> @else 1 @endif
                            </div>
                            <div>
                                <span class="d-block text-uppercase text-muted" style="font-size: 0.65rem; font-weight: 700; letter-spacing: 0.05em;">Step 1</span>
                                <span class="step-title">School Profile</span>
                            </div>
                        </div>

                        <div class="step-item {{ $step == 2 ? 'active' : ($step > 2 ? 'completed' : '') }}">
                            <div class="step-icon">
                                @if($step > 2) <i class="bi bi-check-lg"></i> @else 2 @endif
                            </div>
                            <div>
                                <span class="d-block text-uppercase text-muted" style="font-size: 0.65rem; font-weight: 700; letter-spacing: 0.05em;">Step 2</span>
                                <span class="step-title">Academic Session</span>
                            </div>
                        </div>

                        <div class="step-item {{ $step == 3 ? 'active' : ($step > 3 ? 'completed' : '') }}">
                            <div class="step-icon">
                                @if($step > 3) <i class="bi bi-check-lg"></i> @else 3 @endif
                            </div>
                            <div>
                                <span class="d-block text-uppercase text-muted" style="font-size: 0.65rem; font-weight: 700; letter-spacing: 0.05em;">Step 3</span>
                                <span class="step-title">Classes & Sections</span>
                            </div>
                        </div>

                        <div class="step-item {{ $step == 4 ? 'active' : ($step > 4 ? 'completed' : '') }}">
                            <div class="step-icon">
                                @if($step > 4) <i class="bi bi-check-lg"></i> @else 4 @endif
                            </div>
                            <div>
                                <span class="d-block text-uppercase text-muted" style="font-size: 0.65rem; font-weight: 700; letter-spacing: 0.05em;">Step 4</span>
                                <span class="step-title">Subjects Setup</span>
                            </div>
                        </div>

                        <div class="step-item {{ $step == 5 ? 'active' : '' }}">
                            <div class="step-icon">
                                5
                            </div>
                            <div>
                                <span class="d-block text-uppercase text-muted" style="font-size: 0.65rem; font-weight: 700; letter-spacing: 0.05em;">Step 5</span>
                                <span class="step-title">Finish Setup</span>
                            </div>
                        </div>
                    </div>

                    <div class="d-none d-md-block px-1">
                        <small class="text-muted" style="font-size: 0.75rem;">HelpingHand Onboarding Wizard &copy; {{ date('Y') }}</small>
                    </div>
                </div>

                <!-- Page content container -->
                <div class="col-md-8 col-12">
                    <div class="wizard-content">
                        @yield('content')
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @yield('scripts')
</body>
</html>
