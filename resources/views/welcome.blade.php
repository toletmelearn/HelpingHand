@extends('layouts.public')

@section('title', ($schoolName ?? \App\Models\AdminConfiguration::get('general', 'school_name', 'HelpingHand')) . ' — Admissions Open')

@push('styles')
<style>
    :root {
        --brand-1: #4e73df;
        --brand-2: #224abe;
        --brand-3: #667eea;
        --brand-4: #764ba2;
    }

    html { scroll-behavior: smooth; }

    /* ---------- HERO ---------- */
    .hero {
        position: relative;
        min-height: 100vh;
        display: flex;
        align-items: center;
        overflow: hidden;
        background: linear-gradient(135deg, #0f0c29 0%, #302b63 45%, #24243e 100%);
        color: #fff;
    }
    .hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,.08) 1px, transparent 0);
        background-size: 26px 26px;
        opacity: .5;
    }
    .hero-orb {
        position: absolute;
        border-radius: 50%;
        filter: blur(70px);
        opacity: .55;
        animation: float 12s ease-in-out infinite;
        pointer-events: none;
    }
    .hero-orb.o1 { width: 420px; height: 420px; top: -120px; left: -100px; background: radial-gradient(circle, var(--brand-3), transparent 70%); animation-delay: 0s; }
    .hero-orb.o2 { width: 380px; height: 380px; bottom: -140px; right: -80px; background: radial-gradient(circle, var(--brand-4), transparent 70%); animation-delay: 2s; }
    .hero-orb.o3 { width: 300px; height: 300px; top: 30%; right: 15%; background: radial-gradient(circle, #43e97b, transparent 70%); animation-delay: 4s; opacity: .3; }

    @keyframes float {
        0%, 100% { transform: translate(0, 0) scale(1); }
        50% { transform: translate(20px, -30px) scale(1.08); }
    }

    .hero-content { position: relative; z-index: 2; }

    .badge-pill {
        background: rgba(255,255,255,.1);
        border: 1px solid rgba(255,255,255,.25);
        backdrop-filter: blur(6px);
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        padding: .5rem 1.1rem;
        border-radius: 50px;
        font-size: .85rem;
        font-weight: 600;
        letter-spacing: .02em;
    }
    .badge-pill .dot {
        width: 8px; height: 8px; border-radius: 50%;
        background: #43e97b;
        box-shadow: 0 0 0 0 rgba(67,233,123,.7);
        animation: pulse-dot 2s infinite;
    }
    @keyframes pulse-dot {
        0% { box-shadow: 0 0 0 0 rgba(67,233,123,.6); }
        70% { box-shadow: 0 0 0 10px rgba(67,233,123,0); }
        100% { box-shadow: 0 0 0 0 rgba(67,233,123,0); }
    }

    .hero h1 {
        font-weight: 800;
        font-size: clamp(2.2rem, 5vw, 4rem);
        line-height: 1.08;
        letter-spacing: -.02em;
    }
    .hero h1 .accent {
        background: linear-gradient(90deg, #43e97b, #38f9d7, #4facfe);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
    .hero .lead { color: rgba(255,255,255,.78); font-size: clamp(1.05rem, 2vw, 1.3rem); }

    .btn-hero-primary {
        background: linear-gradient(135deg, #43e97b, #38f9d7);
        border: none;
        color: #06301a;
        font-weight: 700;
        padding: .9rem 2rem;
        border-radius: 50px;
        box-shadow: 0 10px 30px rgba(67, 233, 123, .35);
        transition: transform .25s ease, box-shadow .25s ease;
    }
    .btn-hero-primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 16px 40px rgba(67, 233, 123, .45);
        color: #06301a;
    }
    .btn-hero-outline {
        border: 2px solid rgba(255,255,255,.4);
        color: #fff;
        font-weight: 600;
        padding: .85rem 1.9rem;
        border-radius: 50px;
        background: rgba(255,255,255,.05);
        transition: all .25s ease;
    }
    .btn-hero-outline:hover {
        border-color: #fff;
        background: rgba(255,255,255,.12);
        color: #fff;
    }

    .scroll-cue {
        position: absolute;
        bottom: 28px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 2;
        color: rgba(255,255,255,.6);
        animation: bounce 2s infinite;
        font-size: 1.4rem;
    }
    @keyframes bounce {
        0%, 100% { transform: translate(-50%, 0); }
        50% { transform: translate(-50%, 10px); }
    }

    /* ---------- FLOATING STAT STRIP ---------- */
    .stat-strip {
        position: relative;
        z-index: 3;
        margin-top: -70px;
    }
    .stat-strip .card {
        border: none;
        border-radius: 20px;
        box-shadow: 0 25px 60px rgba(20, 20, 50, .18);
    }
    .stat-item h3 {
        font-weight: 800;
        font-size: 2.1rem;
        background: linear-gradient(135deg, var(--brand-1), var(--brand-4));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        margin-bottom: .1rem;
    }
    .stat-item p { color: #6c757d; font-weight: 500; margin: 0; font-size: .9rem; }

    /* ---------- SECTION HEADERS ---------- */
    .section-eyebrow {
        text-transform: uppercase;
        letter-spacing: .12em;
        font-weight: 700;
        font-size: .78rem;
        color: var(--brand-1);
    }
    .section-title { font-weight: 800; letter-spacing: -.01em; }

    /* ---------- ADMISSIONS JOURNEY ---------- */
    .journey-section {
        background: linear-gradient(180deg, #f7f8fc 0%, #eef1fb 100%);
    }
    .journey-step {
        background: #fff;
        border-radius: 18px;
        padding: 2rem 1.5rem;
        height: 100%;
        border: 1px solid rgba(78,115,223,.08);
        transition: transform .3s ease, box-shadow .3s ease;
        position: relative;
    }
    .journey-step:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(30,40,90,.1); }
    .journey-step .step-num {
        position: absolute;
        top: -16px;
        left: 1.5rem;
        width: 36px; height: 36px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--brand-1), var(--brand-2));
        color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700;
        box-shadow: 0 8px 20px rgba(78,115,223,.4);
    }
    .journey-step .step-icon {
        width: 56px; height: 56px;
        border-radius: 16px;
        background: linear-gradient(135deg, rgba(78,115,223,.12), rgba(34,74,190,.12));
        display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem;
        color: var(--brand-1);
        margin-bottom: 1.1rem;
    }

    .admissions-cta-card {
        border-radius: 24px;
        background: linear-gradient(135deg, var(--brand-1) 0%, var(--brand-2) 100%);
        color: #fff;
        overflow: hidden;
        position: relative;
        padding: 3rem 2rem;
    }
    .admissions-cta-card::before {
        content: '';
        position: absolute;
        width: 260px; height: 260px;
        border-radius: 50%;
        background: rgba(255,255,255,.08);
        top: -100px; right: -60px;
    }
    .admissions-cta-card::after {
        content: '';
        position: absolute;
        width: 180px; height: 180px;
        border-radius: 50%;
        background: rgba(255,255,255,.06);
        bottom: -80px; left: -40px;
    }

    /* ---------- FEATURE CARDS ---------- */
    .feature-card {
        border-radius: 18px;
        border: 1px solid rgba(0,0,0,.05);
        padding: 2rem 1.6rem;
        height: 100%;
        background: #fff;
        transition: transform .3s ease, box-shadow .3s ease, border-color .3s ease;
    }
    .feature-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 22px 45px rgba(20,30,80,.1);
        border-color: transparent;
    }
    .feature-card .icon-wrap {
        width: 58px; height: 58px;
        border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1.2rem;
        color: #fff;
    }

    /* ---------- WHY US ---------- */
    .why-card {
        text-align: center;
        padding: 1rem;
    }
    .why-card .icon-circle {
        width: 70px; height: 70px;
        border-radius: 50%;
        margin: 0 auto 1.1rem;
        display: flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg, rgba(78,115,223,.1), rgba(118,75,162,.1));
        color: var(--brand-1);
        font-size: 1.7rem;
    }

    /* ---------- PORTALS ---------- */
    .portal-card {
        border-radius: 18px;
        border: 1px solid rgba(0,0,0,.06);
        padding: 2rem 1.5rem;
        text-align: center;
        height: 100%;
        transition: transform .3s ease, box-shadow .3s ease;
    }
    .portal-card:hover { transform: translateY(-6px); box-shadow: 0 20px 40px rgba(20,30,80,.1); }

    /* ---------- FINAL CTA ---------- */
    .final-cta {
        background: linear-gradient(135deg, #0f0c29 0%, #302b63 60%, #24243e 100%);
        color: #fff;
        border-radius: 28px;
        padding: 4rem 2rem;
        position: relative;
        overflow: hidden;
    }
    .final-cta .hero-orb.o4 { width: 320px; height: 320px; top: -120px; left: 10%; background: radial-gradient(circle, #4facfe, transparent 70%); }
    .final-cta .hero-orb.o5 { width: 280px; height: 280px; bottom: -120px; right: 10%; background: radial-gradient(circle, #43e97b, transparent 70%); }
</style>
@endpush

@section('content')

<!-- ============ HERO ============ -->
<section class="hero">
    <span class="hero-orb o1"></span>
    <span class="hero-orb o2"></span>
    <span class="hero-orb o3"></span>

    <div class="container hero-content py-5">
        <div class="row justify-content-center text-center">
            <div class="col-lg-9">
                <span class="badge-pill mb-4" data-aos="fade-down">
                    <span class="dot"></span> Admissions Open for {{ now()->year }}–{{ now()->addYear()->year }}
                </span>

                <h1 class="mb-4" data-aos="fade-up" data-aos-delay="100">
                    Give Your Child a<br>
                    <span class="accent">Head Start</span> at {{ \App\Models\AdminConfiguration::get('general', 'school_name', 'HelpingHand') }}
                </h1>

                <p class="lead mb-5 mx-auto" style="max-width: 640px;" data-aos="fade-up" data-aos-delay="200">
                    Apply for admission online in under two minutes — no login, no paperwork to start.
                    Our admissions team will personally guide your family through every step.
                </p>

                <div class="d-flex flex-column flex-sm-row justify-content-center gap-3" data-aos="fade-up" data-aos-delay="300">
                    <a href="{{ route('admissions.apply') }}" class="btn btn-hero-primary btn-lg">
                        <i class="bi bi-send-fill me-2"></i>Apply for Admission
                    </a>
                    <a href="#features" class="btn btn-hero-outline btn-lg">
                        <i class="bi bi-play-circle me-2"></i>Explore the Platform
                    </a>
                </div>
            </div>
        </div>
    </div>

    <a href="#stats" class="scroll-cue">
        <i class="bi bi-chevron-double-down"></i>
    </a>
</section>

<!-- ============ FLOATING STATS ============ -->
<section id="stats" class="stat-strip">
    <div class="container">
        <div class="card">
            <div class="card-body p-4 p-md-5">
                <div class="row text-center g-4">
                    <div class="col-6 col-md-3">
                        <div class="stat-item" data-aos="zoom-in">
                            <h3 class="counter" data-target="{{ $stats['students'] ?? 0 }}">0</h3>
                            <p>Students Enrolled</p>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-item" data-aos="zoom-in" data-aos-delay="100">
                            <h3 class="counter" data-target="{{ $stats['teachers'] ?? 0 }}">0</h3>
                            <p>Expert Teachers</p>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-item" data-aos="zoom-in" data-aos-delay="200">
                            <h3 class="counter" data-target="{{ $stats['applications_this_month'] ?? 0 }}">0</h3>
                            <p>New Applications This Month</p>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-item" data-aos="zoom-in" data-aos-delay="300">
                            <h3 class="counter" data-target="100">0</h3>
                            <p>% Satisfaction</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ ADMISSIONS JOURNEY ============ -->
<section class="journey-section py-5 mt-5">
    <div class="container py-5">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-eyebrow">How It Works</span>
            <h2 class="section-title display-6 mt-2">Your Admission Journey, Simplified</h2>
            <p class="lead text-muted mx-auto" style="max-width: 620px;">From your first click to your child's first day — here's exactly what happens.</p>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-6 col-lg-3">
                <div class="journey-step" data-aos="fade-up" data-aos-delay="0">
                    <span class="step-num">1</span>
                    <div class="step-icon"><i class="bi bi-pencil-square"></i></div>
                    <h5 class="fw-bold">Apply Online</h5>
                    <p class="text-muted mb-0 small">Fill a short form with your child's and your details. No account needed.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="journey-step" data-aos="fade-up" data-aos-delay="100">
                    <span class="step-num">2</span>
                    <div class="step-icon"><i class="bi bi-headset"></i></div>
                    <h5 class="fw-bold">We Reach Out</h5>
                    <p class="text-muted mb-0 small">Our admissions team contacts you to schedule a visit or interview.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="journey-step" data-aos="fade-up" data-aos-delay="200">
                    <span class="step-num">3</span>
                    <div class="step-icon"><i class="bi bi-clipboard-check"></i></div>
                    <h5 class="fw-bold">Confirm Your Seat</h5>
                    <p class="text-muted mb-0 small">Submit documents and complete admission formalities with our staff.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="journey-step" data-aos="fade-up" data-aos-delay="300">
                    <span class="step-num">4</span>
                    <div class="step-icon"><i class="bi bi-mortarboard"></i></div>
                    <h5 class="fw-bold">Welcome Aboard!</h5>
                    <p class="text-muted mb-0 small">Get instant access to our Parent Portal to track everything going forward.</p>
                </div>
            </div>
        </div>

        <div class="admissions-cta-card text-center" data-aos="zoom-in">
            <h3 class="fw-bold mb-2">Ready to begin?</h3>
            <p class="mb-4" style="color: rgba(255,255,255,.85);">Applications are reviewed on a rolling basis — the earlier you apply, the more seat availability your child has.</p>
            <a href="{{ route('admissions.apply') }}" class="btn btn-hero-primary btn-lg">
                <i class="bi bi-send-fill me-2"></i>Start Your Application
            </a>
        </div>
    </div>
</section>

<!-- ============ FEATURES ============ -->
<section id="features" class="py-5">
    <div class="container py-5">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-eyebrow">Platform</span>
            <h2 class="section-title display-6 mt-2">Everything Your School Needs</h2>
            <p class="lead text-muted mx-auto" style="max-width: 620px;">One connected system for admissions, academics, attendance, and communication.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="0">
                <div class="feature-card">
                    <div class="icon-wrap" style="background: linear-gradient(135deg, #667eea, #764ba2);"><i class="bi bi-people"></i></div>
                    <h5 class="fw-bold">Student Management</h5>
                    <p class="text-muted mb-0">Complete student records, academic progress, and class/section tracking in one place.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="100">
                <div class="feature-card">
                    <div class="icon-wrap" style="background: linear-gradient(135deg, #f093fb, #f5576c);"><i class="bi bi-person-badge"></i></div>
                    <h5 class="fw-bold">Teacher Management</h5>
                    <p class="text-muted mb-0">Profiles, qualifications, class assignments, leave, and salary — all managed centrally.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="200">
                <div class="feature-card">
                    <div class="icon-wrap" style="background: linear-gradient(135deg, #4facfe, #00f2fe);"><i class="bi bi-calendar-check"></i></div>
                    <h5 class="fw-bold">Attendance Tracking</h5>
                    <p class="text-muted mb-0">Daily attendance for students and staff, with instant reporting and analytics.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="0">
                <div class="feature-card">
                    <div class="icon-wrap" style="background: linear-gradient(135deg, #43e97b, #38f9d7);"><i class="bi bi-mortarboard-fill"></i></div>
                    <h5 class="fw-bold">Smart Admissions</h5>
                    <p class="text-muted mb-0">Public apply form, lead tracking, document verification, and seat-capacity control.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="100">
                <div class="feature-card">
                    <div class="icon-wrap" style="background: linear-gradient(135deg, #fa709a, #fee140);"><i class="bi bi-journal-text"></i></div>
                    <h5 class="fw-bold">Exams &amp; Results</h5>
                    <p class="text-muted mb-0">Exam paper management, grading, and report cards with secure access controls.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="200">
                <div class="feature-card">
                    <div class="icon-wrap" style="background: linear-gradient(135deg, #a8edea, #fed6e3); color: #333;"><i class="bi bi-shield-lock"></i></div>
                    <h5 class="fw-bold">Parent Portal</h5>
                    <p class="text-muted mb-0">Secure, role-based access to fees, attendance, and updates for every family.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ WHY US ============ -->
<section class="py-5" style="background: #f7f8fc;">
    <div class="container py-5">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-eyebrow">Why Families Trust Us</span>
            <h2 class="section-title display-6 mt-2">Built for Peace of Mind</h2>
        </div>

        <div class="row g-4">
            <div class="col-6 col-lg-3">
                <div class="why-card" data-aos="fade-up" data-aos-delay="0">
                    <div class="icon-circle"><i class="bi bi-clock-history"></i></div>
                    <h6 class="fw-bold">Apply Anytime</h6>
                    <p class="text-muted small mb-0">Our application form is always open — no office hours required.</p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="why-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="icon-circle"><i class="bi bi-clipboard-data"></i></div>
                    <h6 class="fw-bold">Full Transparency</h6>
                    <p class="text-muted small mb-0">Every step of your application is tracked and communicated to you.</p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="why-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="icon-circle"><i class="bi bi-people-fill"></i></div>
                    <h6 class="fw-bold">Fair Seat Allocation</h6>
                    <p class="text-muted small mb-0">Class capacity is tracked in real time so no section is ever over-booked.</p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="why-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="icon-circle"><i class="bi bi-phone"></i></div>
                    <h6 class="fw-bold">Instant Portal Access</h6>
                    <p class="text-muted small mb-0">The moment your child is admitted, you get secure Parent Portal access.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ PORTALS ============ -->
<section class="py-5">
    <div class="container py-5">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-eyebrow">Already With Us?</span>
            <h2 class="section-title display-6 mt-2">Login to Your Portal</h2>
        </div>

        <div class="row g-4 justify-content-center">
            <div class="col-md-4">
                <div class="portal-card" data-aos="fade-up" data-aos-delay="0">
                    <div class="icon-wrap mx-auto" style="background: linear-gradient(135deg, var(--brand-1), var(--brand-2));"><i class="bi bi-shield-lock"></i></div>
                    <h5 class="fw-bold mt-3">Admin / Staff</h5>
                    <p class="text-muted small">Email &amp; Password</p>
                    <a href="{{ route('login') }}" class="btn btn-outline-primary w-100">Login</a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="portal-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="icon-wrap mx-auto" style="background: linear-gradient(135deg, #43e97b, #38f9d7);"><i class="bi bi-person-badge"></i></div>
                    <h5 class="fw-bold mt-3">Teacher</h5>
                    <p class="text-muted small">Mobile Number / Employee ID</p>
                    <a href="{{ route('teacher.login') }}" class="btn btn-outline-success w-100">Login</a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="portal-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="icon-wrap mx-auto" style="background: linear-gradient(135deg, #4facfe, #00f2fe);"><i class="bi bi-people"></i></div>
                    <h5 class="fw-bold mt-3">Parent</h5>
                    <p class="text-muted small">Mobile / Admission Number</p>
                    <a href="{{ route('parent.login') }}" class="btn btn-outline-info w-100">Login</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ FINAL CTA ============ -->
<section class="py-5">
    <div class="container py-4">
        <div class="final-cta text-center" data-aos="zoom-in">
            <span class="hero-orb o4"></span>
            <span class="hero-orb o5"></span>
            <div class="position-relative">
                <h2 class="display-6 fw-bold mb-3">Your Child's Next Chapter Starts Here</h2>
                <p class="lead mb-4 mx-auto" style="max-width: 560px; color: rgba(255,255,255,.8);">
                    Join the families already choosing {{ \App\Models\AdminConfiguration::get('general', 'school_name', 'HelpingHand') }} for a modern, transparent admissions experience.
                </p>
                <a href="{{ route('admissions.apply') }}" class="btn btn-hero-primary btn-lg">
                    <i class="bi bi-send-fill me-2"></i>Apply for Admission Now
                </a>
            </div>
        </div>
    </div>
</section>

@endsection

@push('scripts')
<script>
    (function () {
        const counters = document.querySelectorAll('.counter');
        if (!counters.length) return;

        const animate = (el) => {
            const target = parseInt(el.getAttribute('data-target'), 10) || 0;
            const duration = 1400;
            const start = performance.now();

            function step(now) {
                const progress = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                el.textContent = Math.floor(eased * target).toLocaleString();
                if (progress < 1) {
                    requestAnimationFrame(step);
                } else {
                    el.textContent = target.toLocaleString();
                }
            }
            requestAnimationFrame(step);
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    animate(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.4 });

        counters.forEach((c) => observer.observe(c));
    })();
</script>
@endpush
