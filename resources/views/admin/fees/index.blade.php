@extends('layouts.admin')

@section('title', 'Fee Collection')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Fee Collection</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Fee Collection</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard Summary Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avatar-sm bg-light rounded p-2">
                                <i class="fas fa-calendar-day text-success font-size-20"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="text-muted mb-1">Today's Collection</p>
                            <h5 class="mb-0">₹{{ number_format($todayCollection, 2) }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avatar-sm bg-light rounded p-2">
                                <i class="fas fa-calendar-week text-info font-size-20"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="text-muted mb-1">Monthly Collection</p>
                            <h5 class="mb-0">₹{{ number_format($monthlyCollection, 2) }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avatar-sm bg-light rounded p-2">
                                <i class="fas fa-file-invoice text-warning font-size-20"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="text-muted mb-1">Pending Fees</p>
                            <h5 class="mb-0">{{ $pendingFees }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avatar-sm bg-light rounded p-2">
                                <i class="fas fa-user-graduate text-primary font-size-20"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="text-muted mb-1">Total Students</p>
                            <h5 class="mb-0">{{ $totalStudents }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Fee Collection Panel</h5>
                </div>
                <div class="card-body">
                    <!-- Student Search Section -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <label for="studentName" class="form-label">Student Name</label>
                            <input type="text" class="form-control" id="studentName" placeholder="Enter student name">
                        </div>
                        <div class="col-md-3">
                            <label for="admissionNo" class="form-label">Admission No</label>
                            <input type="text" class="form-control" id="admissionNo" placeholder="Enter admission number">
                        </div>
                        <div class="col-md-2">
                            <label for="studentClass" class="form-label">Class</label>
                            <select class="form-select" id="studentClass">
                                <option value="">Select Class</option>
                                @foreach($classes as $class)
                                    <option value="{{ $class->id }}">{{ $class->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="studentSection" class="form-label">Section</label>
                            <select class="form-select" id="studentSection">
                                <option value="">Select Section</option>
                                @foreach($sections as $section)
                                    <option value="{{ $section->id }}">{{ $section->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="studentMobile" class="form-label">Mobile</label>
                            <input type="text" class="form-control" id="studentMobile" placeholder="Enter mobile number">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <button class="btn btn-primary" id="searchBtn">
                                <i class="fas fa-search me-1"></i> Search Students
                            </button>
                        </div>
                    </div>

                    <!-- Search Results -->
                    <div id="searchResults" class="mb-4" style="display: none;">
                        <h6>Search Results</h6>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Admission No</th>
                                        <th>Class</th>
                                        <th>Mobile</th>
                                        <th class="text-end">Total Paid</th>
                                        <th class="text-end">Due</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="searchResultsBody">
                                    <!-- Results will be populated here -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Student Fee Dashboard -->
                    <div id="studentFeeDashboard" style="display: none;">
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <p><strong>Student Name:</strong> <span id="dashboardStudentName"></span></p>
                                    </div>
                                    <div class="col-md-3">
                                        <p><strong>Class:</strong> <span id="dashboardStudentClass"></span></p>
                                    </div>
                                    <div class="col-md-3">
                                        <p><strong>Father Name:</strong> <span id="dashboardFatherName"></span></p>
                                    </div>
                                    <div class="col-md-3">
                                        <p><strong>Mobile:</strong> <span id="dashboardMobile"></span></p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3">
                                        <p><strong>Assigned Fee Structure:</strong> <span id="dashboardFeeStructure"></span></p>
                                    </div>
                                    <div class="col-md-3">
                                        <p><strong>Academic Year:</strong> <span id="dashboardAcademicYear"></span></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h5 class="mb-3">Fee Details</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Month</th>
                                        <th>Fee Type</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="feeDetailsBody">
                                    <!-- Fee details will be populated here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Pay Fee Modal -->
<div class="modal fade" id="payFeeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Collect Fee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="payFeeForm">
                    @csrf
                    <input type="hidden" id="modalStudentId" name="student_id">
                    <input type="hidden" id="modalFeeTypeId" name="fee_type_id">
                    <input type="hidden" id="modalMonth" name="month">

                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Student Name</label>
                            <input type="text" class="form-control" id="modalStudentName" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Class</label>
                            <input type="text" class="form-control" id="modalStudentClass" readonly>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label class="form-label">Fee Month</label>
                            <input type="text" class="form-control" id="modalFeeMonth" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fee Head</label>
                            <input type="text" class="form-control" id="modalFeeType" readonly>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label class="form-label">Total Amount</label>
                            <input type="number" class="form-control" id="modalAmount" readonly>
                            <input type="hidden" name="amount" id="hiddenAmount">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Late Fine (Optional)</label>
                            <input type="number" class="form-control" name="late_fine" id="modalLateFine" min="0" value="0">
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label class="form-label">Discount (Optional)</label>
                            <input type="number" class="form-control" name="discount" id="modalDiscount" min="0" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Final Payable</label>
                            <input type="number" class="form-control" id="modalFinalAmount" readonly>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label class="form-label">Payment Mode <span class="text-danger">*</span></label>
                            <select class="form-select" name="payment_mode" required>
                                <option value="">Select Payment Mode</option>
                                <option value="cash">Cash</option>
                                <option value="online">Online</option>
                                <option value="upi">UPI</option>
                                <option value="bank">Bank Transfer</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Transaction ID (if online)</label>
                            <input type="text" class="form-control" name="transaction_id">
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="payment_date" required value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Remarks</label>
                            <input type="text" class="form-control" name="remarks">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="collectFeeBtn">Collect Fee</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Auto-search when admission number is entered (minimum 3 chars)
    $('#admissionNo').on('input', function() {
        const admissionNo = $(this).val().trim();
        if (admissionNo.length >= 3) {
            performSmartSearch();
        } else if (admissionNo.length === 0) {
            // Clear results if field is empty
            $('#searchResultsBody').empty();
            $('#searchResults').hide();
        }
    });
    
    // Search students on button click
    $('#searchBtn').click(function() {
        performSmartSearch();
    });

    // Auto-search the moment a class or section is picked -- no need to
    // press Search or hit Enter.
    $('#studentClass, #studentSection').on('change', function() {
        performSmartSearch();
    });

    // Perform smart search with any combination of fields
    function performSmartSearch() {
        const name = $('#studentName').val().trim();
        const admissionNo = $('#admissionNo').val().trim();
        const classId = $('#studentClass').val();
        const sectionId = $('#studentSection').val();
        const mobile = $('#studentMobile').val().trim();

        // Disable button during search
        $('#searchBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Searching...');

        // Only send data that has values
        let searchData = {};
        if(name) searchData.name = name;
        if(admissionNo) searchData.admission_no = admissionNo;
        if(classId) searchData.class_id = classId;
        if(sectionId) searchData.section_id = sectionId;
        if(mobile) searchData.mobile = mobile;
        
        $.ajax({
            url: '{{ route("admin.fees.search.students") }}',
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: searchData,
            success: function(response) {
                if (response.status) {
                    let html = '';
                    
                    if (response.students && response.students.length > 0) {
                        response.students.forEach(function(student) {
                            const totalPaid = parseFloat(student.total_paid || 0).toFixed(2);
                            const due = parseFloat(student.outstanding_due || 0).toFixed(2);
                            html += `
                                <tr>
                                    <td>${student.name}</td>
                                    <td>${student.admission_no || 'N/A'}</td>
                                    <td>${student.schoolClass?.name || 'N/A'}</td>
                                    <td>${student.mobile || 'N/A'}</td>
                                    <td class="text-end text-success">₹${totalPaid}</td>
                                    <td class="text-end text-danger fw-bold">₹${due}</td>
                                    <td>
                                        <a href="/admin/fees/student/${student.id}/dashboard" class="btn btn-sm btn-primary">Collect Fee</a>
                                    </td>
                                </tr>
                            `;
                        });
                        
                        // Show success message
                        let messageHtml = `<div class="alert alert-info mb-2">
                            <i class="fas fa-info-circle me-1"></i>
                            Found ${response.students.length} student(s)
                        </div>`;
                        
                        $('#searchResultsBody').html(messageHtml + html);
                        $('#searchResults').show();
                        
                        // If only one student found, automatically load their fee dashboard
                        if (response.students.length === 1) {
                            setTimeout(function() {
                                window.location.href = `/admin/fees/student/${response.students[0].id}/dashboard`;
                            }, 1000);
                        }
                    } else {
                        // No results found
                        let noResultsHtml = `<div class="alert alert-warning mb-2">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            No student found
                        </div>`;
                        $('#searchResultsBody').html(noResultsHtml);
                        $('#searchResults').show();
                    }
                } else {
                    let message = response.message || 'Search failed';
                    if (message.includes('No student found')) {
                        let noResultsHtml = `<div class="alert alert-warning mb-2">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            No student found
                        </div>`;
                        $('#searchResultsBody').html(noResultsHtml);
                        $('#searchResults').show();
                    } else {
                        showError(message);
                    }
                }
            },
            error: function(xhr) {
                let errorMsg = 'Error searching students';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMsg = xhr.responseJSON.error;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    errorMsg = Object.values(xhr.responseJSON.errors)[0][0];
                }
                showError(errorMsg);
            },
            complete: function() {
                // Re-enable button after search completes
                $('#searchBtn').prop('disabled', false).html('<i class="fas fa-search me-1"></i> Search Students');
            }
        });
    }
    
    // Error display function
    function showError(message) {
        let errorHtml = `<div class="alert alert-danger mb-2">
            <i class="fas fa-exclamation-circle me-1"></i>
            ${message}
        </div>`;
        $('#searchResultsBody').html(errorHtml);
        $('#searchResults').show();
    }

    // View student dashboard
    $(document).on('click', '.view-dashboard', function() {
        const studentId = $(this).data('student-id');
        
        $.ajax({
            url: `/admin/fees/student/${studentId}/dashboard`,
            type: 'GET',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                // Populate student details
                $('#dashboardStudentName').text(response.student.name);
                $('#dashboardStudentClass').text(response.student.schoolClass?.name || 'N/A');
                $('#dashboardFatherName').text(response.student.guardian_name || 'N/A');
                $('#dashboardMobile').text(response.student.mobile || 'N/A');
                $('#dashboardFeeStructure').text(response.student.feeAssignments[0]?.feeStructure?.class_name || 'N/A');
                $('#dashboardAcademicYear').text(response.student.feeAssignments[0]?.academic_year || 'N/A');

                // Populate fee details
                let html = '';
                response.fee_data.forEach(function(fee) {
                    const statusClass = fee.status === 'Paid' ? 'bg-success' : 
                                      fee.status === 'Partial' ? 'bg-warning' : 'bg-danger';
                    const actionButton = fee.status === 'Paid' ? 
                        `<button class="btn btn-sm btn-info view-receipt" data-receipt-id="${fee.collection_id}">Receipt</button>` :
                        `<a href="/admin/fees/collect/${response.student.id}" class="btn btn-sm btn-success">Collect Fee</a>`;
                    
                    html += `
                        <tr>
                            <td>${fee.month}</td>
                            <td>${fee.fee_type}</td>
                            <td>₹${fee.amount.toFixed(2)}</td>
                            <td><span class="badge ${statusClass}">${fee.status}</span></td>
                            <td>${actionButton}</td>
                        </tr>
                    `;
                });

                $('#feeDetailsBody').html(html);
                $('#studentFeeDashboard').show();
                $('#searchResults').hide();
            },
            error: function(xhr) {
                alert('Error loading student fee dashboard: ' + xhr.responseJSON.message);
            }
        });
    });

    // View receipt button click
    $(document).on('click', '.view-receipt', function() {
        const receiptId = $(this).data('receipt-id');
        window.open(`/admin/fees/receipt/${receiptId}`, '_blank');
    });



    // Calculate final amount when discount or late fine changes
    $('#modalLateFine, #modalDiscount').on('input', function() {
        calculateFinalAmount();
    });

    function calculateFinalAmount() {
        const amount = parseFloat($('#modalAmount').val()) || 0;
        const lateFine = parseFloat($('#modalLateFine').val()) || 0;
        const discount = parseFloat($('#modalDiscount').val()) || 0;
        
        const finalAmount = amount + lateFine - discount;
        $('#modalFinalAmount').val(finalAmount.toFixed(2));
    }


});
</script>
@endsection