@extends('layouts.admin')

@section('title', 'Create Fee Structure')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Create Fee Structure</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.fee-structures.index') }}">Fee Structures</a></li>
                        <li class="breadcrumb-item active">Create</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-white">Structure Builder Wizard</h5>
                    <a href="{{ route('admin.fee-structures.index') }}" class="btn btn-light btn-sm text-primary">
                        <i class="fas fa-arrow-left me-1"></i> Back
                    </a>
                </div>
                <div class="card-body">
                    <form id="feeStructureForm" action="{{ route('admin.fee-structures.store') }}" method="POST">
                        @csrf
                        
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="class_name" class="form-label fw-bold">Target Class <span class="text-danger">*</span></label>
                                    <select class="form-select @error('class_name') is-invalid @enderror" 
                                            id="class_name" name="class_name" required>
                                        <option value="">Select Class</option>
                                        @foreach($classes as $class)
                                            <option value="{{ $class->name }}" {{ old('class_name') == $class->name ? 'selected' : '' }}>
                                                {{ $class->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('class_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="academic_year" class="form-label fw-bold">Academic Session <span class="text-danger">*</span></label>
                                    @if($academicSessions->isNotEmpty())
                                    <select class="form-control @error('academic_year') is-invalid @enderror"
                                            id="academic_year" name="academic_year" required>
                                        @foreach($academicSessions as $session)
                                            <option value="{{ $session->code }}" {{ old('academic_year', $academicSessions->firstWhere('is_current', true)->code ?? '') == $session->code ? 'selected' : '' }}>
                                                {{ $session->code }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @else
                                    <input type="text" class="form-control @error('academic_year') is-invalid @enderror"
                                           id="academic_year" name="academic_year"
                                           value="{{ old('academic_year', date('Y') . '-' . (date('Y')+1)) }}"
                                           placeholder="2026-2027" required>
                                    <small class="form-text text-muted">No academic sessions configured yet -- set one up under Academic Sessions for a dropdown here.</small>
                                    @endif
                                    @error('academic_year')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="frequency" class="form-label fw-bold">Overall Billing Type <span class="text-danger">*</span></label>
                                    <select class="form-select @error('frequency') is-invalid @enderror" 
                                            id="frequency" name="frequency" required>
                                        <option value="custom" selected>Custom (Item-wise settings apply)</option>
                                        <option value="monthly">Standard Monthly</option>
                                        <option value="quarterly">Standard Quarterly</option>
                                        <option value="yearly">Standard Yearly</option>
                                    </select>
                                    @error('frequency')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">
                        
                        <h5 class="mb-3"><i class="fas fa-list me-1 text-primary"></i> Select & Set Fee Amounts</h5>
                        
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="feeTypesTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 5%;" class="text-center">Select</th>
                                        <th style="width: 25%;">Fee Head</th>
                                        <th style="width: 25%;">Category</th>
                                        <th style="width: 25%;">Amount</th>
                                        <th style="width: 20%;" class="text-end font-semibold">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($feeTypes as $type)
                                        <tr class="fee-head-row" data-id="{{ $type->id }}">
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input fee-checkbox" id="chk_{{ $type->id }}">
                                                <input type="hidden" name="fee_type_id[]" value="{{ $type->id }}" disabled class="row-input">
                                            </td>
                                            <td>
                                                <label for="chk_{{ $type->id }}" class="form-label mb-0 fw-bold text-dark cursor-pointer">{{ $type->name }}</label>
                                                @if($type->description)
                                                    <small class="d-block text-muted">{{ $type->description }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary text-capitalize">{{ $type->category }}</span>
                                            </td>
                                            <td>
                                                <div class="input-group">
                                                    <span class="input-group-text">₹</span>
                                                    <input type="number" class="form-control fee-amount row-input" 
                                                           name="amount[]" 
                                                           step="0.01" min="0" disabled required placeholder="Enter amount">
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-outline-primary btn-sm customize-btn" disabled>
                                                    <i class="fas fa-sliders-h me-1"></i> Customize
                                                </button>
                                            </td>
                                        </tr>
                                        <!-- Collapsible customization block -->
                                        <tr class="customization-row d-none" id="custom_row_{{ $type->id }}">
                                            <td colspan="5" class="bg-light p-3 border-bottom">
                                                <div class="card card-body mb-0 shadow-none border bg-white">
                                                    <h6 class="card-title text-primary"><i class="fas fa-cog me-1"></i> Override Settings for {{ $type->name }}</h6>
                                                    <div class="row g-3">
                                                        <div class="col-md-3">
                                                            <label class="form-label small fw-semibold text-muted">Billing Frequency</label>
                                                            <select class="form-select billing-frequency-select row-input" name="billing_frequency[]" disabled>
                                                                <option value="monthly">Monthly</option>
                                                                <option value="quarterly">Quarterly</option>
                                                                <option value="yearly">Yearly / Annual</option>
                                                                <option value="session_wise_admission">Session Admission</option>
                                                                <option value="exam_wise">Exam Wise</option>
                                                                <option value="custom">Custom Months</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <label class="form-label small fw-semibold text-muted">Due Day</label>
                                                            <input type="number" class="form-control row-input" name="due_day[]" min="1" max="31" disabled placeholder="e.g. 10">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label small fw-semibold text-muted">Late Fee Rule</label>
                                                            <select class="form-select row-input" name="late_fee_rule_id[]" disabled>
                                                                <option value="">No Late Fee</option>
                                                                @foreach($lateFeeRules as $rule)
                                                                    <option value="{{ $rule->id }}">{{ $rule->name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4 installment-config-col d-none">
                                                            <label class="form-label small fw-semibold text-muted">Installments Override</label>
                                                            <button type="button" class="btn btn-outline-info btn-sm d-block w-100 configure-installments-btn">
                                                                <i class="fas fa-edit me-1"></i> Set Installment Amounts
                                                            </button>
                                                        </div>
                                                        <div class="col-md-4 exam-config-col d-none">
                                                            <label class="form-label small fw-semibold text-muted">Select Exam</label>
                                                            <select class="form-select row-input exam-select" name="exam_id[]" disabled>
                                                                <option value="">Select Exam Event</option>
                                                                <!-- Dynamically loaded from DB later, or hardcoded for sample -->
                                                                <option value="1">Unit Test 1</option>
                                                                <option value="2">Half Yearly</option>
                                                                <option value="3">Annual Exam</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mt-3 months-container">
                                                        <label class="form-label small fw-semibold text-muted d-block">Applicable Billing Months</label>
                                                        <div class="d-flex flex-wrap gap-2 month-pills-wrapper">
                                                            <!-- Month pills populated dynamically -->
                                                        </div>
                                                    </div>

                                                    <input type="hidden" class="charge-months-input row-input" name="charge_months_raw[]" value="" disabled>
                                                    <input type="hidden" class="installments-raw-input row-input" name="installments_raw[]" value="" disabled>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Modal for Custom Installment Entry -->
                        <div class="modal fade" id="installmentsModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Configure Installment Amounts</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="alert alert-info">
                                            Specify variable amounts for each active month. Total installments must equal the main amount: <strong id="mainAmountSpan">₹0.00</strong>
                                        </div>
                                        <div id="installmentsWrapper" class="d-flex flex-col gap-3">
                                            <!-- Dynamically generated month inputs -->
                                        </div>
                                        <div class="mt-3 text-end fw-bold">
                                            Running Total: <span id="installmentRunningTotal">₹0.00</span>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-primary" id="saveInstallmentsBtn">Save Installments</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Estimated Totals</h6>
                                        <p class="mb-1">
                                            <strong>Monthly Total (Active Heads):</strong> 
                                            <span id="monthlyTotal">₹0.00</span>
                                        </p>
                                        <p class="mb-0">
                                            <strong>Yearly Total (Active Heads):</strong> 
                                            <span id="yearlyTotal">₹0.00</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <a href="{{ route('admin.fee-structures.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary" id="saveBtn">
                                <i class="fas fa-save me-1"></i> Generate Structure
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Dump defaults from DB seeder
    const feeTypeDefaults = @json($feeTypes);
    let activeInstallmentRow = null; // tracking which row is setting installments

    // Render month pills based on the row frequency
    function renderMonthPills($row, selectedMonths = null) {
        const frequency = $row.find('.billing-frequency-select').val();
        const $pillsWrapper = $row.find('.month-pills-wrapper');
        const $hiddenInput = $row.find('.charge-months-input');
        
        let pillsHtml = '';
        let activeMonths = selectedMonths || ($hiddenInput.val() ? $hiddenInput.val().split(',') : []);
        
        if (frequency === 'monthly' || frequency === 'custom') {
            const months = [
                { name: 'April', label: 'Apr' },
                { name: 'May', label: 'May' },
                { name: 'June', label: 'Jun' },
                { name: 'July', label: 'Jul' },
                { name: 'August', label: 'Aug' },
                { name: 'September', label: 'Sep' },
                { name: 'October', label: 'Oct' },
                { name: 'November', label: 'Nov' },
                { name: 'December', label: 'Dec' },
                { name: 'January', label: 'Jan' },
                { name: 'February', label: 'Feb' },
                { name: 'March', label: 'Mar' }
            ];
            
            pillsHtml = months.map(m => {
                const isActive = activeMonths.length > 0 ? activeMonths.includes(m.name) : true;
                return `<button type="button" class="btn btn-sm ${isActive ? 'btn-primary' : 'btn-outline-secondary'} month-pill" data-value="${m.name}">${m.label}</button>`;
            }).join('');
            
        } else if (frequency === 'quarterly') {
            const quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
            pillsHtml = quarters.map(q => {
                const isActive = activeMonths.length > 0 ? activeMonths.includes(q) : true;
                return `<button type="button" class="btn btn-sm ${isActive ? 'btn-primary' : 'btn-outline-secondary'} month-pill" data-value="${q}">${q}</button>`;
            }).join('');
        } else if (frequency === 'yearly') {
            pillsHtml = `<button type="button" class="btn btn-sm btn-primary month-pill active" data-value="Annual" disabled>Annual (April)</button>`;
            activeMonths = ['Annual'];
        } else if (frequency === 'session_wise_admission') {
            pillsHtml = `<button type="button" class="btn btn-sm btn-primary month-pill active" data-value="April" disabled>Admission (April)</button>`;
            activeMonths = ['April'];
        } else if (frequency === 'exam_wise') {
            pillsHtml = `<button type="button" class="btn btn-sm btn-primary month-pill active" data-value="Exam" disabled>Exam Event Wise</button>`;
            activeMonths = ['Exam'];
        }
        
        $pillsWrapper.html(pillsHtml);
        
        const activeVals = [];
        $pillsWrapper.find('.month-pill.btn-primary').each(function() {
            activeVals.push($(this).data('value'));
        });
        $hiddenInput.val(activeVals.join(','));
        
        // Show/hide configs
        if (frequency === 'custom') {
            $row.find('.installment-config-col').removeClass('d-none');
        } else {
            $row.find('.installment-config-col').addClass('d-none');
        }
        
        if (frequency === 'exam_wise') {
            $row.find('.exam-config-col').removeClass('d-none');
            $row.find('.exam-select').prop('disabled', false);
        } else {
            $row.find('.exam-config-col').addClass('d-none');
            $row.find('.exam-select').prop('disabled', true);
        }
    }

    // Checkbox toggling
    $('.fee-checkbox').change(function() {
        const isChecked = $(this).is(':checked');
        const $row = $(this).closest('.fee-head-row');
        const typeId = $row.data('id');
        const $customRow = $(`#custom_row_${typeId}`);
        
        $row.find('.row-input').prop('disabled', !isChecked);
        $customRow.find('.row-input').prop('disabled', !isChecked);
        $row.find('.customize-btn').prop('disabled', !isChecked);
        
        if (isChecked) {
            // Apply defaults from template
            const defaults = feeTypeDefaults.find(t => t.id === typeId);
            if (defaults) {
                // Populate billing frequency
                $customRow.find('.billing-frequency-select').val(defaults.default_frequency || 'monthly');
                
                // Populate late fee rule
                if (defaults.default_late_fee_rule_id) {
                    $customRow.find('select[name="late_fee_rule_id[]"]').val(defaults.default_late_fee_rule_id);
                }
                
                // Populate charge months
                let months = defaults.default_charge_months;
                if (typeof months === 'string') months = JSON.parse(months);
                
                renderMonthPills($customRow, months);
            } else {
                renderMonthPills($customRow);
            }
        } else {
            // Hide customization
            $customRow.addClass('d-none');
            $row.find('.customize-btn').removeClass('active btn-primary').addClass('btn-outline-primary');
        }
        
        updateTotals();
    });

    // Customize toggle button
    $('.customize-btn').click(function() {
        const $row = $(this).closest('.fee-head-row');
        const typeId = $row.data('id');
        const $customRow = $(`#custom_row_${typeId}`);
        
        $customRow.toggleClass('d-none');
        $(this).toggleClass('active btn-primary btn-outline-primary');
    });

    // Frequency change inside customization row
    $(document).on('change', '.billing-frequency-select', function() {
        const $customRow = $(this).closest('.customization-row');
        $customRow.find('.charge-months-input').val(''); // clear stored months
        renderMonthPills($customRow);
    });

    // Month pill click inside customization row
    $(document).on('click', '.month-pill', function() {
        const $pill = $(this);
        const $customRow = $pill.closest('.customization-row');
        const freq = $customRow.find('.billing-frequency-select').val();
        
        if (freq === 'yearly' || freq === 'session_wise_admission' || freq === 'exam_wise') return;
        
        $pill.toggleClass('btn-primary btn-outline-secondary');
        
        const activeVals = [];
        $customRow.find('.month-pill.btn-primary').each(function() {
            activeVals.push($(this).data('value'));
        });
        $customRow.find('.charge-months-input').val(activeVals.join(','));
    });

    // Configure Installments Modal trigger
    $(document).on('click', '.configure-installments-btn', function() {
        const $customRow = $(this).closest('.customization-row');
        activeInstallmentRow = $customRow;
        
        const typeId = $customRow.attr('id').split('_').pop();
        const $amountInput = $(`.fee-head-row[data-id="${typeId}"]`).find('.fee-amount');
        const totalAmount = parseFloat($amountInput.val()) || 0;
        
        if (totalAmount <= 0) {
            alert('Please enter a valid amount for this fee type first.');
            return;
        }

        $('#mainAmountSpan').text('₹' + totalAmount.toFixed(2));
        
        // Fetch selected months
        const selectedMonths = $customRow.find('.charge-months-input').val().split(',');
        if (selectedMonths.length === 0 || !selectedMonths[0]) {
            alert('Please select at least one applicable month first.');
            return;
        }

        // Parse existing installment values if any
        const existingRaw = $customRow.find('.installments-raw-input').val();
        const existingMap = {};
        if (existingRaw) {
            existingRaw.split(';').forEach(part => {
                const sub = part.split(':');
                if (sub.length === 2) {
                    existingMap[sub[0]] = parseFloat(sub[1]) || 0;
                }
            });
        }

        // Generate inputs
        let html = '';
        selectedMonths.forEach(m => {
            const val = existingMap[m] !== undefined ? existingMap[m] : (totalAmount / selectedMonths.length).toFixed(2);
            html += `
                <div class="mb-3 row align-items-center">
                    <label class="col-sm-4 col-form-label fw-bold">${m}</label>
                    <div class="col-sm-8">
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" class="form-control installment-amount-input" data-month="${m}" value="${val}" step="0.01" min="0">
                        </div>
                    </div>
                </div>
            `;
        });
        
        $('#installmentsWrapper').html(html);
        recalcInstallmentsTotal();
        $('#installmentsModal').modal('show');
    });

    // Helper to calculate total of installments entered
    function recalcInstallmentsTotal() {
        let sum = 0;
        $('.installment-amount-input').each(function() {
            sum += parseFloat($(this).val()) || 0;
        });
        $('#installmentRunningTotal').text('₹' + sum.toFixed(2));
    }

    $(document).on('input', '.installment-amount-input', function() {
        recalcInstallmentsTotal();
    });

    // Save installments from Modal
    $('#saveInstallmentsBtn').click(function() {
        if (!activeInstallmentRow) return;
        
        const typeId = activeInstallmentRow.attr('id').split('_').pop();
        const $amountInput = $(`.fee-head-row[data-id="${typeId}"]`).find('.fee-amount');
        const totalAmount = parseFloat($amountInput.val()) || 0;
        
        let sum = 0;
        const serializedParts = [];
        
        $('.installment-amount-input').each(function() {
            const m = $(this).data('month');
            const amt = parseFloat($(this).val()) || 0;
            sum += amt;
            serializedParts.push(`${m}:${amt}`);
        });

        if (Math.abs(sum - totalAmount) > 0.05) {
            alert(`The sum of installments (₹${sum.toFixed(2)}) must equal the main amount (₹${totalAmount.toFixed(2)}).`);
            return;
        }

        activeInstallmentRow.find('.installments-raw-input').val(serializedParts.join(';'));
        $('#installmentsModal').modal('hide');
        alert('Installment amounts configured successfully!');
    });

    // Totals calculations
    $(document).on('input', '.fee-amount', function() {
        updateTotals();
    });

    function updateTotals() {
        let monthlyTotal = 0;
        let yearlyTotal = 0;
        
        $('.fee-checkbox:checked').each(function() {
            const $row = $(this).closest('.fee-head-row');
            const typeId = $row.data('id');
            const $customRow = $(`#custom_row_${typeId}`);
            
            const amount = parseFloat($row.find('.fee-amount').val()) || 0;
            const freq = $customRow.find('.billing-frequency-select').val();
            const monthsRaw = $customRow.find('.charge-months-input').val();
            const months = monthsRaw ? monthsRaw.split(',') : [];
            const count = months.length;

            if (freq === 'monthly') {
                monthlyTotal += amount;
                yearlyTotal += amount * count;
            } else if (freq === 'quarterly') {
                yearlyTotal += amount * count;
            } else if (freq === 'yearly' || freq === 'session_wise_admission') {
                yearlyTotal += amount;
            } else if (freq === 'custom') {
                yearlyTotal += amount; // custom config sum equals total amount entered
            } else if (freq === 'exam_wise') {
                yearlyTotal += amount * 3; // estimate average of 3 exams
            }
        });

        $('#monthlyTotal').text('₹' + monthlyTotal.toFixed(2));
        $('#yearlyTotal').text('₹' + yearlyTotal.toFixed(2));
    }

    $('#feeStructureForm').submit(function(e) {
        // Validation: Must select at least one fee type
        if ($('.fee-checkbox:checked').length === 0) {
            alert('Please select at least one fee head to build the structure.');
            e.preventDefault();
            return;
        }

        $('#saveBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Generating...');
    });
});
</script>
@endsection