@extends('layouts.admin')

@section('title', 'Pending Fees')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Pending Fees</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.fees.index') }}">Fee Collection</a></li>
                        <li class="breadcrumb-item active">Pending Fees</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Students with Pending Fees</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Class</th>
                                    <th>Mobile</th>
                                    <th>Pending Amount</th>
                                    <th>Months Due</th>
                                    <th>Due Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pendingFees as $pending)
                                <tr>
                                    <td>{{ $pending['student']->name }}</td>
                                    <td>{{ $pending['class'] }}</td>
                                    <td>{{ $pending['mobile'] }}</td>
                                    <td>₹{{ number_format($pending['pending_amount'], 2) }}</td>
                                    <td>{{ $pending['month'] }}</td>
                                    <td>{{ $pending['due_date'] }}</td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('admin.fees.index') }}?student={{ $pending['student']->id }}" 
                                               class="btn btn-sm btn-primary">Collect Now</a>
                                            <button class="btn btn-sm btn-info whatsapp-reminder-btn" 
                                                    data-student-id="{{ $pending['student']->id }}">
                                                Send Reminder
                                            </button>
                                            <a href="{{ route('admin.fees.student-dashboard', $pending['student']->id) }}" 
                                               class="btn btn-sm btn-secondary">View Details</a>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center">No pending fees found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- WhatsApp Message Modal -->
<div class="modal fade" id="whatsappModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send WhatsApp Reminder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="whatsappMessage" class="mb-3 p-3 bg-light rounded"></div>
                <div class="d-flex gap-2">
                    <button class="btn btn-success" id="copyMessageBtn">Copy Message</button>
                    <button class="btn btn-success" id="openWhatsappBtn">Open WhatsApp Web</button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
$(document).ready(function() {
    $('.whatsapp-reminder-btn').click(function() {
        const studentId = $(this).data('student-id');
        
        $.ajax({
            url: '{{ route("admin.fees.send-whatsapp-reminder") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                student_id: studentId
            },
            success: function(response) {
                if (response.success) {
                    $('#whatsappMessage').text(response.message);
                    $('#openWhatsappBtn').attr('onclick', `window.open('${response.whatsapp_url}', '_blank')`);
                    $('#whatsappModal').modal('show');
                } else {
                    alert('Error sending reminder: ' + response.message);
                }
            },
            error: function(xhr) {
                alert('Error sending reminder: ' + xhr.responseJSON.message);
            }
        });
    });
    
    $('#copyMessageBtn').click(function() {
        const message = $('#whatsappMessage').text();
        navigator.clipboard.writeText(message).then(function() {
            alert('Message copied to clipboard!');
        }).catch(function(err) {
            alert('Failed to copy message: ', err);
        });
    });
});
</script>
@endsection