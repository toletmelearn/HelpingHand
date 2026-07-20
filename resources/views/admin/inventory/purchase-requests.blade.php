@extends('layouts.admin')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-12 mb-4">
            <h1 class="h3 mb-2 text-gray-800">Inventory Purchase Requisitions</h1>
            <p class="mb-4">Create, track, and approve asset procurement purchase requests (Projectors, Computers, Desks, etc.).</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row">
        <!-- New Request Form -->
        <div class="col-md-4 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Log Purchase Requisition</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.inventory.purchase-requests.store') }}" method="POST">
                        @csrf
                        <div class="form-group mb-3">
                            <label>Item / Asset Name</label>
                            <input type="text" name="item_name" class="form-control" placeholder="e.g. Epson Projector EH-TW6250" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Quantity Required</label>
                            <input type="number" name="quantity" class="form-control" min="1" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Estimated Total Cost (₹)</label>
                            <input type="number" name="estimated_cost" class="form-control" min="0" step="0.01" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Submit Purchase Request</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Requests List -->
        <div class="col-md-8 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Procurement Requisitions History</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Qty</th>
                                    <th>Estimated Cost</th>
                                    <th>Requested By</th>
                                    <th>Status</th>
                                    <th>Approver / Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($requests as $req)
                                    <tr>
                                        <td><strong>{{ $req->item_name }}</strong></td>
                                        <td>{{ $req->quantity }}</td>
                                        <td>₹{{ number_format($req->estimated_cost, 2) }}</td>
                                        <td>{{ $req->requester->name }}</td>
                                        <td>
                                            @if($req->status === 'pending')
                                                <span class="badge bg-warning text-dark">Pending Review</span>
                                            @elseif($req->status === 'approved')
                                                <span class="badge bg-success">Approved</span>
                                            @else
                                                <span class="badge bg-danger">Rejected</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($req->status === 'pending')
                                                <div class="d-flex gap-2">
                                                    <form action="{{ route('admin.inventory.purchase-requests.update-status', $req->id) }}" method="POST">
                                                        @csrf
                                                        <input type="hidden" name="status" value="approved">
                                                        <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                                    </form>
                                                    <form action="{{ route('admin.inventory.purchase-requests.update-status', $req->id) }}" method="POST">
                                                        @csrf
                                                        <input type="hidden" name="status" value="rejected">
                                                        <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                                                    </form>
                                                </div>
                                            @else
                                                <span class="text-muted">Approved by {{ $req->approver ? $req->approver->name : 'System' }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No purchase requests logged yet.</td>
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
@endsection
