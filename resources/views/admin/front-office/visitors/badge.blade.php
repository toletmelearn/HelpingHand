<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Pass - {{ $visitor->visitor_name }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .badge-card {
            width: 380px;
            margin: 40px auto;
            border: 2px solid #dee2e6;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .badge-header {
            background-color: #0d6efd;
            color: #fff;
            padding: 15px;
            text-align: center;
        }
        .badge-photo {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border: 3px solid #dee2e6;
            border-radius: 6px;
        }
        @media print {
            body {
                background-color: #fff;
                margin: 0;
            }
            .badge-card {
                border: 1px solid #000;
                box-shadow: none;
                margin: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print text-center my-4">
        <button onclick="window.print()" class="btn btn-primary px-4"><i class="bi bi-printer"></i> Print Badge</button>
        <button onclick="window.close()" class="btn btn-outline-secondary px-4 ms-2">Close Window</button>
    </div>

    <div class="badge-card">
        <div class="badge-header">
            <h5 class="fw-bold mb-0">HELPINGHAND ERP</h5>
            <small>Visitor Access Pass</small>
        </div>
        <div class="p-4 text-center">
            @if($visitor->photo_path)
                <img src="{{ asset('storage/' . $visitor->photo_path) }}" alt="Visitor" class="badge-photo mb-3">
            @else
                <div class="badge-photo mb-3 bg-light d-flex align-items-center justify-content-center mx-auto" style="border: 3px solid #dee2e6;">
                    <span class="text-muted fw-bold">NO PHOTO</span>
                </div>
            @endif

            <h4 class="fw-bold mb-1 text-primary">{{ $visitor->visitor_name }}</h4>
            <div class="text-muted mb-3 font-monospace small">Mob: {{ $visitor->phone }}</div>

            <div class="row text-start bg-light p-3 rounded small g-2">
                <div class="col-6">
                    <span class="text-muted d-block text-uppercase small">Purpose</span>
                    <strong>{{ $visitor->purpose }}</strong>
                </div>
                <div class="col-6">
                    <span class="text-muted d-block text-uppercase small">Host Staff</span>
                    <strong>{{ $visitor->host ? $visitor->host->name : 'General Visit' }}</strong>
                </div>
                <div class="col-6 mt-2">
                    <span class="text-muted d-block text-uppercase small">In Time</span>
                    <strong>{{ $visitor->check_in->format('h:i A') }}</strong>
                </div>
                <div class="col-6 mt-2">
                    <span class="text-muted d-block text-uppercase small">Date</span>
                    <strong>{{ $visitor->check_in->format('M d, Y') }}</strong>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top text-center text-muted small">
                Please return this pass at the gate when checking out.
            </div>
        </div>
    </div>

</body>
</html>
