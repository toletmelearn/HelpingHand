@extends('layouts.teacher')

@section('content')
<div class="container-fluid px-4 py-4">
    <div class="mb-4">
        <a href="{{ route('teacher.leaves.index') }}" class="text-secondary text-decoration-none">
            <i class="bi bi-arrow-left"></i> Back to Leave History
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-lg">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h3 class="card-title text-dark font-weight-bold mb-0">Apply for Leave</h3>
                    <p class="text-secondary mb-0">Fill in details to request leave approval from the administration.</p>
                </div>
                <div class="card-body p-4">
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('teacher.leaves.store') }}" method="POST">
                        @csrf

                        <div class="row g-3 mb-3">
                            <div class="col-md-12">
                                <label for="leave_type" class="form-label text-secondary">Leave Type</label>
                                <select class="form-select text-dark" id="leave_type" name="leave_type" required>
                                    <option value="" disabled selected>Choose a leave type...</option>
                                    <option value="casual_leave">Casual Leave</option>
                                    <option value="medical_leave">Medical Leave</option>
                                    <option value="earned_leave">Earned Leave</option>
                                    <option value="maternity_leave">Maternity Leave</option>
                                    <option value="unpaid_leave">Unpaid Leave</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="start_date" class="form-label text-secondary">Start Date</label>
                                <input type="date" class="form-control text-dark" id="start_date" name="start_date" required>
                            </div>
                            <div class="col-md-6">
                                <label for="end_date" class="form-label text-secondary">End Date</label>
                                <input type="date" class="form-control text-dark" id="end_date" name="end_date" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="reason" class="form-label text-secondary">Reason for Leave</label>
                            <textarea class="form-control text-dark" id="reason" name="reason" rows="5" placeholder="State your reason clearly..." required></textarea>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary px-5 py-2 rounded-pill font-weight-bold shadow-sm">Submit Application</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
