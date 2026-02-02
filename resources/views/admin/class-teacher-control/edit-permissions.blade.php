@extends('layouts.admin')

@section('title', 'Edit Permissions - Class Teacher Control')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-lock"></i> Edit Field Permissions
                    </h4>
                </div>
                <div class="card-body">
                    <!-- Permission Form -->
                    <form action="{{ route('admin.class-teacher-control.save-permissions') }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group mb-3">
                                    <label for="model_type" class="form-label">Model Type</label>
                                    <select name="model_type" id="model_type" class="form-control" required>
                                        <option value="">Select Model</option>
                                        @foreach($models as $model)
                                            <option value="{{ $model }}">{{ $model }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group mb-3">
                                    <label for="field_name" class="form-label">Field Name</label>
                                    <input type="text" name="field_name" id="field_name" class="form-control" placeholder="e.g., name, email, address" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group mb-3">
                                    <label for="role" class="form-label">Role</label>
                                    <select name="role" id="role" class="form-control" required>
                                        <option value="">Select Role</option>
                                        @foreach($roles as $role)
                                            <option value="{{ $role }}">{{ ucfirst(str_replace('-', ' ', $role)) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group mb-3">
                                    <label for="permission_level" class="form-label">Permission Level</label>
                                    <select name="permission_level" id="permission_level" class="form-control" required>
                                        <option value="">Select Permission</option>
                                        @foreach($permissionLevels as $level)
                                            <option value="{{ $level }}">{{ ucfirst(str_replace('_', ' ', $level)) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Permission
                            </button>
                        </div>
                    </form>

                    <!-- Current Permissions Table -->
                    <div class="card mt-4">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-list"></i> Current Field Permissions
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Model</th>
                                            <th>Field</th>
                                            <th>Role</th>
                                            <th>Permission</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($currentPermissions as $permission)
                                        <tr>
                                            <td>{{ $permission->model_type }}</td>
                                            <td>{{ $permission->field_name }}</td>
                                            <td>{{ ucfirst(str_replace('-', ' ', $permission->role)) }}</td>
                                            <td>
                                                <span class="badge 
                                                    @if($permission->permission_level === 'editable') bg-success
                                                    @elseif($permission->permission_level === 'readonly') bg-warning
                                                    @elseif($permission->permission_level === 'hidden') bg-danger
                                                    @endif
                                                ">
                                                    {{ ucfirst(str_replace('_', ' ', $permission->permission_level)) }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge 
                                                    @if($permission->is_active) bg-success
                                                    @else bg-secondary
                                                    @endif
                                                ">
                                                    {{ $permission->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                            <td>
                                                <button 
                                                    class="btn btn-sm btn-outline-primary toggle-permission" 
                                                    data-id="{{ $permission->id }}"
                                                    data-active="{{ $permission->is_active ? 'true' : 'false' }}"
                                                >
                                                    <i class="fas fa-power-off"></i>
                                                    {{ $permission->is_active ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="6" class="text-center">No field permissions configured yet.</td>
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
    </div>

    <!-- JavaScript for Toggle Functionality -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleButtons = document.querySelectorAll('.toggle-permission');
            
            toggleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const permissionId = this.getAttribute('data-id');
                    const isActive = this.getAttribute('data-active') === 'true';
                    const button = this;
                    
                    fetch(`/admin/class-teacher-control/toggle-permission/${permissionId}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({})
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update the button text and data attribute
                            button.setAttribute('data-active', data.is_active.toString());
                            
                            if (data.is_active) {
                                button.innerHTML = '<i class="fas fa-power-off"></i> Deactivate';
                                // Update the status badge to active
                                button.closest('tr').querySelector('.badge').className = 'badge bg-success';
                                button.closest('tr').cells[4].innerHTML = '<span class="badge bg-success">Active</span>';
                            } else {
                                button.innerHTML = '<i class="fas fa-power-off"></i> Activate';
                                // Update the status badge to inactive
                                button.closest('tr').querySelector('.badge').className = 'badge bg-secondary';
                                button.closest('tr').cells[4].innerHTML = '<span class="badge bg-secondary">Inactive</span>';
                            }
                        } else {
                            alert('Error updating permission status');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error updating permission status');
                    });
                });
            });
        });
    </script>
@endsection
