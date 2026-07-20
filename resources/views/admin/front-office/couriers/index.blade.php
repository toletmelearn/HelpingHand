@extends('layouts.admin')

@section('title', 'Courier Tracking Register')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Courier & Postal Register</h1>
            <p class="text-muted mb-0">Record and track inbound and outbound letters, speed posts, and packages.</p>
        </div>
        <a href="{{ route('admin.front-office.couriers.create') }}" class="btn btn-primary">
            <i class="bi bi-box-seam me-1"></i> Log Courier
        </a>
    </div>

    <!-- Success Message -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Filters Card -->
    <div class="card border-0 shadow-sm mb-4 bg-white">
        <div class="card-body p-3">
            <form action="{{ route('admin.front-office.couriers.index') }}" method="GET" class="row g-2">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search tracking, company, sender, receiver..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <select name="courier_type" class="form-select">
                        <option value="">Filter Direction</option>
                        <option value="incoming" {{ request('courier_type') === 'incoming' ? 'selected' : '' }}>Incoming</option>
                        <option value="outgoing" {{ request('courier_type') === 'outgoing' ? 'selected' : '' }}>Outgoing</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Filter Status</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending Delivery</option>
                        <option value="delivered" {{ request('status') === 'delivered' ? 'selected' : '' }}>Delivered / Received</option>
                        <option value="returned" {{ request('status') === 'returned' ? 'selected' : '' }}>Returned to Sender</option>
                        <option value="lost" {{ request('status') === 'lost' ? 'selected' : '' }}>Lost</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100">Apply Filters</button>
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
                            <th>Tracking No</th>
                            <th>Company</th>
                            <th>Direction</th>
                            <th>Sender</th>
                            <th>Receiver</th>
                            <th>Delivery Date</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($couriers as $courier)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $courier->tracking_number }}</div>
                                    <small class="text-muted text-capitalize">{{ $courier->parcel_type }}</small>
                                </td>
                                <td>{{ $courier->courier_company }}</td>
                                <td>
                                    <span class="badge bg-{{ $courier->courier_type === 'incoming' ? 'info' : 'primary' }} text-capitalize">
                                        {{ $courier->courier_type }}
                                    </span>
                                </td>
                                <td>{{ $courier->sender }}</td>
                                <td>
                                    <div>{{ $courier->receiver }}</div>
                                    @if($courier->recipient)
                                        <small class="text-muted"><i class="bi bi-person me-1"></i>Staff: {{ $courier->recipient->name }}</small>
                                    @endif
                                </td>
                                <td>
                                    {{ $courier->delivery_date ? $courier->delivery_date->format('M d, Y') : 'Pending' }}
                                </td>
                                <td>
                                    <span class="badge bg-{{ $courier->status === 'delivered' ? 'success' : ($courier->status === 'pending' ? 'warning' : 'danger') }} rounded-pill text-capitalize">
                                        {{ $courier->status }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <a href="{{ route('admin.front-office.couriers.show', $courier->id) }}" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.front-office.couriers.edit', $courier->id) }}" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('admin.front-office.couriers.destroy', $courier->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this courier log?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No courier entries registered yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $couriers->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
