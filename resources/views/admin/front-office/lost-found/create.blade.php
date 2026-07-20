@extends('layouts.admin')

@section('title', 'Report Lost/Found Item')

@section('content')
<div class="container-fluid py-4">
    <!-- Breadcrumb / Header -->
    <div class="mb-4">
        <a href="{{ route('admin.front-office.lost-found.index') }}" class="btn btn-sm btn-outline-secondary me-2">
            <i class="bi bi-arrow-left"></i> Back to List
        </a>
        <h1 class="h3 d-inline-block align-middle mb-0 mt-2 mt-md-0">Report Lost / Found Article</h1>
    </div>

    <div class="card border-0 shadow-sm bg-white">
        <div class="card-body">
            <form action="{{ route('admin.front-office.lost-found.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="row g-3">
                    <!-- Item Name -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Article / Item Name <span class="text-danger">*</span></label>
                        <input type="text" name="item_name" class="form-control" placeholder="e.g. Leather Wallet, Water Bottle" value="{{ old('item_name') }}" required>
                    </div>

                    <!-- Item Type -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Report Type <span class="text-danger">*</span></label>
                        <select name="item_type" class="form-select" required>
                            <option value="lost" {{ old('item_type') === 'lost' ? 'selected' : '' }}>Lost Item Report</option>
                            <option value="found" {{ old('item_type') === 'found' || is_null(old('item_type')) ? 'selected' : '' }}>Found Item Report</option>
                        </select>
                    </div>

                    <!-- Location Found -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Location Found / Turned In <span class="text-danger">*</span></label>
                        <input type="text" name="location_found" class="form-control" placeholder="e.g. Playground near swings, Room 102" value="{{ old('location_found') }}" required>
                    </div>

                    <!-- Location Lost -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Expected Location Lost (if reporting lost)</label>
                        <input type="text" name="location_lost" class="form-control" placeholder="e.g. Library, School Bus Route 3" value="{{ old('location_lost') }}">
                    </div>

                    <!-- Date Reported -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Reported Date <span class="text-danger">*</span></label>
                        <input type="date" name="date_reported" class="form-control" value="{{ old('date_reported', date('Y-m-d')) }}" required>
                    </div>

                    <!-- Reported By Staff User -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Reported By School Staff</label>
                        <select name="reported_by_user_id" class="form-select">
                            <option value="">Choose Staff Employee</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ old('reported_by_user_id') == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Reported By (General/Walk-in name) -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Reported By Guest / Student</label>
                        <input type="text" name="reported_by_name" class="form-control" placeholder="Enter guest or student name" value="{{ old('reported_by_name') }}">
                    </div>

                    <!-- Status -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-select" required>
                            <option value="lost" {{ old('status') === 'lost' ? 'selected' : '' }}>Lost</option>
                            <option value="found" {{ old('status') === 'found' || is_null(old('status')) ? 'selected' : '' }}>Found / Unclaimed</option>
                            <option value="claimed" {{ old('status') === 'claimed' ? 'selected' : '' }}>Claim In-Progress</option>
                        </select>
                    </div>

                    <!-- Photo Upload -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Item Photo Image (optional)</label>
                        <input type="file" name="photo" class="form-control">
                        <div class="form-text small">Accepted file types: JPEG, PNG. Max size 2MB.</div>
                    </div>

                    <!-- Description -->
                    <div class="col-12">
                        <label class="form-label fw-bold">Detailed Description / Distinctive Markers</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Enter details like color, brand, contents, size...">{{ old('description') }}</textarea>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary btn-lg px-5">Save Report</button>
                    <a href="{{ route('admin.front-office.lost-found.index') }}" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
