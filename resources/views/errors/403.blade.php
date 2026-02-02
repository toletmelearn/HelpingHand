<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>403 - Access Forbidden | HelpingHand ERP</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-lg">
                    <div class="card-header bg-warning text-dark">
                        <h4 class="mb-0">
                            <i class="bi bi-shield-lock me-2"></i>
                            403 - Access Forbidden
                        </h4>
                    </div>
                    <div class="card-body text-center py-5">
                        <div class="display-1 text-warning mb-4">
                            <i class="bi bi-lock"></i>
                        </div>
                        <h2 class="mb-3">Access Denied</h2>
                        <p class="lead mb-4">
                            You don't have permission to access this resource.
                        </p>
                        <div class="alert alert-warning text-start">
                            <strong>Requested URL:</strong> {{ request()->fullUrl() }}<br>
                            <strong>Timestamp:</strong> {{ now()->format('Y-m-d H:i:s') }}
                        </div>
                        <div class="mt-4">
                            <a href="{{ route('admin.dashboard') }}" class="btn btn-primary me-2">
                                <i class="bi bi-speedometer2 me-1"></i>
                                Return to Dashboard
                            </a>
                            <a href="javascript:history.back()" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i>
                                Go Back
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4 text-muted">
                    <small>HelpingHand School Management System</small>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
