@extends('layouts.admin')

@section('title', 'Defaulter List')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Defaulter List</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.fees.index') }}">Fee Collection</a></li>
                        <li class="breadcrumb-item active">Defaulters</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Students with 2+ Months Unpaid</h5>
                    <div class="badge bg-danger">{{ $defaulters->count() }} Defaulters</div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Student Name</th>
                                    <th>Class</th>
                                    <th>Father/Guardian Name</th>
                                    <th>Mobile</th>
                                    <th>Pending Amount</th>
                                    <th title="Ageing Buckets: 0-30d / 31-60d / 61-90d / 91d+">Ageing (30d / 60d / 90d / 90d+)</th>
                                    <th>Months Due</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($defaulters as $defaulter)
                                <tr>
                                    <td>{{ $defaulter['student']->name }}</td>
                                    <td>{{ $defaulter['class'] }}</td>
                                    <td>{{ $defaulter['father_name'] }}</td>
                                    <td>{{ $defaulter['mobile'] }}</td>
                                    <td class="fw-bold">₹{{ number_format($defaulter['pending_amount'], 2) }}</td>
                                    <td>
                                        <span class="badge bg-success" title="0-30 days" style="padding: 5px 8px; border-radius: 4px; font-size: 11px; background-color: #198754; color: white;">₹{{ number_format($defaulter['ageing']['30d'], 0) }}</span>
                                        <span class="badge bg-info" title="31-60 days" style="padding: 5px 8px; border-radius: 4px; font-size: 11px; background-color: #0dcaf0; color: #000;">₹{{ number_format($defaulter['ageing']['60d'], 0) }}</span>
                                        <span class="badge bg-warning text-dark" title="61-90 days" style="padding: 5px 8px; border-radius: 4px; font-size: 11px; background-color: #ffc107; color: #000;">₹{{ number_format($defaulter['ageing']['90d'], 0) }}</span>
                                        <span class="badge bg-danger" title="91+ days" style="padding: 5px 8px; border-radius: 4px; font-size: 11px; background-color: #dc3545; color: white;">₹{{ number_format($defaulter['ageing']['120d_plus'], 0) }}</span>
                                    </td>
                                    <td>{{ $defaulter['months_due'] }} months</td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-info whatsapp-reminder-btn" 
                                                    data-student-id="{{ $defaulter['student']->id }}">
                                                Send Reminder
                                            </button>
                                            <a href="tel:{{ $defaulter['mobile'] }}" 
                                               class="btn btn-sm btn-warning">
                                                <i class="fas fa-phone"></i> Call Parent
                                            </a>
                                            <a href="{{ route('admin.fees.index') }}?student={{ $defaulter['student']->id }}" 
                                               class="btn btn-sm btn-primary">
                                                Collect Now
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center">No defaulters found.</td>
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