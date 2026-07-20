@extends('layouts.admin')

@section('title', 'Close Shift')

@section('content')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');
    
    .closing-form-container {
        font-family: 'Outfit', sans-serif;
        background-color: #f8fafc;
        border-radius: 16px;
    }
    
    .page-title {
        font-weight: 700;
        color: #1e293b;
    }
    
    .card-premium {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
</style>

<div class="container-fluid py-4 closing-form-container">
    <div class="mb-4">
        <h3 class="page-title mb-1"><i class="bi bi-shield-lock-fill text-danger me-2"></i>Close Shift Report</h3>
        <p class="text-muted">Perform currency counts, tally against expected system balances, and submit the shift closing form.</p>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card card-premium p-4">
                <form action="{{ route('admin.fees.cashier-closings.store') }}" method="POST" id="closingForm">
                    @csrf
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Cashier Name</label>
                            <input type="text" value="{{ $cashier->name }}" class="form-control bg-light" disabled>
                        </div>
                        <div class="col-md-6">
                            <label for="closing_date" class="form-label fw-bold">Closing Date</label>
                            <input type="date" name="closing_date" id="closing_date" value="{{ $date }}" class="form-control" onchange="window.location.href='?date='+this.value">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="opening_balance" class="form-label fw-bold">Opening Balance (₹)</label>
                        <input type="number" name="opening_balance" id="opening_balance" value="0.00" step="0.01" class="form-control" style="max-width: 200px;">
                    </div>

                    <h5 class="mb-3 border-bottom pb-2">Denomination / Mode Tally</h5>
                    
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Payment Mode</th>
                                <th class="text-end">Expected (System)</th>
                                <th class="text-end" style="width: 220px;">Actual Counted (₹)</th>
                                <th class="text-end">Difference</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- CASH -->
                            <tr>
                                <td><strong>Cash</strong></td>
                                <td class="text-end">
                                    ₹{{ number_format($expected->cash, 2) }}
                                    <input type="hidden" name="expected_cash" id="exp_cash" value="{{ $expected->cash }}">
                                </td>
                                <td>
                                    <input type="number" name="actual_cash" id="act_cash" value="0.00" step="0.01" min="0" class="form-control text-end tally-input">
                                </td>
                                <td class="text-end"><span id="diff_cash" class="fw-bold">₹0.00</span></td>
                            </tr>
                            <!-- UPI -->
                            <tr>
                                <td><strong>UPI</strong></td>
                                <td class="text-end">
                                    ₹{{ number_format($expected->upi, 2) }}
                                    <input type="hidden" name="expected_upi" id="exp_upi" value="{{ $expected->upi }}">
                                </td>
                                <td>
                                    <input type="number" name="actual_upi" id="act_upi" value="0.00" step="0.01" min="0" class="form-control text-end tally-input">
                                </td>
                                <td class="text-end"><span id="diff_upi" class="fw-bold">₹0.00</span></td>
                            </tr>
                            <!-- BANK -->
                            <tr>
                                <td><strong>Bank</strong></td>
                                <td class="text-end">
                                    ₹{{ number_format($expected->bank, 2) }}
                                    <input type="hidden" name="expected_bank" id="exp_bank" value="{{ $expected->bank }}">
                                </td>
                                <td>
                                    <input type="number" name="actual_bank" id="act_bank" value="0.00" step="0.01" min="0" class="form-control text-end tally-input">
                                </td>
                                <td class="text-end"><span id="diff_bank" class="fw-bold">₹0.00</span></td>
                            </tr>
                            <!-- CHEQUE -->
                            <tr>
                                <td><strong>Cheque</strong></td>
                                <td class="text-end">
                                    ₹{{ number_format($expected->cheque, 2) }}
                                    <input type="hidden" name="expected_cheque" id="exp_cheque" value="{{ $expected->cheque }}">
                                </td>
                                <td>
                                    <input type="number" name="actual_cheque" id="act_cheque" value="0.00" step="0.01" min="0" class="form-control text-end tally-input">
                                </td>
                                <td class="text-end"><span id="diff_cheque" class="fw-bold">₹0.00</span></td>
                            </tr>
                            <!-- ONLINE -->
                            <tr>
                                <td><strong>Online</strong></td>
                                <td class="text-end">
                                    ₹{{ number_format($expected->online, 2) }}
                                    <input type="hidden" name="expected_online" id="exp_online" value="{{ $expected->online }}">
                                </td>
                                <td>
                                    <input type="number" name="actual_online" id="act_online" value="0.00" step="0.01" min="0" class="form-control text-end tally-input">
                                </td>
                                <td class="text-end"><span id="diff_online" class="fw-bold">₹0.00</span></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="table-light fw-bold">
                                <td>TOTALS</td>
                                <td class="text-end">₹{{ number_format($expected->cash + $expected->upi + $expected->bank + $expected->cheque + $expected->online, 2) }}</td>
                                <td class="text-end"><span id="total_actual">₹0.00</span></td>
                                <td class="text-end"><span id="total_diff">₹0.00</span></td>
                            </tr>
                        </tfoot>
                    </table>

                    <div class="mb-3 d-none" id="discrepancyBox">
                        <label for="discrepancy_reason" class="form-label fw-bold text-danger">Discrepancy Reason *</label>
                        <textarea name="discrepancy_reason" id="discrepancy_reason" rows="2" class="form-control" placeholder="Describe the reason for collection discrepancies (under/over collection)..."></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="remarks" class="form-label fw-bold">Closing Notes / Remarks</label>
                        <textarea name="remarks" id="remarks" rows="2" class="form-control" placeholder="Any shift notes..."></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('admin.fees.cashier-closings.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                        <button type="submit" class="btn btn-danger px-4">Submit Closing</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card card-premium p-4 bg-light">
                <h5 class="mb-3 border-bottom pb-2"><i class="bi bi-info-circle me-1"></i>Closing Policy</h5>
                <p class="small text-muted mb-2">Each cashier is accountable for counting physical cash, checks, and verifying UPI receipts at the end of their shift.</p>
                <p class="small text-muted mb-2">If any discrepancy is found between the counted balance and the expected system balance, you must provide a valid explanation before submitting.</p>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const inputs = document.querySelectorAll('.tally-input');
        
        function calculateTally() {
            let totalExpected = 0;
            let totalActual = 0;
            let hasDiscrepancy = false;
            
            const modes = ['cash', 'upi', 'bank', 'cheque', 'online'];
            
            modes.forEach(mode => {
                const exp = parseFloat(document.getElementById('exp_' + mode).value) || 0;
                const act = parseFloat(document.getElementById('act_' + mode).value) || 0;
                
                totalExpected += exp;
                totalActual += act;
                
                const diff = act - exp;
                const diffSpan = document.getElementById('diff_' + mode);
                diffSpan.innerText = '₹' + diff.toFixed(2);
                
                if (Math.abs(diff) > 0.01) {
                    hasDiscrepancy = true;
                    diffSpan.className = diff < 0 ? 'text-danger fw-bold' : 'text-success fw-bold';
                } else {
                    diffSpan.className = 'text-muted fw-bold';
                }
            });
            
            const grandDiff = totalActual - totalExpected;
            document.getElementById('total_actual').innerText = '₹' + totalActual.toFixed(2);
            
            const totalDiffSpan = document.getElementById('total_diff');
            totalDiffSpan.innerText = '₹' + grandDiff.toFixed(2);
            if (Math.abs(grandDiff) > 0.01) {
                totalDiffSpan.className = grandDiff < 0 ? 'text-danger fw-bold fs-5' : 'text-success fw-bold fs-5';
                document.getElementById('discrepancyBox').classList.remove('d-none');
                document.getElementById('discrepancy_reason').required = true;
            } else {
                totalDiffSpan.className = 'text-muted fw-bold fs-5';
                document.getElementById('discrepancyBox').classList.add('d-none');
                document.getElementById('discrepancy_reason').required = false;
            }
        }
        
        inputs.forEach(input => {
            input.addEventListener('input', calculateTally);
        });
        
        calculateTally();
    });
</script>
@endsection
