<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gate Pass - {{ $pass->holder_name }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .pass-card {
            width: 420px;
            margin: 40px auto;
            border: 2px solid #dee2e6;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .pass-header {
            background-color: #198754; /* Green header to indicate gate release pass */
            color: #fff;
            padding: 18px;
            text-align: center;
        }
        @media print {
            body {
                background-color: #fff;
                margin: 0;
            }
            .pass-card {
                border: 1px solid #000;
                box-shadow: none;
                margin: 0 auto;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print text-center my-4">
        <button onclick="window.print()" class="btn btn-success px-4"><i class="bi bi-printer me-1"></i> Print Pass</button>
        <a href="{{ route('admin.front-office.gate-passes.index') }}" class="btn btn-outline-secondary px-4 ms-2">Back to Registry</a>
    </div>

    <div class="pass-card">
        <div class="pass-header">
            <h5 class="fw-bold mb-0">HELPINGHAND SCHOOL ERP</h5>
            <small class="text-uppercase tracking-wider">Outbound Gate Pass</small>
        </div>
        <div class="p-4">
            <div class="text-center mb-4">
                @if($pass->pass_type === 'student' && $pass->student)
                    <img src="{{ $pass->student->photo_url }}" alt="{{ $pass->holder_name }}"
                         class="rounded-circle mb-2" width="80" height="80" style="object-fit: cover;">
                @endif
                <span class="badge bg-light text-success border border-success px-3 py-2 text-uppercase mb-2">
                    <i class="bi bi-shield-check me-1"></i> {{ $pass->pass_type }} Exit Authorized
                </span>
                <h3 class="fw-bold mb-1 text-dark">{{ $pass->holder_name }}</h3>
                <small class="text-muted font-monospace d-block">Pass ID: GP-{{ str_pad($pass->id, 6, '0', STR_PAD_LEFT) }}</small>
            </div>

            <div class="bg-light p-3 rounded small mb-4">
                <div class="row g-2">
                    @if($pass->pass_type === 'student' && $pass->student)
                        <div class="col-6">
                            <span class="text-muted d-block text-uppercase small">Admission No</span>
                            <strong>{{ $pass->student->admission_no }}</strong>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block text-uppercase small">Class / Section</span>
                            <strong>{{ $pass->student->schoolClass ? $pass->student->schoolClass->name : 'N/A' }}</strong>
                        </div>
                        <div class="col-12 mt-2">
                            <span class="text-muted d-block text-uppercase small">Father's Name</span>
                            <strong>{{ $pass->student->father_name }}</strong>
                        </div>
                    @elseif($pass->pass_type === 'staff' && $pass->user)
                        <div class="col-12">
                            <span class="text-muted d-block text-uppercase small">Staff Email</span>
                            <strong>{{ $pass->user->email }}</strong>
                        </div>
                    @endif

                    @if($pass->vehicle_no)
                        <div class="col-12 mt-2">
                            <span class="text-muted d-block text-uppercase small">Vehicle Registration No</span>
                            <strong><i class="bi bi-car-front-fill me-1"></i> {{ $pass->vehicle_no }}</strong>
                        </div>
                    @endif

                    <div class="col-12 mt-2">
                        <span class="text-muted d-block text-uppercase small">Exit Reason</span>
                        <strong>{{ $pass->purpose }}</strong>
                    </div>

                    <div class="col-6 mt-2">
                        <span class="text-muted d-block text-uppercase small">Release Date</span>
                        <strong>{{ $pass->request_date->format('M d, Y') }}</strong>
                    </div>
                    <div class="col-6 mt-2">
                        <span class="text-muted d-block text-uppercase small">Departure Time</span>
                        <strong>{{ \Carbon\Carbon::parse($pass->departure_time)->format('h:i A') }}</strong>
                    </div>
                </div>
            </div>

            <!-- Signature Lines -->
            <div class="row text-center mt-5 small g-2 pt-3 border-top">
                <div class="col-6">
                    <div style="height: 40px;"></div>
                    <div class="border-top mx-2 pt-1 text-muted">Issued By</div>
                    <strong>{{ $pass->requester ? $pass->requester->name : 'Receptionist' }}</strong>
                </div>
                <div class="col-6">
                    <div style="height: 40px;"></div>
                    <div class="border-top mx-2 pt-1 text-muted">Gate Verification</div>
                    <span class="text-muted italic small">Security Signature</span>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top text-center text-muted small" style="font-size: 0.8rem;">
                <i class="bi bi-info-circle me-1"></i> Pass must be signed and verified by gate security officer before exit.
            </div>
        </div>
    </div>

</body>
</html>
