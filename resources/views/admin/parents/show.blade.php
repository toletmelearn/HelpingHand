@extends('layouts.admin')

@section('content')
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <!-- Back Button row -->
        <div class="col-md-10 mb-3 d-flex justify-content-between align-items-center">
            <h1 class="h3 text-gray-800 mb-0">Parent Profile: {{ $parent->name }}</h1>
            <a href="{{ route('admin.parents.index') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Back to Directory</a>
        </div>

        @if(session('success'))
            <div class="col-md-10 mb-3">
                <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div>
            </div>
        @endif

        <div class="col-md-5 mb-4">
            <!-- Edit Profile Card -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Edit Parent Information</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.parents.update', $parent->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="form-group mb-3">
                            <label class="fw-bold">Parent Name</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $parent->name) }}" required>
                        </div>

                        <div class="form-group mb-3">
                            <label class="fw-bold">Email Address</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $parent->email) }}" required>
                        </div>

                        <div class="form-group mb-3">
                            <label class="fw-bold">Contact Phone</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $parent->phone) }}">
                        </div>

                        <div class="form-group mb-3">
                            <label class="fw-bold">Mobile Phone</label>
                            <input type="text" name="mobile" class="form-control" value="{{ old('mobile', $parent->mobile) }}">
                        </div>

                        <div class="form-group mb-4">
                            <label class="fw-bold">Account Status</label>
                            <select name="status" class="form-control" required>
                                <option value="active" {{ old('status', $parent->status) === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status', $parent->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            Save Changes <i class="fas fa-save ms-1"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-5 mb-4">
            <!-- Reset Password Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-danger">Reset Password Credentials</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.parents.reset-password', $parent->id) }}" method="POST">
                        @csrf

                        <div class="form-group mb-3">
                            <label class="fw-bold">New Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
                        </div>

                        <div class="form-group mb-4">
                            <label class="fw-bold">Confirm Password</label>
                            <input type="password" name="password_confirmation" class="form-control" required>
                        </div>

                        <button type="submit" class="btn btn-danger w-100">
                            Update Password <i class="fas fa-key ms-1"></i>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Linked Children Card -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-success">Linked Children (Students)</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse($parent->students as $child)
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <div>
                                    <h6 class="mb-0 fw-bold">{{ $child->name }}</h6>
                                    <small class="text-muted">Class: {{ $child->schoolClass?->name ?? 'N/A' }} | Section: {{ $child->section?->name ?? 'N/A' }}</small>
                                </div>
                                <a href="{{ route('admin.students.show', $child->id) }}" class="btn btn-sm btn-outline-info">
                                    View Student Profile <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </li>
                        @empty
                            <li class="list-group-item py-3 text-center text-muted">No student records linked to this parent.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
