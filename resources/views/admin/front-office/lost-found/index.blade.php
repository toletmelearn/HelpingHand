@extends('layouts.admin')

@section('title', 'Lost & Found Registry')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Lost & Found Registry</h1>
            <p class="text-muted mb-0">Record, claim, and return lost or found articles on campus.</p>
        </div>
        <a href="{{ route('admin.front-office.lost-found.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Report Item
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
            <form action="{{ route('admin.front-office.lost-found.index') }}" method="GET" class="row g-2">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search item, location, claimant..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <select name="item_type" class="form-select">
                        <option value="">Filter Type</option>
                        <option value="lost" {{ request('item_type') === 'lost' ? 'selected' : '' }}>Lost Report</option>
                        <option value="found" {{ request('item_type') === 'found' ? 'selected' : '' }}>Found Report</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Filter Status</option>
                        <option value="lost" {{ request('status') === 'lost' ? 'selected' : '' }}>Lost</option>
                        <option value="found" {{ request('status') === 'found' ? 'selected' : '' }}>Found / Unclaimed</option>
                        <option value="claimed" {{ request('status') === 'claimed' ? 'selected' : '' }}>Claim Request</option>
                        <option value="returned" {{ request('status') === 'returned' ? 'selected' : '' }}>Returned / Resolved</option>
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
                            <th>Item Image & Name</th>
                            <th>Category</th>
                            <th>Report Details</th>
                            <th>Reporter</th>
                            <th>Claimed By</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        @if($item->photo_path)
                                            <img src="{{ asset('storage/' . $item->photo_path) }}" alt="Photo" class="rounded me-2 border bg-light" style="width: 38px; height: 38px; object-fit: cover;">
                                        @else
                                            <div class="bg-light rounded p-2 me-2 text-center text-primary" style="width: 38px; height: 38px;">
                                                <i class="bi bi-search"></i>
                                            </div>
                                        @endif
                                        <div>
                                            <div class="fw-semibold">{{ $item->item_name }}</div>
                                            <small class="text-muted text-truncate d-inline-block" style="max-width: 150px;">{{ $item->description }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $item->item_type === 'found' ? 'success' : 'warning' }} text-capitalize">
                                        {{ $item->item_type }}
                                    </span>
                                </td>
                                <td>
                                    <div>Found: {{ $item->location_found }}</div>
                                    <small class="text-muted">On: {{ $item->date_reported->format('M d, Y') }}</small>
                                </td>
                                <td>
                                    @if($item->reportedByUser)
                                        {{ $item->reportedByUser->name }} (Staff)
                                    @else
                                        {{ $item->reported_by_name ?: 'General' }}
                                    @endif
                                </td>
                                <td>
                                    @if($item->status === 'returned')
                                        <div>{{ $item->claimant_name }}</div>
                                        <small class="text-muted">{{ $item->claimant_phone }}</small>
                                    @else
                                        <span class="text-muted small">Unclaimed</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $item->status === 'returned' ? 'success' : ($item->status === 'found' ? 'info' : 'danger') }} rounded-pill text-capitalize">
                                        {{ $item->status }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        @if($item->status !== 'returned' && $item->item_type === 'found')
                                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#claimModal-{{ $item->id }}">
                                                Claim Item
                                            </button>
                                        @endif
                                        <a href="{{ route('admin.front-office.lost-found.show', $item->id) }}" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.front-office.lost-found.edit', $item->id) }}" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('admin.front-office.lost-found.destroy', $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this item?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Claim Modal -->
                                    <div class="modal fade" id="claimModal-{{ $item->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered text-start">
                                            <div class="modal-content">
                                                <form action="{{ route('admin.front-office.lost-found.claim', $item->id) }}" method="POST">
                                                    @csrf
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Log claimant Return Details</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Claimant Full Name <span class="text-danger">*</span></label>
                                                            <input type="text" name="claimant_name" class="form-control" placeholder="Enter name of person claiming item" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Claimant Phone Number <span class="text-danger">*</span></label>
                                                            <input type="text" name="claimant_phone" class="form-control" placeholder="Mobile number" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Verification Details <span class="text-danger">*</span></label>
                                                            <textarea name="verification_details" class="form-control" rows="4" placeholder="Briefly log what verification details or ID was checked to confirm ownership..." required></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn btn-success">Verify and Return Item</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No lost or found items recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $items->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
