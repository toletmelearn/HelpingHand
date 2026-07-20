@extends('layouts.admin')

@section('title', 'Promote Students')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Promote Students</h4>
                    <p class="text-muted mb-0">Promote students from one class to another for the academic session</p>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.student-promotions.store') }}" method="POST" id="promotion-form">
                        @csrf
                        
                        <!-- Academic Session Selection -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="academic_session_id">Academic Session <span class="text-danger">*</span></label>
                                    <select name="academic_session_id" id="academic_session_id" class="form-control" required>
                                        <option value="">Select Academic Session</option>
                                        @php
                                            $currentSession = \App\Models\AcademicSession::current()->first();
                                            $allSessions = \App\Models\AcademicSession::orderBy('start_date', 'desc')->get();
                                        @endphp
                                        @foreach($allSessions as $session)
                                            <option value="{{ $session->id }}" {{ $currentSession && $currentSession->id == $session->id ? 'selected' : '' }}>
                                                {{ $session->name }} ({{ \Carbon\Carbon::parse($session->start_date)->format('M Y') }} - {{ \Carbon\Carbon::parse($session->end_date)->format('M Y') }})
                                                @if($currentSession && $currentSession->id == $session->id) - Current @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Class Selection -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="from_class">From Class <span class="text-danger">*</span></label>
                                    <select name="from_class" id="from_class" class="form-control" required>
                                        <option value="">Select Source Class</option>
                                        @foreach($currentClasses as $class)
                                            <option value="{{ $class->id }}" {{ request('from_class') == $class->id ? 'selected' : '' }}>
                                                {{ $class->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="to_class">To Class <span class="text-danger">*</span></label>
                                    <select name="to_class" id="to_class" class="form-control" required disabled>
                                        <option value="">Select Destination Class</option>
                                    </select>
                                    <div id="destination-message" class="text-muted small mt-1">Select "From Class" first to enable this field</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Student Selection Section -->
                        <div class="form-group">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="mb-0">Select Students to Promote <span class="text-danger">*</span></label>
                                <div>
                                    <span id="student-count" class="badge badge-info mr-2">0 of 0 students selected</span>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="select-all-btn" style="display:none;">
                                        <i class="fas fa-check-square"></i> Select All
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="deselect-all-btn" style="display:none;">
                                        <i class="fas fa-square"></i> Deselect All
                                    </button>
                                </div>
                            </div>
                            
                            <div id="students-list" class="border rounded p-3 bg-light" style="min-height: 150px;">
                                <!-- Students will be loaded here via AJAX -->
                                <div class="col-12 text-center text-muted">
                                    <i class="fas fa-info-circle"></i> Please select a class first to load students
                                </div>
                            </div>
                            <div id="loading-students" class="text-center py-4" style="display:none;">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                                <p class="mt-2 text-muted"><i class="fas fa-spinner fa-spin"></i> Loading students...</p>
                            </div>
                        </div>
                        
                        <!-- Remarks Field -->
                        <div class="form-group">
                            <label for="remarks">Remarks / Notes (Optional)</label>
                            <textarea name="remarks" id="remarks" class="form-control" rows="3" placeholder="Add any notes or remarks about this promotion..."></textarea>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="form-group mt-4">
                            <button type="submit" class="btn btn-primary btn-lg" id="promote-btn" disabled>
                                <i class="fas fa-arrow-up"></i> Promote Selected Students
                            </button>
                            <a href="{{ route('admin.student-promotions.index') }}" class="btn btn-secondary btn-lg">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmationModal" tabindex="-1" role="dialog" aria-labelledby="confirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="confirmationModalLabel">
                    <i class="fas fa-exclamation-triangle"></i> Confirm Student Promotion
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="lead">Are you sure you want to promote the selected students?</p>
                <table class="table table-sm table-bordered">
                    <tr>
                        <th width="40%">Academic Session:</th>
                        <td id="confirm-session"></td>
                    </tr>
                    <tr>
                        <th>From Class:</th>
                        <td id="confirm-from-class"></td>
                    </tr>
                    <tr>
                        <th>To Class:</th>
                        <td id="confirm-to-class"></td>
                    </tr>
                    <tr>
                        <th>Students Selected:</th>
                        <td id="confirm-student-count" class="font-weight-bold text-primary"></td>
                    </tr>
                </table>
                <div class="alert alert-warning mb-0">
                    <i class="fas fa-info-circle"></i> This action will update the class for all selected students and create a promotion log.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="confirm-promotion-btn">
                    <i class="fas fa-check"></i> Confirm Promotion
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Simple direct fix for Student Promotion functionality
(function() {
    'use strict';
    
    // Simple function to enable To Class dropdown
    function enableToClassDropdown() {
        const toClass = document.getElementById('to_class');
        const message = document.getElementById('destination-message');
        
        if (toClass) {
            toClass.disabled = false;
            console.log('To Class dropdown enabled');
            if (message) {
                message.innerHTML = '<i class="fas fa-info-circle"></i> Please select a destination class';
            }
        }
    }
    
    // Simple function to handle From Class change
    function handleFromClassChange() {
        const fromClass = document.getElementById('from_class');
        const selectedValue = fromClass.value;
        
        console.log('From Class changed to:', selectedValue);
        
        if (selectedValue) {
            // Enable To Class dropdown immediately
            enableToClassDropdown();
        }
    }
    
    // Wait for DOM to be fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Student Promotion Critical Fix: DOM loaded');
        
        // Check if we're on the right page
        if (!document.querySelector('#from_class') || !document.querySelector('#to_class')) {
            console.log('Student Promotion Critical Fix: Required elements not found');
            return;
        }
        
        // Function to enable the To Class dropdown
        function enableToClass() {
            const toClass = document.getElementById('to_class');
            const destinationMessage = document.getElementById('destination-message');
            
            if (toClass) {
                toClass.disabled = false;
                console.log('Student Promotion Critical Fix: To Class dropdown enabled');
                
                if (destinationMessage) {
                    destinationMessage.innerHTML = '<i class="fas fa-info-circle"></i> Please select a destination class';
                }
            }
        }
        
        // Function to handle From Class selection
        function handleFromClassChange() {
            const fromClass = document.getElementById('from_class');
            const toClass = document.getElementById('to_class');
            const destinationMessage = document.getElementById('destination-message');
            
            if (!fromClass || !toClass) return;
            
            const selectedValue = fromClass.value;
            console.log('Student Promotion Critical Fix: From Class changed to', selectedValue);
            
            if (selectedValue) {
                // Enable the To Class dropdown immediately
                enableToClass();
                
                // Update message
                if (destinationMessage) {
                    destinationMessage.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading destination classes...';
                }
                
                // Trigger AJAX requests
                loadDestinationClasses(selectedValue);
                loadStudents(selectedValue);
            } else {
                // Disable To Class if no From Class selected
                toClass.disabled = true;
                if (destinationMessage) {
                    destinationMessage.innerHTML = 'Select "From Class" first to enable this field';
                }
            }
        }
        
        // Simple AJAX function
        function makeAjaxRequest(url, successCallback, errorCallback) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            
            if (!csrfToken) {
                console.error('Student Promotion Critical Fix: CSRF token not found');
                if (errorCallback) errorCallback('CSRF token missing');
                return;
            }
            
            fetch(url, {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (successCallback) successCallback(data);
            })
            .catch(error => {
                console.error('Student Promotion Critical Fix: AJAX Error:', error);
                if (errorCallback) errorCallback(error.message);
            });
        }
        
        // Function to load destination classes via AJAX
        function loadDestinationClasses(fromClassId) {
            const url = `/admin/student-promotions/destination-classes/${fromClassId}`;
            
            makeAjaxRequest(url, 
                function(data) {
                    console.log('Student Promotion Critical Fix: Destination classes loaded', data);
                    populateDestinationClasses(Array.isArray(data) ? data : []);
                },
                function(error) {
                    console.error('Student Promotion Critical Fix: Error loading destination classes', error);
                    // Still enable the dropdown even on error
                    enableToClass();
                    showErrorMessage('Error loading destination classes: ' + error);
                }
            );
        }
        
        // Function to load students via AJAX
        function loadStudents(fromClassId) {
            const url = `/admin/student-promotions/class/${fromClassId}/students`;
            
            makeAjaxRequest(url,
                function(data) {
                    console.log('Student Promotion Critical Fix: Students loaded', data);
                    populateStudents(data.students || []);
                },
                function(error) {
                    console.error('Student Promotion Critical Fix: Error loading students', error);
                    showStudentError('Error loading students: ' + error);
                }
            );
        }
        
        // Function to populate destination classes dropdown
        function populateDestinationClasses(classes) {
            const toClass = document.getElementById('to_class');
            const destinationMessage = document.getElementById('destination-message');
            
            if (!toClass) return;
            
            // Clear existing options
            toClass.innerHTML = '<option value="">Select Destination Class</option>';
            
            // Add new options
            if (Array.isArray(classes) && classes.length > 0) {
                classes.forEach(cls => {
                    const option = document.createElement('option');
                    option.value = cls.id;
                    option.textContent = cls.name;
                    toClass.appendChild(option);
                });
                
                if (destinationMessage) {
                    destinationMessage.innerHTML = `<i class="fas fa-check-circle text-success"></i> ${classes.length} destination classes available`;
                }
            } else {
                if (destinationMessage) {
                    destinationMessage.innerHTML = '<i class="fas fa-info-circle text-info"></i> No destination classes found';
                }
            }
        }
        
        // Function to populate students list
        function populateStudents(students) {
            const studentsList = document.getElementById('students-list');
            const loadingStudents = document.getElementById('loading-students');
            const selectAllBtn = document.getElementById('select-all-btn');
            const deselectAllBtn = document.getElementById('deselect-all-btn');
            
            if (!studentsList) return;
            
            if (loadingStudents) {
                loadingStudents.style.display = 'none';
            }
            
            if (Array.isArray(students) && students.length > 0) {
                let html = '<div class="row">';
                students.forEach(student => {
                    html += `
                        <div class="col-md-4 col-sm-6 mb-2">
                            <div class="card student-card" style="border: 1px solid #dee2e6;">
                                <div class="card-body p-2">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input student-checkbox" type="checkbox" name="students[]" value="${student.id}" id="student_${student.id}">
                                        <label class="form-check-label d-block" for="student_${student.id}">
                                            <strong>${student.name}</strong><br>
                                            <small class="text-muted">Roll: ${student.roll_number}</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                
                studentsList.innerHTML = html;
                studentsList.style.display = 'block';
                
                if (selectAllBtn) selectAllBtn.style.display = 'inline-block';
                if (deselectAllBtn) deselectAllBtn.style.display = 'inline-block';
                
                // Add event listeners to checkboxes
                document.querySelectorAll('.student-checkbox').forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const card = this.closest('.student-card');
                        if (this.checked) {
                            card.classList.add('selected');
                        } else {
                            card.classList.remove('selected');
                        }
                        updateStudentCount();
                    });
                });
                
            } else {
                studentsList.innerHTML = '<div class="col-12 text-center text-muted"><i class="fas fa-users-slash"></i> No students found.</div>';
                studentsList.style.display = 'block';
                
                if (selectAllBtn) selectAllBtn.style.display = 'none';
                if (deselectAllBtn) deselectAllBtn.style.display = 'none';
            }
        }
        
        // Function to update student count
        function updateStudentCount() {
            const checkboxes = document.querySelectorAll('.student-checkbox');
            const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
            const totalCount = checkboxes.length;
            
            const countElement = document.getElementById('student-count');
            if (countElement) {
                countElement.textContent = `${checkedCount} of ${totalCount} students selected`;
            }
            
            // Enable/disable promote button
            const promoteBtn = document.getElementById('promote-btn');
            const toClass = document.getElementById('to_class');
            const academicSession = document.getElementById('academic_session_id');
            
            if (promoteBtn && toClass && academicSession) {
                const hasStudents = checkedCount > 0;
                const hasToClass = toClass.value !== '';
                const hasSession = academicSession.value !== '';
                
                promoteBtn.disabled = !(hasStudents && hasToClass && hasSession);
            }
        }
        
        // Function to show error messages
        function showErrorMessage(message) {
            const destinationMessage = document.getElementById('destination-message');
            if (destinationMessage) {
                destinationMessage.innerHTML = `<i class="fas fa-exclamation-triangle text-warning"></i> ${message}`;
            }
        }
        
        function showStudentError(message) {
            const studentsList = document.getElementById('students-list');
            const loadingStudents = document.getElementById('loading-students');
            
            if (loadingStudents) {
                loadingStudents.style.display = 'none';
            }
            
            if (studentsList) {
                studentsList.innerHTML = `<div class="text-danger">${message}</div>`;
                studentsList.style.display = 'block';
            }
        }
        
        // Bind event listeners
        const fromClass = document.getElementById('from_class');
        if (fromClass) {
            fromClass.addEventListener('change', handleFromClassChange);
            console.log('From Class change event bound');
            
            // Check if pre-selected on load
            if (fromClass.value) {
                console.log('From Class pre-selected on load:', fromClass.value);
                handleFromClassChange();
            }
        }
        
        // Bind select all/deselect all buttons
        const selectAllBtn = document.getElementById('select-all-btn');
        const deselectAllBtn = document.getElementById('deselect-all-btn');
        
        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function() {
                document.querySelectorAll('.student-checkbox').forEach(checkbox => {
                    checkbox.checked = true;
                    checkbox.closest('.student-card').classList.add('selected');
                });
                updateStudentCount();
            });
        }
        
        if (deselectAllBtn) {
            deselectAllBtn.addEventListener('click', function() {
                document.querySelectorAll('.student-checkbox').forEach(checkbox => {
                    checkbox.checked = false;
                    checkbox.closest('.student-card').classList.remove('selected');
                });
                updateStudentCount();
            });
        }
        
        // Bind other change events
        const toClass = document.getElementById('to_class');
        const academicSession = document.getElementById('academic_session_id');
        
        if (toClass) {
            toClass.addEventListener('change', updateStudentCount);
        }
        
        if (academicSession) {
            academicSession.addEventListener('change', updateStudentCount);
        }
        
        // Form submission with confirmation
        const promotionForm = document.getElementById('promotion-form');
        const confirmationModal = document.getElementById('confirmationModal');
        const confirmPromotionBtn = document.getElementById('confirm-promotion-btn');
        
        if (promotionForm) {
            promotionForm.addEventListener('submit', function(e) {
                e.preventDefault();
                console.log('Student Promotion Critical Fix: Form submission attempt');
                
                const selectedCount = document.querySelectorAll('.student-checkbox:checked').length;
                if (selectedCount === 0) {
                    alert('Please select at least one student to promote.');
                    return false;
                }
                
                // Populate confirmation modal
                const sessionText = document.querySelector('#academic_session_id option:checked').textContent;
                const fromClassText = document.querySelector('#from_class option:checked').textContent;
                const toClassText = document.querySelector('#to_class option:checked').textContent;
                
                document.getElementById('confirm-session').textContent = sessionText;
                document.getElementById('confirm-from-class').textContent = fromClassText;
                document.getElementById('confirm-to-class').textContent = toClassText;
                document.getElementById('confirm-student-count').textContent = selectedCount + ' student' + (selectedCount > 1 ? 's' : '');
                
                // Show confirmation modal (using Bootstrap's modal method)
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    const modal = new bootstrap.Modal(confirmationModal);
                    modal.show();
                } else {
                    // Fallback for older Bootstrap versions
                    $(confirmationModal).modal('show');
                }
            });
        }
        
        if (confirmPromotionBtn) {
            confirmPromotionBtn.addEventListener('click', function() {
                // Hide modal and submit form
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    const modal = bootstrap.Modal.getInstance(confirmationModal);
                    if (modal) modal.hide();
                } else {
                    $(confirmationModal).modal('hide');
                }
                console.log('Student Promotion Critical Fix: Submitting promotion form...');
                promotionForm.submit();
            });
        }
        
        console.log('All event listeners bound successfully');
    });
})();
</script>

<style>
.form-check-label {
    cursor: pointer;
    user-select: none;
}
.badge-info {
    font-size: 0.9rem;
    padding: 0.4rem 0.8rem;
}
#students-list {
    max-height: 400px;
    overflow-y: auto;
}
.student-card {
    transition: all 0.2s ease-in-out;
}
.student-card.selected {
    border: 2px solid #007bff !important;
    background-color: #f8f9ff;
}
</style>
@endsection