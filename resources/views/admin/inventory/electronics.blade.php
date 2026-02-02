@extends('layouts.admin')

@section('title', 'Electronics Inventory Management')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Electronics Inventory Management</h4>
                    <div>
                        <a href="{{ route('admin.assets.create') }}" class="btn btn-primary">Add New Device</a>
                        <a href="{{ route('admin.inventory.index') }}" class="btn btn-secondary">Back to Inventory</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <form method="GET" action="{{ route('inventory.electronics') }}">
                                <div class="row">
                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="search" placeholder="Search electronic devices..." 
                                               value="{{ request('search') }}">
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-control" name="location">
                                            <option value="">All Locations</option>
                                            <option value="classroom" {{ request('location') == 'classroom' ? 'selected' : '' }}>Classroom</option>
                                            <option value="lab" {{ request('location') == 'lab' ? 'selected' : '' }}>Lab</option>
                                            <option value="office" {{ request('location') == 'office' ? 'selected' : '' }}>Office</option>
                                            <option value="staff_room" {{ request('location') == 'staff_room' ? 'selected' : '' }}>Staff Room</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-control" name="condition">
                                            <option value="">All Conditions</option>
                                            <option value="new" {{ request('condition') == 'new' ? 'selected' : '' }}>New</option>
                                            <option value="good" {{ request('condition') == 'good' ? 'selected' : '' }}>Good</option>
                                            <option value="repair" {{ request('condition') == 'repair' ? 'selected' : '' }}>Repair</option>
                                            <option value="scrap" {{ request('condition') == 'scrap' ? 'selected' : '' }}>Scrap</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-control" name="status">
                                            <option value="">All Status</option>
                                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                                            <option value="in_use" {{ request('status') == 'in_use' ? 'selected' : '' }}>In Use</option>
                                            <option value="under_repair" {{ request('status') == 'under_repair' ? 'selected' : '' }}>Under Repair</option>
                                            <option value="disposed" {{ request('status') == 'disposed' ? 'selected' : '' }}>Disposed</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <button type="submit" class="btn btn-primary">Filter</button>
                                        <a href="{{ route('inventory.electronics') }}" class="btn btn-secondary">Reset</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Asset Code</th>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Serial Number</th>
                                    <th>Location</th>
                                    <th>Quantity</th>
                                    <th>Available</th>
                                    <th>Warranty Expiry</th>
                                    <th>Condition</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($assets as $asset)
                                    @if($asset->category && $asset->category->type == 'electronics')
                                    <tr>
                                        <td>{{ $asset->asset_code }}</td>
                                        <td>{{ $asset->name }}</td>
                                        <td>
                                            @if(str_contains(strtolower($asset->name), 'computer') || str_contains(strtolower($asset->name), 'pc'))
                                                Computer
                                            @elseif(str_contains(strtolower($asset->name), 'projector'))
                                                Projector
                                            @elseif(str_contains(strtolower($asset->name), 'printer'))
                                                Printer
                                            @elseif(str_contains(strtolower($asset->name), 'smart board') || str_contains(strtolower($asset->name), 'smartboard'))
                                                Smart Board
                                            @elseif(str_contains(strtolower($asset->name), 'cctv') || str_contains(strtolower($asset->name), 'camera'))
                                                CCTV
                                            @else
                                                Electronics
                                            @endif
                                        </td>
                                        <td>{{ $asset->serial_number ?: 'N/A' }}</td>
                                        <td>{{ $asset->location ?: 'N/A' }}</td>
                                        <td>{{ $asset->quantity }}</td>
                                        <td>{{ $asset->available_quantity }}</td>
                                        <td>
                                            @if($asset->warranty_expiry_date)
                                                {{ \Carbon\Carbon::parse($asset->warranty_expiry_date)->format('M d, Y') }}
                                                @if(\Carbon\Carbon::parse($asset->warranty_expiry_date)->lt(now()))
                                                    <span class="badge badge-danger">Expired</span>
                                                @elseif(\Carbon\Carbon::parse($asset->warranty_expiry_date)->lt(now()->addDays(30)))
                                                    <span class="badge badge-warning">Expiring Soon</span>
                                                @endif
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                        <td>
                                            @if($asset->condition == 'new')
                                                <span class="badge badge-info">New</span>
                                            @elseif($asset->condition == 'good')
                                                <span class="badge badge-success">Good</span>
                                            @elseif($asset->condition == 'repair')
                                                <span class="badge badge-warning">Repair</span>
                                            @else
                                                <span class="badge badge-danger">Scrap</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($asset->status == 'active')
                                                <span class="badge badge-success">Active</span>
                                            @elseif($asset->status == 'in_use')
                                                <span class="badge badge-primary">In Use</span>
                                            @elseif($asset->status == 'under_repair')
                                                <span class="badge badge-warning">Under Repair</span>
                                            @else
                                                <span class="badge badge-danger">Disposed</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('inventory.assets.show', $asset->id) }}" 
                                                   class="btn btn-sm btn-info" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="{{ route('inventory.assets.edit', $asset->id) }}" 
                                                   class="btn btn-sm btn-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                @if($asset->available_quantity > 0)
                                                    <button type="button" class="btn btn-sm btn-success" 
                                                            data-action="issue" data-asset-id="{{ $asset->id }}" title="Issue">
                                                        <i class="fas fa-arrow-down"></i>
                                                    </button>
                                                @else
                                                    <button type="button" class="btn btn-sm btn-secondary" disabled>
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                @endif
                                                <button type="button" class="btn btn-sm btn-warning" 
                                                        data-action="return" data-asset-id="{{ $asset->id }}" title="Return">
                                                    <i class="fas fa-arrow-up"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="11" class="text-center">No electronic devices found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $assets->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Issue Electronic Device Modal -->
<div class="modal fade" id="issueDeviceModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Issue Electronic Device</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="issueDeviceForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="form-group">
                        <label for="issue_quantity">Quantity to Issue *</label>
                        <input type="number" class="form-control" id="issue_quantity" name="quantity" min="1" required>
                        <small class="form-text text-muted">Available: <span id="available_qty">0</span></small>
                    </div>
                    <div class="form-group">
                        <label for="issue_location">Issue To Location *</label>
                        <select class="form-control" id="issue_location" name="location" required>
                            <option value="classroom">Classroom</option>
                            <option value="lab">Lab</option>
                            <option value="office">Office</option>
                            <option value="staff_room">Staff Room</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="assigned_to">Assigned To (Optional)</label>
                        <input type="text" class="form-control" id="assigned_to" name="assigned_to">
                    </div>
                    <div class="form-group">
                        <label for="issue_reason">Reason *</label>
                        <textarea class="form-control" id="issue_reason" name="reason" rows="2" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Issue Device</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Return Electronic Device Modal -->
<div class="modal fade" id="returnDeviceModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Return Electronic Device</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="returnDeviceForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="form-group">
                        <label for="return_quantity">Quantity to Return *</label>
                        <input type="number" class="form-control" id="return_quantity" name="quantity" min="1" required>
                        <small class="form-text text-muted">Issued: <span id="issued_qty">0</span></small>
                    </div>
                    <div class="form-group">
                        <label for="return_location">Return From Location *</label>
                        <select class="form-control" id="return_location" name="location" required>
                            <option value="classroom">Classroom</option>
                            <option value="lab">Lab</option>
                            <option value="office">Office</option>
                            <option value="staff_room">Staff Room</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="return_reason">Reason *</label>
                        <textarea class="form-control" id="return_reason" name="reason" rows="2" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Return Device</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Handle issue device button clicks
    $(document).on('click', 'button[data-action="issue"]', function() {
        var assetId = $(this).data('asset-id');
        document.getElementById('issueDeviceForm').action = '/admin/inventory/assets/' + assetId + '/issue';
        
        // Fetch asset details to populate modal
        fetch('/admin/inventory/assets/' + assetId)
            .then(response => response.json())
            .then(data => {
                document.getElementById('available_qty').textContent = data.available_quantity;
                document.getElementById('issue_quantity').max = data.available_quantity;
                document.getElementById('issue_quantity').value = 1;
            });
        
        $('#issueDeviceModal').modal('show');
    });
    
    // Handle return device button clicks
    $(document).on('click', 'button[data-action="return"]', function() {
        var assetId = $(this).data('asset-id');
        document.getElementById('returnDeviceForm').action = '/admin/inventory/assets/' + assetId + '/return';
        
        // For return, we'll set max based on issued quantity (which needs to be calculated)
        // For simplicity, we'll set it to a reasonable default
        document.getElementById('return_quantity').value = 1;
        
        $('#returnDeviceModal').modal('show');
    });
});
</script>
@endsection