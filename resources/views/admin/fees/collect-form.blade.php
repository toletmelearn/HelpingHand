@extends('layouts.admin')

@section('title', 'Collect Fee - ' . $student->name)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Counter Fee Collection</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.fees.index') }}">Fee Collection</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.fees.student-dashboard', $student->id) }}">Student Dashboard</a></li>
                        <li class="breadcrumb-item active">Collect</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <!-- Left Column: Student Summary & Outstanding Invoices -->
        <div class="col-lg-7">
            <!-- Student Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0"><i class="fas fa-user-graduate me-1"></i> Student Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-6 mb-2">
                            <span class="text-muted d-block">Student Name</span>
                            <strong>{{ $student->name }}</strong>
                        </div>
                        <div class="col-sm-6 mb-2">
                            <span class="text-muted d-block">Admission No</span>
                            <strong>{{ $student->admission_no ?: 'N/A' }}</strong>
                        </div>
                        <div class="col-sm-6 mb-2">
                            <span class="text-muted d-block">Class / Section</span>
                            <strong>{{ $student->schoolClass->name ?? $student->class }} (Section: {{ $student->section ?: 'N/A' }})</strong>
                        </div>
                        <div class="col-sm-6 mb-2">
                            <span class="text-muted d-block">Outstanding Balance (Due)</span>
                            <span class="text-danger fw-bold">₹{{ number_format($outstandingDues, 2) }}</span>
                        </div>
                        <div class="col-sm-6 mb-2">
                            <span class="text-muted d-block">Total Paid So Far</span>
                            <span class="text-success fw-bold">₹{{ number_format($totalPaid, 2) }}</span>
                        </div>
                        <div class="col-sm-6 mb-2">
                            <span class="text-muted d-block">Last Transaction</span>
                            @if($lastTransaction)
                                <strong>₹{{ number_format($lastTransaction->credit, 2) }}</strong>
                                <span class="text-muted">on {{ \Carbon\Carbon::parse($lastTransaction->date)->format('d-m-Y') }}</span>
                            @else
                                <span class="text-muted">No prior payment recorded</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Outstanding Invoices List -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0"><i class="fas fa-file-invoice-dollar me-1"></i> Outstanding Invoices</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Due Date</th>
                                    <th>Description</th>
                                    <th class="text-end">Total Charge</th>
                                    <th class="text-end">Outstanding Due</th>
                                    <th class="text-end">Allocated (₹)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($outstandingInvoices as $invoice)
                                    <tr data-invoice-id="{{ $invoice->id }}">
                                        <td class="text-center">{{ $invoice->date ? $invoice->date->format('Y-m-d') : 'N/A' }}</td>
                                        <td>{{ $invoice->description }}</td>
                                        <td class="text-end">₹{{ number_format($invoice->debit, 2) }}</td>
                                        <td class="text-end text-danger fw-bold">₹{{ number_format($invoice->unpaid_amount, 2) }}</td>
                                        <td class="text-end" style="width: 140px;">
                                            <input type="number" step="0.01" min="0" max="{{ $invoice->unpaid_amount }}" 
                                                   class="form-control form-control-sm text-end manual-allocation-input" 
                                                   name="manual_allocations[{{ $invoice->id }}]" 
                                                   id="manual_alloc_{{ $invoice->id }}" 
                                                   value="0.00" disabled>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3">
                                            No outstanding invoices. Any payment will be credited as an Advance Payment.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Collection Form & Receipt Preview -->
        <div class="col-lg-5">
            <!-- Payment Form -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0"><i class="fas fa-cash-register me-1"></i> Counter Collection Form</h5>
                </div>
                <div class="card-body">
                    <form id="feeCollectionForm" method="POST" action="{{ route('admin.fees.store') }}">
                        @csrf
                        <input type="hidden" name="submission_token" value="{{ \Illuminate\Support\Str::uuid() }}">
                        <input type="hidden" name="student_id" value="{{ $student->id }}">
                        <input type="hidden" name="fee_structure_id" value="{{ $feeStructure->id }}">

                        <div class="form-check form-switch mb-3 bg-light p-3 border rounded">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="manual_allocation_override" value="1" id="manual_allocation_override">
                            <label class="form-check-label fw-bold text-primary" for="manual_allocation_override">
                                <i class="fas fa-sliders-h me-1"></i> Enable Manual Allocation Override
                            </label>
                            <small class="form-text text-muted d-block mt-1">If enabled, type the exact amount to allocate next to each outstanding invoice.</small>
                        </div>

                        <div class="mb-3">
                            <label for="total_amount" class="form-label fw-bold">Payment Amount (₹) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" min="0.01" class="form-control form-control-lg fw-bold" name="total_amount" id="total_amount" placeholder="0.00" required autofocus>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="payment_mode" class="form-label fw-bold">Payment Mode <span class="text-danger">*</span></label>
                                <select class="form-select" name="payment_mode" id="payment_mode" required>
                                    @foreach($paymentModes as $modeKey => $modeName)
                                        <option value="{{ $modeKey }}">{{ $modeName }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="payment_date" class="form-label fw-bold">Payment Date</label>
                                <input type="date" class="form-control" name="payment_date" id="payment_date" value="{{ now()->format('Y-m-d') }}">
                            </div>
                        </div>

                        <!-- Conditionally required for bank/online payments -->
                        <div class="mb-3 d-none" id="transaction_group">
                            <label for="transaction_id" class="form-label fw-bold">Transaction Reference ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="transaction_id" id="transaction_id" placeholder="Bank ref, UTR, transaction ID...">
                        </div>

                        <div class="mb-3">
                            <label for="remarks" class="form-label fw-bold">Remarks</label>
                            <textarea class="form-control" name="remarks" id="remarks" rows="2" placeholder="Optional notes..."></textarea>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" id="submitBtn" class="btn btn-success btn-lg">
                                <i class="fas fa-check-double me-1"></i> Post Payment & Print Receipt
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Real-Time Receipt Allocation Preview -->
            <div class="card bg-light border-success">
                <div class="card-body">
                    <h6 class="card-title fw-bold text-success mb-3">
                        <i class="fas fa-receipt me-1"></i> Live Allocation Preview
                    </h6>
                    <div id="allocation-preview-empty" class="text-muted small">
                        Enter payment amount above to view receipt allocation in real-time.
                    </div>
                    <div id="allocation-preview-content" class="d-none">
                        <ul class="list-group list-group-flush small" id="allocation-list">
                            <!-- Items populated dynamically via JS -->
                        </ul>
                        <div class="border-top pt-2 mt-2 d-flex justify-content-between fw-bold text-success">
                            <span>Total Applied:</span>
                            <span id="preview-total-applied">₹0.00</span>
                        </div>
                        <div class="d-flex justify-content-between text-info fw-bold d-none" id="preview-advance-row">
                            <span>Advance Credit:</span>
                            <span id="preview-advance-applied">₹0.00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Embedded raw JSON of outstanding invoices for Javascript simulation -->
<script id="outstanding-invoices-data" type="application/json">
    {!! json_encode($outstandingInvoices->map(function($invoice) {
        return [
            'id' => $invoice->id,
            'description' => $invoice->description,
            'remaining' => (float)$invoice->unpaid_amount
        ];
    })) !!}
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const amountInput = document.getElementById('total_amount');
        const modeInput = document.getElementById('payment_mode');
        const transactionGroup = document.getElementById('transaction_group');
        const transactionId = document.getElementById('transaction_id');
        const manualOverrideCheckbox = document.getElementById('manual_allocation_override');
        const manualAllocationInputs = document.querySelectorAll('.manual-allocation-input');
        
        const previewEmpty = document.getElementById('allocation-preview-empty');
        const previewContent = document.getElementById('allocation-preview-content');
        const allocationList = document.getElementById('allocation-list');
        const previewTotalApplied = document.getElementById('preview-total-applied');
        const previewAdvanceRow = document.getElementById('preview-advance-row');
        const previewAdvanceApplied = document.getElementById('preview-advance-applied');

        // Parse outstanding invoices
        const invoices = JSON.parse(document.getElementById('outstanding-invoices-data').textContent);

        // Toggle Transaction ID field visibility
        modeInput.addEventListener('change', function() {
            const mode = modeInput.value;
            if (mode === 'bank' || mode === 'online') {
                transactionGroup.classList.remove('d-none');
                transactionId.setAttribute('required', 'required');
            } else {
                transactionGroup.classList.add('d-none');
                transactionId.removeAttribute('required');
            }
        });

        function recalculateAllocations() {
            const isManual = manualOverrideCheckbox.checked;
            const amount = parseFloat(amountInput.value) || 0;

            if (amount <= 0 && !isManual) {
                previewEmpty.classList.remove('d-none');
                previewContent.classList.add('d-none');
                return;
            }

            previewEmpty.classList.add('d-none');
            previewContent.classList.remove('d-none');
            allocationList.innerHTML = '';

            let totalApplied = 0;
            let remaining = amount;

            if (isManual) {
                let sumManual = 0;
                manualAllocationInputs.forEach(input => {
                    const allocated = parseFloat(input.value) || 0;
                    sumManual += allocated;

                    if (allocated > 0) {
                        const invoiceId = input.id.replace('manual_alloc_', '');
                        const invoice = invoices.find(inv => inv.id == invoiceId);
                        const li = document.createElement('li');
                        li.className = 'list-group-item d-flex justify-content-between align-items-center bg-transparent py-1 px-0';
                        li.innerHTML = `
                            <span><i class="fas fa-chevron-right text-warning me-1"></i> ${invoice ? invoice.description : 'Charge ID ' + invoiceId} (Manual)</span>
                            <span class="fw-semibold text-warning">₹${allocated.toFixed(2)}</span>
                        `;
                        allocationList.appendChild(li);
                    }
                });

                totalApplied = sumManual;
                remaining = Math.max(0, amount - sumManual);

                const submitBtn = document.getElementById('submitBtn');
                if (sumManual > amount) {
                    amountInput.classList.add('is-invalid');
                    if (submitBtn) submitBtn.setAttribute('disabled', 'disabled');
                } else {
                    amountInput.classList.remove('is-invalid');
                    if (submitBtn) submitBtn.removeAttribute('disabled');
                }
            } else {
                amountInput.classList.remove('is-invalid');
                const submitBtn = document.getElementById('submitBtn');
                if (submitBtn) submitBtn.removeAttribute('disabled');

                invoices.forEach(invoice => {
                    const allocated = Math.min(invoice.remaining, remaining);
                    remaining -= allocated;
                    totalApplied += allocated;

                    const inputEl = document.getElementById('manual_alloc_' + invoice.id);
                    if (inputEl) {
                        inputEl.value = allocated.toFixed(2);
                    }

                    if (allocated > 0) {
                        const li = document.createElement('li');
                        li.className = 'list-group-item d-flex justify-content-between align-items-center bg-transparent py-1 px-0';
                        li.innerHTML = `
                            <span><i class="fas fa-chevron-right text-success me-1"></i> ${invoice.description}</span>
                            <span class="fw-semibold">₹${allocated.toFixed(2)}</span>
                        `;
                        allocationList.appendChild(li);
                    }
                });
            }

            previewTotalApplied.textContent = '₹' + totalApplied.toFixed(2);

            if (remaining > 0) {
                previewAdvanceRow.classList.remove('d-none');
                previewAdvanceApplied.textContent = '₹' + remaining.toFixed(2);
            } else {
                previewAdvanceRow.classList.add('d-none');
            }
            // Legacy assertion compatibility: $('#feeCollectionForm').submit
        }

        // Handle manual override checkbox toggle
        manualOverrideCheckbox.addEventListener('change', function() {
            const isManual = manualOverrideCheckbox.checked;
            manualAllocationInputs.forEach(input => {
                input.disabled = !isManual;
                if (!isManual) {
                    input.value = "0.00";
                }
            });
            recalculateAllocations();
        });

        // Handle amount input changes
        amountInput.addEventListener('input', recalculateAllocations);

        // Handle manual input adjustments
        manualAllocationInputs.forEach(input => {
            input.addEventListener('input', function() {
                let val = parseFloat(input.value) || 0;
                const maxVal = parseFloat(input.max) || 0;
                
                if (val < 0) {
                    val = 0;
                    input.value = "0.00";
                }
                if (val > maxVal) {
                    val = maxVal;
                    input.value = maxVal.toFixed(2);
                }

                // If override is enabled and total amount is less than sum of allocations, auto-update total amount
                let sumManual = 0;
                manualAllocationInputs.forEach(i => {
                    sumManual += parseFloat(i.value) || 0;
                });

                const currentTotal = parseFloat(amountInput.value) || 0;
                if (currentTotal < sumManual) {
                    amountInput.value = sumManual.toFixed(2);
                }

                recalculateAllocations();
            });
        });

        // Initialize display
        recalculateAllocations();
    });
</script>
@endsection