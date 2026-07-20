@extends('layouts.admin')

@section('title', 'Edit Lost/Found Item')

@section('content')
<div class="container-fluid py-4">
    <!-- Breadcrumb / Header -->
    <div class="mb-4">
        <a href="{{ route('admin.front-office.lost-found.index') }}" class="btn btn-sm btn-outline-secondary me-2">
            <i class="bi bi-arrow-left"></i> Back to List
        </a>
        <h1 class="h3 d-inline-block align-middle mb-0 mt-2 mt-md-0">Edit Lost / Found Article Details</h1>
    </div>

    <div class="card border-0 shadow-sm bg-white">
        <div class="card-body">
            <form action="{{ route('admin.front-office.lost-found.update', $item->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <!-- Item Name -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Article / Item Name <span class="text-danger">*</span></label>
                        <input type="text" name="item_name" class="form-control" value="{{ old('item_name', $item->item_name) }}" required>
                    </div>

                    <!-- Item Type -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Report Type <span class="text-danger">*</span></label>
                        <select name="item_type" class="form-select" required>
                            <option value="lost" {{ old('item_type', $item->item_type) === 'lost' ? 'selected' : '' }}>Lost Item Report</option>
                            <option value="found" {{ old('item_type', $item->item_type) === 'found' ? 'selected' : '' }}>Found Item Report</option>
                        </select>
                    </div>

                    <!-- Location Found -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Location Found / Turned In <span class="text-danger">*</span></label>
                        <input type="text" name="location_found" class="form-control" value="{{ old('location_found', $item->location_found) }}" required>
                    </div>

                    <!-- Location Lost -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Expected Location Lost (if reporting lost)</label>
                        <input type="text" name="location_lost" class="form-control" value="{{ old('location_lost', $item->location_lost) }}">
                    </div>

                    <!-- Date Reported -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Reported Date <span class="text-danger">*</span></label>
                        <input type="date" name="date_reported" class="form-control" value="{{ old('date_reported', $item->date_reported ? $item->date_reported->format('Y-m-d') : '') }}" required>
                    </div>

                    <!-- Reported By Staff User -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Reported By School Staff</label>
                        <select name="reported_by_user_id" class="form-select">
                            <option value="">Choose Staff Employee</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ old('reported_by_user_id', $item->reported_by_user_id) == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Reported By (General/Walk-in name) -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Reported By Guest / Student</label>
                        <input type="text" name="reported_by_name" class="form-control" value="{{ old('reported_by_name', $item->reported_by_name) }}">
                    </div>

                    <!-- Status -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-select" required>
                            <option value="lost" {{ old('status', $item->status) === 'lost' ? 'selected' : '' }}>Lost</option>
                            <option value="found" {{ old('status', $item->status) === 'found' ? 'selected' : '' }}>Found / Unclaimed</option>
                            <option value="claimed" {{ old('status', $item->status) === 'claimed' ? 'selected' : '' }}>Claim In-Progress</option>
                            <option value="returned" {{ old('status', $item->status) === 'returned' ? 'selected' : '' }}>Returned / Resolved</option>
                        </select>
                    </div>

                    <!-- Photo Upload -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Item Photo Image (optional)</label>
                        <input type="file" name="photo" class="form-control">
                        @if($item->photo_path)
                            <div class="mt-2 small">
                                Current photo: <a href="{{ asset('storage/' . $item->photo_path) }}" target="_blank">View Item Image &rarr;</a>
                            </div>
                        @endif
                    </div>

                    <!-- Description -->
                    <div class="col-12">
                        <label class="form-label fw-bold">Detailed Description / Distinctive Markers</label>
                        <textarea name="description" class="form-control" rows="3">{{ old('description', $item->description) }}</textarea>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary btn-lg px-5">Update Report</button>
                    <a href="{{ route('admin.front-office.lost-found.index') }}" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
