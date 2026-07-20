@extends('layouts.admin')

@section('title', 'Visitor Registry')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Visitor Gate Registry</h1>
            <p class="text-muted mb-0">Record and track guest entries and check-outs for school security.</p>
        </div>
        <a href="{{ route('admin.front-office.visitors.create') }}" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i> Guest Check-In
        </a>
    </div>

    <!-- Blacklist Warning Alert -->
    @if(session('blacklist_warning'))
        <div class="alert alert-warning alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-shield-slash-fill me-2 fs-5 text-danger"></i>
            <strong>Warning: Blacklisted Visitor!</strong> {{ session('blacklist_warning') }}
            <form action="{{ route('admin.front-office.visitors.store') }}" method="POST" class="d-inline ms-3">
                @csrf
                <input type="hidden" name="visitor_name" value="{{ old('visitor_name') }}">
                <input type="hidden" name="phone" value="{{ old('phone') }}">
                <input type="hidden" name="purpose" value="{{ old('purpose') }}">
                <input type="hidden" name="department" value="{{ old('department') }}">
                <input type="hidden" name="host_user_id" value="{{ old('host_user_id') }}">
                <input type="hidden" name="id_proof_type" value="{{ old('id_proof_type') }}">
                <input type="hidden" name="id_proof_number" value="{{ old('id_proof_number') }}">
                <input type="hidden" name="photo" value="{{ old('photo') }}">
                <input type="hidden" name="vehicle_no" value="{{ old('vehicle_no') }}">
                <input type="hidden" name="remarks" value="{{ old('remarks') }}">
                <input type="hidden" name="force_check_in" value="1">
                <button type="submit" class="btn btn-sm btn-danger px-3">Force Check-In</button>
            </form>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Success Messages -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Stats Grid -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="rounded-3 bg-primary bg-opacity-10 p-3 text-primary me-3">
                        <i class="bi bi-people fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1 small">Total Guests Today</h6>
                        <h3 class="mb-0 fw-bold">{{ $totalVisitorsToday }}</h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="rounded-3 bg-success bg-opacity-10 p-3 text-success me-3">
                        <i class="bi bi-door-open fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1 small">Guests Currently Inside</h6>
                        <h3 class="mb-0 fw-bold">{{ $currentlyOnCampus }}</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card border-0 shadow-sm mb-4 bg-white">
        <div class="card-body p-3">
            <form action="{{ route('admin.front-office.visitors.index') }}" method="GET" class="row g-2">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Search by name, phone, or purpose..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Filter by Status</option>
                        <option value="inside" {{ request('status') === 'inside' ? 'selected' : '' }}>Currently Inside</option>
                        <option value="checked_out" {{ request('status') === 'checked_out' ? 'selected' : '' }}>Checked Out</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="form-check mt-2">
                        <input type="checkbox" name="blacklisted" value="1" class="form-check-input" id="filter_blacklisted" {{ request('blacklisted') ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold text-danger small" for="filter_blacklisted">Show Blacklisted Only</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100">Apply</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card border-0 shadow-sm bg-white">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-uppercase small text-muted">
                            <th>Visitor</th>
                            <th>Purpose & Dept</th>
                            <th>Host Staff</th>
                            <th>In Time</th>
                            <th>Out Time</th>
                            <th>Security Alerts</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($visitors as $visitor)
                            <tr class="{{ $visitor->is_blacklisted ? 'table-danger bg-opacity-10' : '' }}">
                                <td>
                                    <div class="d-flex align-items-center">
                                        @if($visitor->photo_path)
                                            <img src="{{ asset('storage/' . $visitor->photo_path) }}" alt="Photo" class="rounded me-2 border bg-light" style="width: 38px; height: 38px; object-fit: cover;">
                                        @else
                                            <div class="bg-light rounded p-2 me-2 text-center text-primary" style="width: 38px; height: 38px;">
                                                <i class="bi bi-person"></i>
                                            </div>
                                        @endif
                                        <div>
                                            <div class="fw-semibold">{{ $visitor->visitor_name }}</div>
                                            <small class="text-muted">{{ $visitor->phone }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>{{ $visitor->purpose }}</div>
                                    <small class="text-muted">{{ $visitor->department ?: 'N/A' }}</small>
                                </td>
                                <td>{{ $visitor->host ? $visitor->host->name : 'N/A' }}</td>
                                <td>{{ $visitor->check_in->format('M d, h:i A') }}</td>
                                <td>
                                    @if($visitor->check_out)
                                        {{ $visitor->check_out->format('M d, h:i A') }}
                                    @else
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success">Inside</span>
                                    @endif
                                </td>
                                <td>
                                    @if($visitor->is_blacklisted)
                                        <span class="badge bg-danger rounded-pill"><i class="bi bi-shield-slash me-1"></i>Blacklisted</span>
                                    @endif
                                    @if($visitor->is_emergency)
                                        <span class="badge bg-warning text-dark rounded-pill"><i class="bi bi-exclamation-triangle me-1"></i>Emergency</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        @if(!$visitor->check_out)
                                            <form action="{{ route('admin.front-office.visitors.update', $visitor->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="check_out_now" value="1">
                                                <input type="hidden" name="visitor_name" value="{{ $visitor->visitor_name }}">
                                                <input type="hidden" name="phone" value="{{ $visitor->phone }}">
                                                <input type="hidden" name="purpose" value="{{ $visitor->purpose }}">
                                                <button type="submit" class="btn btn-sm btn-success" title="Check Out Guest">
                                                    Check-Out
                                                </button>
                                            </form>
                                        @endif
                                        <a href="{{ route('admin.front-office.visitors.badge', $visitor->id) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print Visitor Pass">
                                            <i class="bi bi-printer"></i> Pass
                                        </a>
                                        <form action="{{ route('admin.front-office.visitors.blacklist', $visitor->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ $visitor->is_blacklisted ? 'Remove Blacklist' : 'Blacklist Visitor' }}">
                                                <i class="bi bi-shield-slash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No visitors logged yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $visitors->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
