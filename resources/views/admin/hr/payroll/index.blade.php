@extends('layouts.admin')

@section('content')
<div class="container-fluid px-4 py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h2 text-dark font-weight-bold">Payroll & Salaries</h1>
            <p class="text-secondary">Manage teacher salary pay scales, allowances, deductions, and print salary slips.</p>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-primary px-4 py-2 rounded-pill font-weight-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#generatePayrollModal">
                <i class="bi bi-wallet2"></i> Generate Payroll Payout
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0 rounded-lg mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h5 class="card-title text-dark font-weight-bold mb-0">Salary Log</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-secondary font-weight-bold text-uppercase" style="font-size: 0.8rem;">
                        <tr>
                            <th class="ps-4 py-3">Teacher</th>
                            <th class="py-3">Period</th>
                            <th class="py-3">Pay Scale</th>
                            <th class="py-3 text-end">Basic Salary</th>
                            <th class="py-3 text-end">Gross Salary</th>
                            <th class="py-3 text-end">Deductions</th>
                            <th class="py-3 text-end">Net Salary</th>
                            <th class="py-3">Payment Info</th>
                            <th class="py-3 text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($salaries as $salary)
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; font-weight: 600;">
                                            {{ strtoupper(substr($salary->teacher->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <h6 class="mb-0 text-dark font-weight-bold">{{ $salary->teacher->name }}</h6>
                                            <small class="text-secondary">Emp ID: {{ $salary->teacher->employee_id ?? 'N/A' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($salary->pay_month && $salary->pay_year)
                                        <span class="text-dark">{{ \Carbon\Carbon::create($salary->pay_year, $salary->pay_month, 1)->format('M Y') }}</span>
                                    @else
                                        <span class="text-secondary">&mdash;</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-dark font-weight-bold">{{ $salary->pay_scale ?? 'Standard' }}</span>
                                </td>
                                <td class="text-end text-dark font-weight-bold">₹{{ number_format($salary->basic_salary, 2) }}</td>
                                <td class="text-end text-dark font-weight-bold">₹{{ number_format($salary->gross_salary, 2) }}</td>
                                <td class="text-end text-danger">
                                    ₹{{ number_format(($salary->pf_amount ?? 0) + ($salary->esi_amount ?? 0) + ($salary->tax_deduction ?? 0) + ($salary->other_deductions ?? 0) + ($salary->attendance_deduction_amount ?? 0), 2) }}
                                    @if($salary->attendance_deduction_days > 0)
                                        <br><small class="text-secondary">incl. {{ rtrim(rtrim(number_format($salary->attendance_deduction_days, 2), '0'), '.') }} day(s) attendance</small>
                                    @endif
                                </td>
                                <td class="text-end text-success font-weight-bold">₹{{ number_format($salary->net_salary, 2) }}</td>
                                <td>
                                    <div>
                                        <span class="badge bg-success-light text-success px-2 py-1 text-uppercase" style="background-color: #e8f5e9;">{{ $salary->payment_status }}</span>
                                    </div>
                                    <small class="text-secondary">{{ $salary->payment_method }} | {{ $salary->payment_date ? $salary->payment_date->format('M d, Y') : $salary->created_at->format('M d, Y') }}</small>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="{{ route('admin.hr.payroll.pdf', $salary->id) }}" class="btn btn-sm btn-outline-danger px-3 rounded-pill">
                                        <i class="bi bi-file-earmark-pdf"></i> Slip PDF
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <div class="text-secondary mb-3">
                                        <i class="bi bi-wallet" style="font-size: 3rem;"></i>
                                    </div>
                                    <h5 class="text-dark font-weight-bold">No Payroll Records</h5>
                                    <p class="text-secondary mb-0">Generate a monthly salary payout to record payroll transactions.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($salaries->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $salaries->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Generate Payroll Modal -->
<div class="modal fade" id="generatePayrollModal" tabindex="-1" aria-labelledby="generatePayrollModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0 py-3 px-4">
                <h5 class="modal-title font-weight-bold" id="generatePayrollModalLabel">Generate Salary Payout</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-dark">
                <form action="{{ route('admin.hr.payroll.generate') }}" method="POST">
                    @csrf

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="teacher_id" class="form-label text-secondary">Teacher</label>
                            <select class="form-select text-dark" id="teacher_id" name="teacher_id" required>
                                <option value="" disabled selected>Select Teacher...</option>
                                @foreach($teachers as $teacher)
                                    <option value="{{ $teacher->id }}" data-salary="{{ $teacher->salary ?? 0 }}">{{ $teacher->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="pay_scale" class="form-label text-secondary">Pay Scale Code</label>
                            <input type="text" class="form-control text-dark" id="pay_scale" name="pay_scale" placeholder="e.g. GRADE-A">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="pay_month" class="form-label text-secondary">Pay Period &mdash; Month</label>
                            <select class="form-select text-dark" id="pay_month" name="pay_month" required>
                                @foreach(['January','February','March','April','May','June','July','August','September','October','November','December'] as $i => $m)
                                    <option value="{{ $i + 1 }}" {{ (now()->month == $i + 1) ? 'selected' : '' }}>{{ $m }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="pay_year" class="form-label text-secondary">Pay Period &mdash; Year</label>
                            <input type="number" class="form-control text-dark" id="pay_year" name="pay_year" value="{{ now()->year }}" min="2000" max="2100" required>
                        </div>
                    </div>

                    <h6 class="font-weight-bold text-primary mt-4 mb-2">Earnings & Allowances</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="basic_salary" class="form-label text-secondary">Basic Salary (₹)</label>
                            <input type="number" step="0.01" class="form-control text-dark" id="basic_salary" name="basic_salary" required>
                        </div>
                        <div class="col-md-4">
                            <label for="hra" class="form-label text-secondary">HRA (₹)</label>
                            <input type="number" step="0.01" class="form-control text-dark" id="hra" name="hra" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="da" class="form-label text-secondary">DA (₹)</label>
                            <input type="number" step="0.01" class="form-control text-dark" id="da" name="da" value="0">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="ta" class="form-label text-secondary">TA (₹)</label>
                            <input type="number" step="0.01" class="form-control text-dark" id="ta" name="ta" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="medical_allowance" class="form-label text-secondary">Medical (₹)</label>
                            <input type="number" step="0.01" class="form-control text-dark" id="medical_allowance" name="medical_allowance" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="special_allowance" class="form-label text-secondary">Special Allowance (₹)</label>
                            <input type="number" step="0.01" class="form-control text-dark" id="special_allowance" name="special_allowance" value="0">
                        </div>
                    </div>

                    <h6 class="font-weight-bold text-danger mt-4 mb-2">Deductions</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label for="pf_amount" class="form-label text-secondary">PF Amount (₹)</label>
                            <input type="number" step="0.01" class="form-control text-dark" id="pf_amount" name="pf_amount" value="0">
                        </div>
                        <div class="col-md-3">
                            <label for="esi_amount" class="form-label text-secondary">ESI Amount (₹)</label>
                            <input type="number" step="0.01" class="form-control text-dark" id="esi_amount" name="esi_amount" value="0">
                        </div>
                        <div class="col-md-3">
                            <label for="tax_deduction" class="form-label text-secondary">Tax Deducted (₹)</label>
                            <input type="number" step="0.01" class="form-control text-dark" id="tax_deduction" name="tax_deduction" value="0">
                        </div>
                        <div class="col-md-3">
                            <label for="other_deductions" class="form-label text-secondary">Other Deductions (₹)</label>
                            <input type="number" step="0.01" class="form-control text-dark" id="other_deductions" name="other_deductions" value="0">
                        </div>
                    </div>

                    <h6 class="font-weight-bold text-danger mt-4 mb-2">Attendance-Based Deduction</h6>
                    <div class="row g-3 mb-2 align-items-end">
                        <div class="col-md-4">
                            <label for="attendance_deduction_days" class="form-label text-secondary">Deduction Days</label>
                            <input type="number" step="0.5" min="0" class="form-control text-dark" id="attendance_deduction_days" name="attendance_deduction_days" value="0">
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-outline-primary w-100" id="calcAttendanceBtn">
                                <i class="bi bi-calculator"></i> Calculate from Attendance
                            </button>
                        </div>
                        <div class="col-md-4">
                            <small class="text-secondary d-block">Deduction Amount</small>
                            <span class="fw-bold text-danger" id="attendance_deduction_amount_display">₹0.00</span>
                        </div>
                    </div>
                    <div id="attendanceBreakdown" class="small text-secondary mb-3" style="display:none;"></div>

                    <h6 class="font-weight-bold text-dark mt-4 mb-2">Payment Details</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="payment_method" class="form-label text-secondary">Payment Method</label>
                            <select class="form-select text-dark" id="payment_method" name="payment_method" required>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cash">Cash</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="reference_number" class="form-label text-secondary">Reference No / Cheque No</label>
                            <input type="text" class="form-control text-dark" id="reference_number" name="reference_number" placeholder="Txn Ref No">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="remarks" class="form-label text-secondary">Remarks / Office Notes</label>
                        <input type="text" class="form-control text-dark" id="remarks" name="remarks">
                    </div>

                    <!-- Live Payroll Summary -->
                    <div class="card bg-light border-0 mb-4 rounded-lg">
                        <div class="card-body p-3">
                            <div class="row text-center">
                                <div class="col-4 border-end">
                                    <small class="text-secondary d-block">Gross Earnings</small>
                                    <span class="h5 font-weight-bold text-dark" id="est_gross">₹0.00</span>
                                </div>
                                <div class="col-4 border-end">
                                    <small class="text-secondary d-block">Total Deductions</small>
                                    <span class="h5 font-weight-bold text-danger" id="est_deductions">₹0.00</span>
                                </div>
                                <div class="col-4">
                                    <small class="text-secondary d-block">Net Payout</small>
                                    <span class="h5 font-weight-bold text-success" id="est_net">₹0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="button" class="btn btn-outline-secondary px-4 py-2 rounded-pill font-weight-bold me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-5 py-2 rounded-pill font-weight-bold shadow-sm">Process Payout</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const teacherSelect = document.getElementById('teacher_id');
    const basicSalaryInput = document.getElementById('basic_salary');
    
    const hraInput = document.getElementById('hra');
    const daInput = document.getElementById('da');
    const taInput = document.getElementById('ta');
    const medicalInput = document.getElementById('medical_allowance');
    const specialInput = document.getElementById('special_allowance');
    
    const pfInput = document.getElementById('pf_amount');
    const esiInput = document.getElementById('esi_amount');
    const taxInput = document.getElementById('tax_deduction');
    const otherInput = document.getElementById('other_deductions');
    const attendanceDaysInput = document.getElementById('attendance_deduction_days');
    const attendanceAmountDisplay = document.getElementById('attendance_deduction_amount_display');
    const attendanceBreakdown = document.getElementById('attendanceBreakdown');
    const calcAttendanceBtn = document.getElementById('calcAttendanceBtn');
    const payMonthSelect = document.getElementById('pay_month');
    const payYearInput = document.getElementById('pay_year');

    const estGross = document.getElementById('est_gross');
    const estDeductions = document.getElementById('est_deductions');
    const estNet = document.getElementById('est_net');

    // Retrieve default configuration ratios from Admin Settings
    const hraPct = parseFloat("{{ $hraPercent }}") || 10.00;
    const daPct = parseFloat("{{ $daPercent }}") || 5.00;
    const taPct = parseFloat("{{ $taPercent }}") || 2.00;
    const medicalPct = parseFloat("{{ $medicalPercent }}") || 1.50;
    const pfPct = parseFloat("{{ $pfPercent }}") || 12.00;
    const esiPct = parseFloat("{{ $esiPercent }}") || 0.75;

    function calculateSalary() {
        const basic = parseFloat(basicSalaryInput.value) || 0;
        
        // Auto-calculate allowances based on configured percentages
        const hra = Math.round(basic * (hraPct / 100) * 100) / 100;
        const da = Math.round(basic * (daPct / 100) * 100) / 100;
        const ta = Math.round(basic * (taPct / 100) * 100) / 100;
        const medical = Math.round(basic * (medicalPct / 100) * 100) / 100;
        
        // Update input fields
        hraInput.value = hra.toFixed(2);
        daInput.value = da.toFixed(2);
        taInput.value = ta.toFixed(2);
        medicalInput.value = medical.toFixed(2);
        
        // Auto-calculate PF deduction based on configured percentage
        const pf = Math.round(basic * (pfPct / 100) * 100) / 100;
        
        // ESI is applied on gross salary if gross <= 21000
        const gross = basic + hra + da + ta + medical + (parseFloat(specialInput.value) || 0);
        const esi = gross <= 21000 ? Math.round(gross * (esiPct / 100) * 100) / 100 : 0;
        
        // Tax (TDS) estimation: 10% if basic > 50000, 5% if basic > 30000, else 0
        let tax = 0;
        if (basic > 50000) {
            tax = Math.round(basic * 0.10 * 100) / 100;
        } else if (basic > 30000) {
            tax = Math.round(basic * 0.05 * 100) / 100;
        }
        
        pfInput.value = pf.toFixed(2);
        esiInput.value = esi.toFixed(2);
        taxInput.value = tax.toFixed(2);
        
        updateLiveSummary();
    }

    function attendanceDeductionAmount(gross) {
        const days = parseFloat(attendanceDaysInput.value) || 0;
        const perDayRate = gross / 30;
        return Math.round(days * perDayRate * 100) / 100;
    }

    function updateLiveSummary() {
        const basic = parseFloat(basicSalaryInput.value) || 0;
        const hra = parseFloat(hraInput.value) || 0;
        const da = parseFloat(daInput.value) || 0;
        const ta = parseFloat(taInput.value) || 0;
        const medical = parseFloat(medicalInput.value) || 0;
        const special = parseFloat(specialInput.value) || 0;

        const pf = parseFloat(pfInput.value) || 0;
        const esi = parseFloat(esiInput.value) || 0;
        const tax = parseFloat(taxInput.value) || 0;
        const other = parseFloat(otherInput.value) || 0;

        const gross = basic + hra + da + ta + medical + special;
        const attendanceDeduction = attendanceDeductionAmount(gross);
        const deductions = pf + esi + tax + other + attendanceDeduction;
        const net = Math.max(0, gross - deductions);

        attendanceAmountDisplay.textContent = '₹' + attendanceDeduction.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        estGross.textContent = '₹' + gross.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        estDeductions.textContent = '₹' + deductions.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        estNet.textContent = '₹' + net.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Fetch attendance/leave-based deduction days for the selected teacher +
    // pay period. Populates attendance_deduction_days but leaves it fully
    // editable -- the admin can override before submitting.
    function calculateAttendanceDeduction() {
        const teacherId = teacherSelect.value;
        const month = payMonthSelect.value;
        const year = payYearInput.value;

        if (!teacherId || !month || !year) {
            alert('Select a teacher and pay period first.');
            return;
        }

        calcAttendanceBtn.disabled = true;
        calcAttendanceBtn.textContent = 'Calculating...';

        const url = "{{ route('admin.hr.payroll.preview-deduction') }}" +
            '?teacher_id=' + encodeURIComponent(teacherId) +
            '&pay_month=' + encodeURIComponent(month) +
            '&pay_year=' + encodeURIComponent(year);

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(response => response.json())
            .then(data => {
                attendanceDaysInput.value = data.total_deduction_days;
                attendanceBreakdown.style.display = 'block';
                attendanceBreakdown.textContent =
                    `Unpaid leave: ${data.unpaid_leave_days} day(s) | Unmarked absences: ${data.absent_days} day(s) | ` +
                    `Half-days: ${data.half_days} | Late marks: ${data.late_count} (${data.late_deduction_days} day(s) deducted)`;
                updateLiveSummary();
            })
            .catch(() => {
                alert('Could not calculate attendance deduction. You can still enter it manually.');
            })
            .finally(() => {
                calcAttendanceBtn.disabled = false;
                calcAttendanceBtn.innerHTML = '<i class="bi bi-calculator"></i> Calculate from Attendance';
            });
    }

    calcAttendanceBtn.addEventListener('click', calculateAttendanceDeduction);
    attendanceDaysInput.addEventListener('input', updateLiveSummary);

    // Event listener for teacher selection
    teacherSelect.addEventListener('change', function () {
        const selectedOption = teacherSelect.options[teacherSelect.selectedIndex];
        const salary = parseFloat(selectedOption.getAttribute('data-salary')) || 0;
        basicSalaryInput.value = salary.toFixed(2);
        calculateSalary();
    });

    // Event listeners to update totals live if user edits inputs manually
    [basicSalaryInput, hraInput, daInput, taInput, medicalInput, specialInput, pfInput, esiInput, taxInput, otherInput].forEach(input => {
        input.addEventListener('input', updateLiveSummary);
    });
    
    // Trigger calculation when basic salary is manually changed
    basicSalaryInput.addEventListener('change', calculateSalary);
});
</script>
@endsection
