@extends('layouts.admin')

@section('title', 'Admission Enquiries')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Admission Enquiries</h1>
            <p class="text-muted mb-0">Record and follow up on school candidate walk-in lead pipelines.</p>
        </div>
        <a href="{{ route('admin.front-office.enquiries.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> New Enquiry
        </a>
    </div>

    <!-- Duplicate Alert Warning -->
    @if(session('duplicate_found'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i>
            <strong>Possible Duplicate Record Detected!</strong> An enquiry for 
            <strong>{{ session('duplicate_found')['name'] }}</strong> (Parent: {{ session('duplicate_found')['parent'] }}, Phone: {{ session('duplicate_found')['phone'] }}) already exists.
            <form action="{{ route('admin.front-office.enquiries.store') }}" method="POST" class="d-inline ms-3">
                @csrf
                <input type="hidden" name="candidate_name" value="{{ old('candidate_name') }}">
                <input type="hidden" name="parent_name" value="{{ old('parent_name') }}">
                <input type="hidden" name="phone" value="{{ old('phone') }}">
                <input type="hidden" name="email" value="{{ old('email') }}">
                <input type="hidden" name="aadhaar_number" value="{{ old('aadhaar_number') }}">
                <input type="hidden" name="counsellor_id" value="{{ old('counsellor_id') }}">
                <input type="hidden" name="status" value="{{ old('status') }}">
                <input type="hidden" name="remarks" value="{{ old('remarks') }}">
                <input type="hidden" name="force_create" value="1">
                <button type="submit" class="btn btn-sm btn-danger px-3">Force Create Enquiry</button>
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

    <!-- Filters Card -->
    <div class="card border-0 shadow-sm mb-4 bg-white">
        <div class="card-body p-3">
            <form action="{{ route('admin.front-office.enquiries.index') }}" method="GET" class="row g-2">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Search by name, parent, phone..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Filter by Status</option>
                        <option value="new" {{ request('status') === 'new' ? 'selected' : '' }}>New</option>
                        <option value="interested" {{ request('status') === 'interested' ? 'selected' : '' }}>Interested</option>
                        <option value="follow_up" {{ request('status') === 'follow_up' ? 'selected' : '' }}>Follow-up Required</option>
                        <option value="documents_pending" {{ request('status') === 'documents_pending' ? 'selected' : '' }}>Documents Pending</option>
                        <option value="selected" {{ request('status') === 'selected' ? 'selected' : '' }}>Selected (Evaluated)</option>
                        <option value="confirmed" {{ request('status') === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                        <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                        <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
                        <option value="admitted" {{ request('status') === 'admitted' ? 'selected' : '' }}>Admitted</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="counsellor_id" class="form-select">
                        <option value="">Filter by Counsellor</option>
                        @foreach($counsellors as $counsellor)
                            <option value="{{ $counsellor->id }}" {{ request('counsellor_id') == $counsellor->id ? 'selected' : '' }}>
                                {{ $counsellor->name }}
                            </option>
                        @endforeach
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
                            <th>Candidate</th>
                            <th>Parent & Phone</th>
                            <th>Aadhaar</th>
                            <th>Assigned Counsellor</th>
                            <th>Status</th>
                            <th>Follow-up</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($enquiries as $enquiry)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $enquiry->candidate_name }}</div>
                                    <small class="text-muted">{{ $enquiry->email ?: 'No email' }}</small>
                                </td>
                                <td>
                                    <div>{{ $enquiry->parent_name }}</div>
                                    <small class="text-muted">{{ $enquiry->phone }}</small>
                                </td>
                                <td>{{ $enquiry->aadhaar_number ?: 'N/A' }}</td>
                                <td>
                                    @if($enquiry->counsellor)
                                        <span class="badge bg-light text-dark border"><i class="bi bi-person me-1"></i>{{ $enquiry->counsellor->name }}</span>
                                    @else
                                        <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 text-success fw-medium" data-bs-toggle="modal" data-bs-target="#assignModal-{{ $enquiry->id }}">
                                            + Assign
                                        </button>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $enquiry->status === 'admitted' || $enquiry->status === 'confirmed' ? 'success' : ($enquiry->status === 'rejected' || $enquiry->status === 'closed' ? 'danger' : 'warning') }} rounded-pill text-capitalize">
                                        {{ str_replace('_', ' ', $enquiry->status) }}
                                    </span>
                                </td>
                                <td>
                                    @if($enquiry->follow_up_date)
                                        <div class="small fw-medium">{{ $enquiry->follow_up_date->format('M d, Y') }}</div>
                                        <small class="text-muted text-truncate d-inline-block" style="max-width: 150px;">{{ $enquiry->follow_up_notes }}</small>
                                    @else
                                        <span class="text-muted small">No scheduled follow-up</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <a href="{{ route('admin.front-office.enquiries.show', $enquiry->id) }}" class="btn btn-sm btn-outline-secondary" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#followUpModal-{{ $enquiry->id }}" title="Log Follow-up">
                                            <i class="bi bi-chat-right-text"></i>
                                        </button>
                                        <a href="{{ route('admin.front-office.enquiries.edit', $enquiry->id) }}" class="btn btn-sm btn-outline-secondary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        @if(in_array($enquiry->status, ['selected', 'confirmed']))
                                            <a href="{{ route('admin.admissions.confirm-form', $enquiry->id) }}" class="btn btn-sm btn-outline-success" title="Admit Student">
                                                <i class="bi bi-check2-circle"></i>
                                            </a>
                                        @endif
                                        <form action="{{ route('admin.front-office.enquiries.destroy', $enquiry->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this enquiry?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Assign Counsellor Modal -->
                                    <div class="modal fade" id="assignModal-{{ $enquiry->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <form action="{{ route('admin.front-office.enquiries.counsellor', $enquiry->id) }}" method="POST">
                                                    @csrf
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Assign Counsellor</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body text-start">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Select Counsellor Staff</label>
                                                            <select name="counsellor_id" class="form-select" required>
                                                                <option value="">Select Staff Member</option>
                                                                @foreach($counsellors as $counsellor)
                                                                    <option value="{{ $counsellor->id }}">{{ $counsellor->name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn btn-success">Save Assignment</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Follow Up Log Modal -->
                                    <div class="modal fade" id="followUpModal-{{ $enquiry->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <form action="{{ route('admin.front-office.enquiries.follow-up', $enquiry->id) }}" method="POST">
                                                    @csrf
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Log Follow-Up & Outcome</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body text-start">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Next Follow-up Date</label>
                                                            <input type="date" name="follow_up_date" class="form-control" min="{{ date('Y-m-d') }}" value="{{ $enquiry->follow_up_date ? $enquiry->follow_up_date->format('Y-m-d') : '' }}" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Update Lead Status</label>
                                                            <select name="status" class="form-select" required>
                                                                <option value="interested" {{ $enquiry->status === 'interested' ? 'selected' : '' }}>Interested</option>
                                                                <option value="follow_up" {{ $enquiry->status === 'follow_up' ? 'selected' : '' }}>Follow-up Required</option>
                                                                <option value="documents_pending" {{ $enquiry->status === 'documents_pending' ? 'selected' : '' }}>Documents Pending</option>
                                                                <option value="closed" {{ $enquiry->status === 'closed' ? 'selected' : '' }}>Closed/Completed</option>
                                                                <option value="rejected" {{ $enquiry->status === 'rejected' ? 'selected' : '' }}>Rejected/Not Interested</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Follow-up Discussion Notes</label>
                                                            <textarea name="follow_up_notes" class="form-control" rows="4" placeholder="Enter details of conversation with parent..." required>{{ $enquiry->follow_up_notes }}</textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Save Log</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No admission enquiries recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $enquiries->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
