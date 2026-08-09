<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard - HelpingHand School ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-graduation-cap me-2"></i>HelpingHand School ERP
            </a>
            <div class="navbar-nav ms-auto align-items-center" style="display: flex; flex-direction: row; align-items: center;">
                @if(isset($students) && $students->count() > 1)
                    <form action="" method="POST" id="student_switch_form" class="me-3 mb-0">
                        @csrf
                        <select class="form-select form-select-sm" onchange="this.form.action='{{ url('/parent/switch-student') }}/' + this.value; this.form.submit();">
                            @foreach($students as $s)
                                <option value="{{ $s->id }}" {{ $s->id === $student->id ? 'selected' : '' }}>
                                    Child: {{ $s->name }} ({{ $s->class }})
                                </option>
                            @endforeach
                        </select>
                    </form>
                @endif
                <span class="navbar-text me-3">
                    <i class="fas fa-user me-1"></i>Welcome, {{ $student->name ?? 'Parent' }}
                </span>
                <a class="btn btn-outline-light btn-sm" href="{{ route('parent.logout') }}">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <h2>
                    <i class="fas fa-user-graduate me-2"></i>
                    Parent Dashboard
                </h2>
                <hr>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-child me-2"></i>
                            Student Information
                        </h5>
                    </div>
                    <div class="card-body">
                        @if($student)
                        <div class="text-center mb-3">
                            <img src="{{ $student->photo_url }}" alt="{{ $student->name }}"
                                 class="rounded-circle" width="80" height="80" style="object-fit: cover;">
                        </div>
                        @endif
                        <p><strong>Student Name:</strong> {{ $student->name ?? 'N/A' }}</p>
                        <p><strong>Admission Number:</strong> {{ $student->admission_no ?? 'N/A' }}</p>
                        <p><strong>Class:</strong> {{ $student->class ?? 'N/A' }}</p>
                        <p><strong>Mobile:</strong> {{ $student->mobile ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-money-bill-wave me-2"></i>
                            Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <a href="{{ route('parent.payments.pay-fees') }}" class="btn btn-success mb-2 w-100">
                            <i class="fas fa-qrcode me-2"></i>Pay Fees
                        </a>
                        <a href="{{ route('parent.timetable.today') }}" class="btn btn-outline-primary mb-2 w-100">
                            <i class="fas fa-calendar-alt me-2"></i>View Timetable
                        </a>
                        <a href="{{ route('parent.lesson-plans.index') }}" class="btn btn-info mb-2 w-100">
                            <i class="fas fa-book me-2"></i>View Lesson Plans
                        </a>
                        <a href="{{ route('parent.homework.index') }}" class="btn btn-primary mb-2 w-100">
                            <i class="fas fa-tasks me-2"></i>View Homework
                        </a>
                        <a href="{{ route('parent.payment.history') }}" class="btn btn-outline-success mb-2 w-100">
                            <i class="fas fa-receipt me-2"></i>View Fee Receipts
                        </a>
                        <a href="{{ route('parent.payment.history') }}" class="btn btn-primary mb-2 w-100">
                            <i class="fas fa-history me-2"></i>Payment History
                        </a>
                        <a href="{{ route('parent.fee.structure') }}" class="btn btn-warning w-100">
                            <i class="fas fa-file-invoice me-2"></i>Fee Structure
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>
                            Academic Overview
                        </h5>
                    </div>
                    <div class="card-body">
                        <p>Attendance: <span class="badge bg-primary">85%</span></p>
                        <p>Current Term: <span class="badge bg-success">Term 2</span></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-file-invoice-dollar me-2"></i>
                            Fee Overview
                        </h5>
                    </div>
                    <div class="card-body">
                        <p>Total Assigned: <span class="badge bg-info">₹{{ number_format($totalYearlyFee, 2) }}</span></p>
                        <p>Total Paid: <span class="badge bg-success">₹{{ number_format($totalPaid, 2) }}</span></p>
                        <p>Next Fee Due (Pending): <span class="badge bg-danger">₹{{ number_format($pendingAmount, 2) }}</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>