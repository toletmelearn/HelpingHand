@extends('layouts.admin')

@section('title', 'Fee Type Master Defaults')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Fee Type Master Defaults</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.fee-structures.index') }}">Fee Structures</a></li>
                        <li class="breadcrumb-item active">Master Defaults</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-white">Predefined Fee Patterns</h5>
                    <a href="{{ route('admin.fee-structures.index') }}" class="btn btn-light btn-sm text-primary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Structures
                    </a>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.fee-types.update-master') }}" method="POST" id="masterForm">
                        @csrf
                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 15%;">Fee Type</th>
                                        <th style="width: 15%;">Category</th>
                                        <th style="width: 15%;">Default Frequency</th>
                                        <th style="width: 35%;">Default Charge Months</th>
                                        <th style="width: 20%;">Default Late Fee Rule</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($feeTypes as $type)
                                        <tr class="fee-type-row" data-id="{{ $type->id }}">
                                            <td>
                                                <span class="fw-bold text-dark">{{ $type->name }}</span>
                                                <small class="d-block text-muted">{{ $type->description }}</small>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary text-capitalize">{{ $type->category }}</span>
                                            </td>
                                            <td>
                                                <select class="form-select default-frequency-select" name="default_frequency[{{ $type->id }}]" required>
                                                    <option value="monthly" {{ $type->default_frequency == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                                    <option value="quarterly" {{ $type->default_frequency == 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                                                    <option value="yearly" {{ $type->default_frequency == 'yearly' ? 'selected' : '' }}>Yearly / Annual</option>
                                                    <option value="session_wise_admission" {{ $type->default_frequency == 'session_wise_admission' ? 'selected' : '' }}>Session Admission (New Students Only)</option>
                                                    <option value="session_wise_continuing" {{ $type->default_frequency == 'session_wise_continuing' ? 'selected' : '' }}>Continuing Students Only (Old)</option>
                                                    <option value="exam_wise" {{ $type->default_frequency == 'exam_wise' ? 'selected' : '' }}>Exam Wise</option>
                                                    <option value="custom" {{ $type->default_frequency == 'custom' ? 'selected' : '' }}>Custom Months</option>
                                                </select>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-1 month-pills-wrapper">
                                                    <!-- Pills will be rendered here dynamically -->
                                                </div>
                                                <input type="hidden" class="charge-months-input" name="default_charge_months_raw[{{ $type->id }}]" value="{{ is_array($type->default_charge_months) ? implode(',', $type->default_charge_months) : '' }}">
                                            </td>
                                            <td>
                                                <select class="form-select" name="default_late_fee_rule_id[{{ $type->id }}]">
                                                    <option value="">No Late Fee</option>
                                                    @foreach($lateFeeRules as $rule)
                                                        <option value="{{ $rule->id }}" {{ $type->default_late_fee_rule_id == $rule->id ? 'selected' : '' }}>
                                                            {{ $rule->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-primary" id="saveMasterBtn">
                                <i class="fas fa-save me-1"></i> Save Master Defaults
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
    function renderMonthPills($row) {
        const frequency = $row.find('.default-frequency-select').val();
        const $pillsWrapper = $row.find('.month-pills-wrapper');
        const $hiddenInput = $row.find('.charge-months-input');
        
        let pillsHtml = '';
        const storedVal = $hiddenInput.val();
        const activeMonths = storedVal ? storedVal.split(',') : [];
        
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
                const isActive = activeMonths.includes(m.name) || (!storedVal && m.name !== 'June');
                return `<button type="button" class="btn btn-xs btn-sm ${isActive ? 'btn-primary' : 'btn-outline-secondary'} month-pill" data-value="${m.name}">${m.label}</button>`;
            }).join('');
            
        } else if (frequency === 'quarterly') {
            const quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
            pillsHtml = quarters.map(q => {
                const isActive = activeMonths.includes(q) || !storedVal;
                return `<button type="button" class="btn btn-xs btn-sm ${isActive ? 'btn-primary' : 'btn-outline-secondary'} month-pill" data-value="${q}">${q}</button>`;
            }).join('');
        } else if (frequency === 'yearly') {
            pillsHtml = `<button type="button" class="btn btn-xs btn-sm btn-primary month-pill active" data-value="Annual" disabled>Annual</button>`;
        } else if (frequency === 'session_wise_admission') {
            pillsHtml = `<button type="button" class="btn btn-xs btn-sm btn-primary month-pill active" data-value="April" disabled>Admission (April)</button>`;
        } else if (frequency === 'session_wise_continuing') {
            pillsHtml = `<button type="button" class="btn btn-xs btn-sm btn-primary month-pill active" data-value="Annual" disabled>Annual (April)</button>`;
        } else if (frequency === 'exam_wise') {
            pillsHtml = `<button type="button" class="btn btn-xs btn-sm btn-primary month-pill active" data-value="Exam" disabled>Exam Wise</button>`;
        }
        
        $pillsWrapper.html(pillsHtml);
        
        // Re-calculate the actual selected value
        const currentActive = [];
        $pillsWrapper.find('.month-pill.btn-primary').each(function() {
            currentActive.push($(this).data('value'));
        });
        $hiddenInput.val(currentActive.join(','));
    }

    // Initialize all rows
    $('.fee-type-row').each(function() {
        renderMonthPills($(this));
    });

    // Toggle pill state on click
    $(document).on('click', '.month-pill', function() {
        const $pill = $(this);
        const $row = $pill.closest('.fee-type-row');
        const frequency = $row.find('.default-frequency-select').val();
        
        if (frequency === 'yearly' || frequency === 'session_wise_admission' || frequency === 'session_wise_continuing' || frequency === 'exam_wise') return;
        
        if ($pill.hasClass('btn-primary')) {
            $pill.removeClass('btn-primary').addClass('btn-outline-secondary');
        } else {
            $pill.removeClass('btn-outline-secondary').addClass('btn-primary');
        }
        
        const activeValues = [];
        $row.find('.month-pill.btn-primary').each(function() {
            activeValues.push($(this).data('value'));
        });
        $row.find('.charge-months-input').val(activeValues.join(','));
    });

    // Change pills when billing frequency changes
    $(document).on('change', '.default-frequency-select', function() {
        const $row = $(this).closest('.fee-type-row');
        $row.find('.charge-months-input').val(''); // clear stored
        renderMonthPills($row);
    });

    $('#masterForm').submit(function() {
        $('#saveMasterBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');
    });
});
</script>
@endsection
