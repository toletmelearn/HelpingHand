<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - HelpingHand School ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #224abe;
            --success-color: #1cc88a;
            --light-bg: #f8f9fc;
        }
        
        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table thead {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(78, 115, 223, 0.05);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            border-radius: 8px;
        }
        
        .payment-status.paid {
            background: #d4edda;
            color: #155724;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .amount-positive {
            color: var(--success-color);
            font-weight: 700;
        }
        
        .back-btn {
            background: #6c757d;
            border: none;
            border-radius: 8px;
            padding: 8px 15px;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="{{ route('parent.dashboard') }}">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
            <div class="d-flex align-items-center">
                <span class="text-white me-3">
                    <i class="fas fa-user me-1"></i>{{ $student->guardian_name ?? $student->father_name }}
                </span>
                <form method="POST" action="{{ route('parent.logout') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-light">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">
                                <i class="fas fa-history me-2 text-primary"></i>Payment History
                            </h4>
                            <div>
                                <span class="badge bg-primary me-2">Student: {{ $student->name }}</span>
                                <span class="badge bg-secondary">Admission: {{ $student->admission_no }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($paymentHistory->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Receipt No</th>
                                            <th>Payment Date</th>
                                            <th>Payment Mode</th>
                                            <th>Amount</th>
                                            <th>Discount</th>
                                            <th>Late Fine</th>
                                            <th>Final Amount</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($paymentHistory as $payment)
                                        <tr>
                                            <td>
                                                <strong>#{{ $payment->receipt_no }}</strong>
                                            </td>
                                            <td>
                                                <i class="fas fa-calendar me-1 text-muted"></i>
                                                {{ $payment->payment_date->format('d M Y') }}
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    {{ ucfirst($payment->payment_mode) }}
                                                </span>
                                            </td>
                                            <td class="amount-positive">
                                                ₹{{ number_format($payment->total_amount, 2) }}
                                            </td>
                                            <td>
                                                @if($payment->discount > 0)
                                                    <span class="text-danger">-₹{{ number_format($payment->discount, 2) }}</span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($payment->late_fine > 0)
                                                    <span class="text-warning">+₹{{ number_format($payment->late_fine, 2) }}</span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="amount-positive">
                                                <strong>₹{{ number_format($payment->final_amount, 2) }}</strong>
                                            </td>
                                            <td>
                                                <span class="payment-status paid">PAID</span>
                                            </td>
                                            <td>
                                                <a href="{{ route('parent.receipt.download', $payment->id) }}" 
                                                   class="btn btn-primary btn-sm">
                                                    <i class="fas fa-download me-1"></i>Download
                                                </a>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-light">
                                            <td colspan="6" class="text-end"><strong>Total Payments:</strong></td>
                                            <td class="amount-positive">
                                                <strong>₹{{ number_format($paymentHistory->sum('final_amount'), 2) }}</strong>
                                            </td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-5">
                                <i class="fas fa-file-invoice fa-4x text-muted mb-4"></i>
                                <h4 class="text-muted">No Payment History Found</h4>
                                <p class="text-muted">There are no payment records for this student.</p>
                                <a href="{{ route('parent.dashboard') }}" class="btn btn-primary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                </a>
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