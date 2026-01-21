<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Class - HelpingHand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white text-center">
                        <h3><i class="bi bi-calendar-check"></i> Mark Daily Attendance</h3>
                        <p class="mb-0">Select class to mark attendance for {{ \Carbon\Carbon::parse($date)->format('d F Y') }}</p>
                    </div>
                    <div class="card-body">
                        @if($classes->count() > 0)
                            <div class="row">
                                @foreach($classes as $class)
                                    <div class="col-md-6 mb-3">
                                        <a href="{{ route('attendance.create', ['class' => $class, 'date' => $date]) }}" 
                                           class="btn btn-outline-primary btn-lg w-100 py-3">
                                            <i class="bi bi-mortarboard"></i>
                                            <div class="mt-2">{{ $class }}</div>
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="alert alert-warning text-center">
                                <i class="bi bi-exclamation-triangle"></i>
                                No classes found. Please add students with class information first.
                            </div>
                        @endif
                        
                        <div class="text-center mt-4">
                            <a href="{{ route('attendance.index') }}" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Attendance List
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>