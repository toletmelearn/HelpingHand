@extends('layouts.admin')

@section('title', 'Promote Students')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Promote Students</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.student-promotions.store') }}" method="POST">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="from_class">From Class <span class="text-danger">*</span></label>
                                    <select name="from_class" id="from_class" class="form-control @error('from_class') is-invalid @enderror" required>
                                        <option value="">Select Class</option>
                                        @foreach($currentClasses as $class)
                                            <option value="{{ $class->id }}" {{ request('from_class') == $class->id ? 'selected' : '' }}>
                                                {{ $class->name }} ({{ $class->students->count() }} students)
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('from_class')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="to_class">To Class <span class="text-danger">*</span></label>
                                    <select name="to_class" id="to_class" class="form-control @error('to_class') is-invalid @enderror" required>
                                        <option value="">Select Destination Class</option>
                                    </select>
                                    <div id="destination-message" class="text-muted small mt-1"></div>
                                    @error('to_class')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Select Students to Promote <span class="text-danger">*</span></label>
                            <div id="students-list" class="row">
                                <!-- Students will be loaded here via AJAX -->
                                <div class="col-12 text-center">
                                    <p>Please select a class first to load students</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary" id="promote-btn" disabled>Promote Selected Students</button>
                            <a href="{{ route('admin.student-promotions.index') }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#from_class').change(function() {
        var fromClass = $(this).val();
        
        // Clear destination class dropdown
        $('#to_class').html('<option value="">Select Destination Class</option>');
        $('#destination-message').text('');
        
        if(fromClass) {
            // Load students
            $.ajax({
                url: '/admin/student-promotions/class/' + fromClass + '/students',
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    var html = '';
                    if(data.students.length > 0) {
                        $.each(data.students, function(index, student) {
                            html += '<div class="col-md-4 mb-2">';
                            html += '<div class="form-check">';
                            html += '<input class="form-check-input student-checkbox" type="checkbox" name="students[]" value="' + student.id + '" id="student_' + student.id + '">';
                            html += '<label class="form-check-label" for="student_' + student.id + '">';
                            html += student.name + ' (' + student.roll_number + ')';
                            html += '</label>';
                            html += '</div>';
                            html += '</div>';
                        });
                        $('#promote-btn').prop('disabled', false);
                    } else {
                        html = '<div class="col-12"><p>No students found in this class.</p></div>';
                        $('#promote-btn').prop('disabled', true);
                    }
                    $('#students-list').html(html);
                }
            });
            
            // Load destination classes
            $.ajax({
                url: '/admin/student-promotions/destination-classes/' + fromClass,
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    var selectHtml = '<option value="">Select Destination Class</option>';
                    if(data.length > 0) {
                        $.each(data, function(index, classObj) {
                            selectHtml += '<option value="' + classObj.id + '">' + classObj.name + '</option>';
                        });
                        $('#destination-message').text('');
                    } else {
                        selectHtml = '<option value="">No eligible classes found</option>';
                        $('#destination-message').text('No eligible destination classes available');
                    }
                    $('#to_class').html(selectHtml);
                },
                error: function() {
                    $('#to_class').html('<option value="">Error loading classes</option>');
                    $('#destination-message').text('Error loading destination classes');
                }
            });
        } else {
            $('#students-list').html('<div class="col-12 text-center"><p>Please select a class first to load students</p></div>');
            $('#promote-btn').prop('disabled', true);
        }
    });
    
    // Trigger change event if class is pre-selected
    if($('#from_class').val()) {
        $('#from_class').trigger('change');
    }
});
</script>
@endsection
