@extends('layouts.admin')

@section('title', 'Log Courier')

@section('content')
<div class="container-fluid py-4">
    <!-- Breadcrumb / Header -->
    <div class="mb-4">
        <a href="{{ route('admin.front-office.couriers.index') }}" class="btn btn-sm btn-outline-secondary me-2">
            <i class="bi bi-arrow-left"></i> Back to Register
        </a>
        <h1 class="h3 d-inline-block align-middle mb-0 mt-2 mt-md-0">Log Courier / Package</h1>
    </div>

    <div class="card border-0 shadow-sm bg-white">
        <div class="card-body">
            <form action="{{ route('admin.front-office.couriers.store') }}" method="POST" enctype="multipart/form-data" id="courier-form">
                @csrf

                <div class="row g-3">
                    <!-- Tracking Number -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Courier Tracking Number <span class="text-danger">*</span></label>
                        <input type="text" name="tracking_number" class="form-control" placeholder="AWB / Ref Number" value="{{ old('tracking_number') }}" required>
                    </div>

                    <!-- Courier Company -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Courier Company Name <span class="text-danger">*</span></label>
                        <input type="text" name="courier_company" class="form-control" placeholder="e.g. DHL, FedEx, Speed Post" value="{{ old('courier_company') }}" required>
                    </div>

                    <!-- Direction -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Courier Direction <span class="text-danger">*</span></label>
                        <select name="courier_type" class="form-select" required>
                            <option value="incoming" {{ old('courier_type') === 'incoming' || is_null(old('courier_type')) ? 'selected' : '' }}>Incoming / Inbound</option>
                            <option value="outgoing" {{ old('courier_type') === 'outgoing' ? 'selected' : '' }}>Outgoing / Outbound</option>
                        </select>
                    </div>

                    <!-- Parcel Type -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Parcel Type <span class="text-danger">*</span></label>
                        <select name="parcel_type" class="form-select" required>
                            <option value="document" {{ old('parcel_type') === 'document' ? 'selected' : '' }}>Document / Letter</option>
                            <option value="package" {{ old('parcel_type') === 'package' ? 'selected' : '' }}>Package / Box</option>
                            <option value="registered_post" {{ old('parcel_type') === 'registered_post' ? 'selected' : '' }}>Registered Post</option>
                            <option value="speed_post" {{ old('parcel_type') === 'speed_post' ? 'selected' : '' }}>Speed Post</option>
                            <option value="regular" {{ old('parcel_type') === 'regular' || is_null(old('parcel_type')) ? 'selected' : '' }}>Regular Post</option>
                        </select>
                    </div>

                    <!-- Status -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Delivery Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-select" required>
                            <option value="pending" {{ old('status') === 'pending' || is_null(old('status')) ? 'selected' : '' }}>Pending / Received at Desk</option>
                            <option value="delivered" {{ old('status') === 'delivered' ? 'selected' : '' }}>Delivered to Recipient</option>
                            <option value="returned" {{ old('status') === 'returned' ? 'selected' : '' }}>Returned to Sender</option>
                            <option value="lost" {{ old('status') === 'lost' ? 'selected' : '' }}>Lost</option>
                        </select>
                    </div>

                    <!-- Sender -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Sender Details <span class="text-danger">*</span></label>
                        <input type="text" name="sender" class="form-control" placeholder="Name & Address of Sender" value="{{ old('sender') }}" required>
                    </div>

                    <!-- Receiver -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Receiver Details <span class="text-danger">*</span></label>
                        <input type="text" name="receiver" class="form-control" placeholder="Name & Address of Receiver" value="{{ old('receiver') }}" required>
                    </div>

                    <!-- Recipient Staff User -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Target Staff Recipient</label>
                        <select name="recipient_user_id" class="form-select">
                            <option value="">Select Employee</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ old('recipient_user_id') == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Delivery Date -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Date Received / Delivered</label>
                        <input type="date" name="delivery_date" class="form-control" value="{{ old('delivery_date', date('Y-m-d')) }}">
                    </div>

                    <!-- Document Attachment -->
                    <div class="col-12">
                        <label class="form-label fw-bold">Receipt Attachment (optional)</label>
                        <input type="file" name="attachment" class="form-control">
                        <div class="form-text small">Accepted file types: JPEG, PNG, PDF. Max size 2MB.</div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary btn-lg px-5">Save Entry</button>
                    <a href="{{ route('admin.front-office.couriers.index') }}" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
