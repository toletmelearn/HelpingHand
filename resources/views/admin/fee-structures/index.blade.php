@extends('layouts.admin')

@section('title', 'Fee Structures')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Fee Structures</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Fee Structures</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">Fee Structure Management</h5>
                    <div class="d-flex gap-2">
                        <a href="{{ route('admin.fee-types.master') }}" class="btn btn-info">
                            <i class="fas fa-cogs me-1"></i> Fee Type Master Defaults
                        </a>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#copyModal">
                            <i class="fas fa-copy me-1"></i> Copy Structure
                        </button>
                        <a href="{{ route('admin.fee-structures.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i> Create Fee Structure
                        </a>
                        @if(Route::has('imports.wizard'))
                            <a href="{{ route('imports.wizard', ['module' => 'fee-structures']) }}" class="btn btn-outline-secondary" title="Add many fee structures at once from a spreadsheet">
                                <i class="fas fa-file-upload me-1"></i> Bulk Import Instead
                            </a>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-centered table-nowrap mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Class</th>
                                    <th>Academic Year</th>
                                    <th>Frequency</th>
                                    <th>Total Fee</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($feeStructures as $structure)
                                <tr>
                                    <td>
                                        <h6 class="mb-0">{{ $structure->class_name }}</h6>
                                    </td>
                                    <td>{{ $structure->academic_year }}</td>
                                    <td>
                                        <span class="badge bg-info text-uppercase">{{ $structure->frequency }}</span>
                                    </td>
                                    <td>
                                        <strong>₹{{ number_format($structure->feeStructureItems->sum('amount'), 2) }}</strong>
                                    </td>
                                    <td>
                                        @if($structure->status == 'active')
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($structure->createdBy)
                                            {{ $structure->createdBy->name ?? 'N/A' }}
                                        @else
                                            <span class="text-muted">System</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="{{ route('admin.fee-structures.edit', $structure->id) }}" 
                                               class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            @if($structure->status == 'active')
                                                <button type="button" 
                                                        class="btn btn-sm btn-secondary toggle-btn"
                                                        data-id="{{ $structure->id }}"
                                                        data-action="deactivate"
                                                        title="Deactivate">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            @else
                                                <button type="button" 
                                                        class="btn btn-sm btn-success toggle-btn" 
                                                        data-id="{{ $structure->id }}"
                                                        data-action="activate"
                                                        title="Activate">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            @endif
                                            
                                            <button type="button" 
                                                    class="btn btn-sm btn-info view-btn" 
                                                    data-id="{{ $structure->id }}"
                                                    title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <form action="{{ route('admin.fee-structures.destroy', $structure->id) }}" 
                                                  method="POST" 
                                                  class="d-inline"
                                                  onsubmit="return confirm('Are you sure you want to delete this fee structure? This action cannot be undone.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center">
                                        <div class="py-5">
                                            <i class="fas fa-file-invoice-dollar fa-3x text-muted mb-3"></i>
                                            <h5>No Fee Structures Found</h5>
                                            <p class="text-muted">Create your first fee structure to get started.</p>
                                            <a href="{{ route('admin.fee-structures.create') }}" class="btn btn-primary">
                                                <i class="fas fa-plus me-1"></i> Create Fee Structure
                                            </a>
                                        </div>
                                    </td>
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

<!-- Modal for viewing structure details -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Fee Structure Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Modal for copying structure -->
<div class="modal fade" id="copyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Copy Fee Structure</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.fee-structures.copy') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="source_structure_id" class="form-label">Source Fee Structure <span class="text-danger">*</span></label>
                        <select class="form-select" name="source_structure_id" id="source_structure_id" required>
                            <option value="">Select Source Structure</option>
                            @foreach($feeStructures as $str)
                                <option value="{{ $str->id }}">{{ $str->class_name }} ({{ $str->academic_year }}) - Total: ₹{{ number_format($str->feeStructureItems->sum('amount'), 2) }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="target_class_name" class="form-label">Target Class <span class="text-danger">*</span></label>
                        <select class="form-select" name="target_class_name" id="target_class_name" required>
                            <option value="">Select Class</option>
                            @foreach($classes as $c)
                                <option value="{{ $c->name }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="target_academic_year" class="form-label">Target Academic Year <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="target_academic_year" id="target_academic_year" placeholder="2026-27" required 
                               value="{{ date('Y') . '-' . substr(date('Y')+1, -2) }}">
                    </div>

                    <div class="mb-3">
                        <label for="percentage_increase" class="form-label">Percentage Amount Increase (Optional)</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="percentage_increase" id="percentage_increase" step="0.01" min="-100" max="1000" placeholder="e.g. 10">
                            <span class="input-group-text">%</span>
                        </div>
                        <small class="text-muted">Enter positive number for increase, negative for decrease, or 0/blank for identical copy.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-copy me-1"></i> Copy & Generate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Handle toggle buttons
    $(document).on('click', '.toggle-btn', function() {
        var id = $(this).data('id');
        var action = $(this).data('action');
        
        if (confirm(`Are you sure you want to ${action} this fee structure?`)) {
            $.ajax({
                url: `/admin/fee-structures/${id}/${action}`,
                type: 'PUT',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    location.reload();
                },
                error: function(xhr) {
                    alert('Error occurred: ' + xhr.responseJSON.message);
                }
            });
        }
    });
    
    // Handle view buttons
    $(document).on('click', '.view-btn', function() {
        var id = $(this).data('id');
        
        // Get structure details
        $.ajax({
            url: `/admin/fee-structures/${id}`,
            type: 'GET',
            success: function(data) {
                $('#modalBody').html(`
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Class:</strong> ${data.class_name}</p>
                            <p><strong>Academic Year:</strong> ${data.academic_year}</p>
                            <p><strong>Frequency:</strong> ${data.frequency.toUpperCase()}</p>
                            <p><strong>Status:</strong> <span class="badge bg-${data.status == 'active' ? 'success' : 'secondary'}">${data.status.toUpperCase()}</span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Total Fee:</strong> ₹${data.total_amount.toFixed(2)}</p>
                            <p><strong>Created At:</strong> ${new Date(data.created_at).toLocaleDateString()}</p>
                            <p><strong>Updated At:</strong> ${new Date(data.updated_at).toLocaleDateString()}</p>
                        </div>
                    </div>
                    <hr>
                    <h6>Fee Components:</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Frequency</th>
                                    <th>Billing Months</th>
                                    <th>Amount</th>
                                    <th>Due Day</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.fee_items.map(item => {
                                    const freqLabel = (item.billing_frequency || 'monthly').toUpperCase();
                                    const months = Array.isArray(item.charge_months) 
                                        ? item.charge_months 
                                        : (typeof item.charge_months === 'string' ? JSON.parse(item.charge_months) : []);
                                    const monthsLabel = months.join(', ') || 'N/A';
                                    return `
                                        <tr>
                                            <td><strong>${item.fee_type.name}</strong></td>
                                            <td><span class="badge bg-info">${freqLabel}</span></td>
                                            <td style="white-space: normal; max-width: 280px; font-size: 0.85rem;">${monthsLabel}</td>
                                            <td>₹${item.amount.toFixed(2)}</td>
                                            <td>${item.due_day || 'N/A'}</td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                `);
                $('#viewModal').modal('show');
            },
            error: function(xhr) {
                alert('Error loading structure details: ' + xhr.responseJSON.message);
            }
        });
    });
});
</script>
@endsection