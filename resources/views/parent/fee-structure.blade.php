<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Structure - HelpingHand School ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="{{ route('parent.dashboard') }}">
                <i class="fas fa-graduation-cap me-2"></i>HelpingHand School ERP
            </a>
            <div class="navbar-nav ms-auto">
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
                    <i class="fas fa-file-invoice me-2"></i>
                    Fee Structure
                </h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="{{ route('parent.dashboard') }}">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item active">Fee Structure</li>
                    </ol>
                </nav>
                <hr>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Fee Structure for Class: {{ $student->class ?? 'N/A' }}
                        </h5>
                    </div>
                    <div class="card-body">
                        @if($feeStructure && $feeItems->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>{{ $parentDisplayFrequency === 'quarterly' ? 'Fee Head (Quarter)' : 'Fee Head (Month)' }}</th>
                                            <th>Amount (₹)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($displayRows as $row)
                                            <tr>
                                                <td>{{ $row['label'] }}</td>
                                                <td>₹{{ number_format($row['amount'], 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="table-secondary">
                                        <tr>
                                            <th>Total (Annual)</th>
                                            <th>₹{{ number_format(collect($displayRows)->sum('amount'), 2) }}</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @else
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                No fee structure found for your class.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>