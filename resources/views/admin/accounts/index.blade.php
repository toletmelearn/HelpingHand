@extends('layouts.admin')

@section('content')
<div class="container-fluid py-4">
    <!-- Header Block -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1 text-gray-800">
                <i class="bi bi-shield-lock-fill text-primary"></i> Account & Password Management
            </h1>
            <p class="text-muted mb-0">Monitor statuses, change passwords, and activate/deactivate accounts for all portal roles.</p>
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-start border-success border-4 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div>{{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-start border-danger border-4 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div>{{ session('error') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('generated_credentials') && count(session('generated_credentials')) > 0)
        <div class="alert alert-warning shadow-sm border-start border-warning border-4 mb-4" role="alert">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-key-fill me-2 fs-5"></i>
                <strong>One-time login credentials — shown once, communicate these securely and then close this alert.</strong>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered bg-white mb-0">
                    <thead class="table-light">
                        <tr><th>Type</th><th>Name</th><th>Username</th><th>Temporary Password</th></tr>
                    </thead>
                    <tbody>
                        @foreach(session('generated_credentials') as $cred)
                            <tr>
                                <td>{{ $cred['type'] }}</td>
                                <td>{{ $cred['name'] }}</td>
                                <td>{{ $cred['username'] }}</td>
                                <td><code>{{ $cred['password'] }}</code></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-start border-danger border-4 mb-4" role="alert">
            <div class="d-flex">
                <i class="bi bi-x-circle-fill me-2 fs-5 mt-1"></i>
                <div>
                    <strong class="d-block mb-1">Please fix the following validation errors:</strong>
                    <ul class="mb-0 ps-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Sync Warning Alert -->
    @if(($missingTeachersCount ?? 0) > 0 || ($missingParentsCount ?? 0) > 0)
        <div class="alert alert-warning shadow-sm border-start border-warning border-4 mb-4" role="alert">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill text-warning me-3 fs-3"></i>
                    <div>
                        <h6 class="alert-heading mb-1 fw-bold">Missing Portal Logins Detected</h6>
                        <p class="mb-0">
                            There are 
                            @if(($missingTeachersCount ?? 0) > 0) 
                                <strong>{{ $missingTeachersCount }}</strong> teacher logins 
                            @endif
                            @if(($missingTeachersCount ?? 0) > 0 && ($missingParentsCount ?? 0) > 0)
                                and
                            @endif
                            @if(($missingParentsCount ?? 0) > 0) 
                                <strong>{{ $missingParentsCount }}</strong> parent logins 
                            @endif
                            missing. These accounts cannot log in to their portals until logins are created.
                        </p>
                    </div>
                </div>
                <div>
                    <form method="POST" action="{{ route('admin.accounts.sync') }}">
                        @csrf
                        <button type="submit" class="btn btn-warning fw-bold text-dark px-4 shadow-sm">
                            <i class="bi bi-lightning-charge-fill"></i> Auto-Generate Logins
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Main Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-0 py-3">
            <!-- Nav Tabs -->
            <ul class="nav nav-pills card-header-pills" id="roleTabs">
                <li class="nav-item">
                    <a class="nav-link fw-bold px-4 py-2 {{ $tab === 'users' ? 'active' : '' }}" 
                       href="{{ route('admin.accounts.index', ['tab' => 'users', 'search' => $search, 'status' => $status]) }}">
                        <i class="bi bi-person-workspace me-1"></i> Admins & Staff
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-bold px-4 py-2 {{ $tab === 'teachers' ? 'active' : '' }}" 
                       href="{{ route('admin.accounts.index', ['tab' => 'teachers', 'search' => $search, 'status' => $status]) }}">
                        <i class="bi bi-person-badge me-1"></i> Teachers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-bold px-4 py-2 {{ $tab === 'parents' ? 'active' : '' }}" 
                       href="{{ route('admin.accounts.index', ['tab' => 'parents', 'search' => $search, 'status' => $status]) }}">
                        <i class="bi bi-people me-1"></i> Parents
                    </a>
                </li>
            </ul>
        </div>

        <div class="card-body">
            <!-- Filter & Search Section -->
            <form method="GET" action="{{ route('admin.accounts.index') }}" class="row g-3 mb-4">
                <input type="hidden" name="tab" value="{{ $tab }}">
                
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" 
                               name="search" 
                               class="form-control border-start-0 bg-light" 
                               placeholder="Search by name, email, phone, admission..." 
                               value="{{ $search }}">
                    </div>
                </div>

                <div class="col-md-3">
                    <select name="status" class="form-select bg-light">
                        <option value="">All Statuses</option>
                        <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>

                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-funnel-fill"></i> Filter
                    </button>
                    @if($search || $status)
                        <a href="{{ route('admin.accounts.index', ['tab' => $tab]) }}" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Clear
                        </a>
                    @endif
                </div>
            </form>

            <!-- Accounts Table -->
            <div class="table-responsive">
                @if($tab === 'users')
                    <!-- ADMIN & STAFF TABLE -->
                    <table class="table table-hover align-middle border-light">
                        <thead class="table-light text-secondary">
                            <tr>
                                <th>Name</th>
                                <th>Email (Username)</th>
                                <th>System Role</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $user)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar bg-primary-subtle text-primary rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                                                <i class="bi bi-person-fill fs-5"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-semibold">{{ $user->name }}</h6>
                                                <small class="text-muted">ID: #{{ $user->id }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $user->email }}</td>
                                    <td>
                                        <span class="badge bg-secondary text-white text-uppercase px-2 py-1" style="font-size: 0.75rem;">
                                            {{ $user->role ?? 'Staff' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($user->status === 'active')
                                            <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill fw-bold">Active</span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger px-3 py-2 rounded-pill fw-bold">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#changePasswordModal" 
                                                    data-role="user" 
                                                    data-id="{{ $user->id }}" 
                                                    data-name="{{ $user->name }}">
                                                <i class="bi bi-key-fill"></i> Reset Password
                                            </button>
                                            
                                            <form method="POST" action="{{ route('admin.accounts.toggle-status') }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="role_type" value="user">
                                                <input type="hidden" name="account_id" value="{{ $user->id }}">
                                                <button type="submit" class="btn btn-sm {{ $user->status === 'active' ? 'btn-outline-danger' : 'btn-outline-success' }}" 
                                                        {{ $user->id === auth()->id() ? 'disabled' : '' }}
                                                        title="{{ $user->id === auth()->id() ? 'You cannot deactivate yourself' : '' }}">
                                                    @if($user->status === 'active')
                                                        <i class="bi bi-dash-circle"></i> Deactivate
                                                    @else
                                                        <i class="bi bi-check-circle"></i> Activate
                                                    @endif
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-people fs-1 d-block mb-3"></i>
                                        No Admin or Staff accounts found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="mt-4">
                        {{ $users->links() }}
                    </div>

                @elseif($tab === 'teachers')
                    <!-- TEACHERS TABLE -->
                    <table class="table table-hover align-middle border-light">
                        <thead class="table-light text-secondary">
                            <tr>
                                <th>Teacher Name</th>
                                <th>Username (Mobile)</th>
                                <th>Force Reset Status</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($teachers as $teacherLogin)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar bg-success-subtle text-success rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                                                <i class="bi bi-person-badge-fill fs-5"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-semibold">{{ $teacherLogin->teacher->name ?? 'Unknown Teacher' }}</h6>
                                                <small class="text-muted">ID: #{{ $teacherLogin->teacher_id }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $teacherLogin->username }}</td>
                                    <td>
                                        @if($teacherLogin->force_password_change)
                                            <span class="badge bg-warning text-dark">Required on Login</span>
                                        @else
                                            <span class="badge bg-light text-secondary border">No</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($teacherLogin->status === 'active')
                                            <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill fw-bold">Active</span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger px-3 py-2 rounded-pill fw-bold">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#changePasswordModal" 
                                                    data-role="teacher" 
                                                    data-id="{{ $teacherLogin->id }}" 
                                                    data-name="{{ $teacherLogin->teacher->name ?? $teacherLogin->username }}">
                                                <i class="bi bi-key-fill"></i> Reset Password
                                            </button>
                                            
                                            <form method="POST" action="{{ route('admin.accounts.toggle-status') }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="role_type" value="teacher">
                                                <input type="hidden" name="account_id" value="{{ $teacherLogin->id }}">
                                                <button type="submit" class="btn btn-sm {{ $teacherLogin->status === 'active' ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                                    @if($teacherLogin->status === 'active')
                                                        <i class="bi bi-dash-circle"></i> Deactivate
                                                    @else
                                                        <i class="bi bi-check-circle"></i> Activate
                                                    @endif
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-person-badge fs-1 d-block mb-3"></i>
                                        No Teacher accounts found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="mt-4">
                        {{ $teachers->links() }}
                    </div>

                @elseif($tab === 'parents')
                    <!-- PARENTS TABLE -->
                    <table class="table table-hover align-middle border-light">
                        <thead class="table-light text-secondary">
                            <tr>
                                <th>Parent Name</th>
                                <th>Username / Mobile</th>
                                <th>Linked Student</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($parents as $parent)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar bg-info-subtle text-info rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                                                <i class="bi bi-people-fill fs-5"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-semibold">{{ $parent->name }}</h6>
                                                <small class="text-muted">ID: #{{ $parent->id }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div><small class="fw-bold">Mobile:</small> {{ $parent->mobile ?: 'N/A' }}</div>
                                        <div><small class="fw-bold">Adm No:</small> {{ $parent->admission_number ?: 'N/A' }}</div>
                                    </td>
                                    <td>
                                        @if($parent->student)
                                            <a href="{{ route('admin.students.show', $parent->student_id) }}" class="text-decoration-none fw-semibold">
                                                {{ $parent->student->name }}
                                            </a>
                                            <span class="text-muted d-block" style="font-size: 0.8rem;">Class: {{ $parent->student->schoolClass->name ?? $parent->student->class }}</span>
                                        @else
                                            <span class="text-muted">No student linked</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($parent->status === 'active')
                                            <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill fw-bold">Active</span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger px-3 py-2 rounded-pill fw-bold">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#changePasswordModal" 
                                                    data-role="parent" 
                                                    data-id="{{ $parent->id }}" 
                                                    data-name="{{ $parent->name }}">
                                                <i class="bi bi-key-fill"></i> Reset Password
                                            </button>
                                            
                                            <form method="POST" action="{{ route('admin.accounts.toggle-status') }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="role_type" value="parent">
                                                <input type="hidden" name="account_id" value="{{ $parent->id }}">
                                                <button type="submit" class="btn btn-sm {{ $parent->status === 'active' ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                                    @if($parent->status === 'active')
                                                        <i class="bi bi-dash-circle"></i> Deactivate
                                                    @else
                                                        <i class="bi bi-check-circle"></i> Activate
                                                    @endif
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-people fs-1 d-block mb-3"></i>
                                        No Parent accounts found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="mt-4">
                        {{ $parents->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- CHANGE PASSWORD MODAL -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="changePasswordModalLabel">
                    <i class="bi bi-shield-lock-fill me-1"></i> Reset Password
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('admin.accounts.change-password') }}">
                @csrf
                <div class="modal-body py-4">
                    <input type="hidden" name="role_type" id="modal-role-type">
                    <input type="hidden" name="account_id" id="modal-account-id">
                    
                    <p class="text-muted mb-4">
                        You are setting a new password for user: <strong class="text-dark" id="modal-username"></strong>.
                    </p>

                    <!-- New Password Input -->
                    <div class="mb-3">
                        <label for="password" class="form-label fw-bold">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-lock-fill text-muted"></i></span>
                            <input type="password" 
                                   name="password" 
                                   id="password" 
                                   class="form-control" 
                                   placeholder="Min 6 characters" 
                                   required>
                        </div>
                    </div>

                    <!-- Confirm Password Input -->
                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label fw-bold">Confirm New Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-lock-fill text-muted"></i></span>
                            <input type="password" 
                                   name="password_confirmation" 
                                   id="password_confirmation" 
                                   class="form-control" 
                                   placeholder="Re-enter password" 
                                   required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check-circle-fill me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Script -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const passwordModal = document.getElementById('changePasswordModal');
    if (passwordModal) {
        passwordModal.addEventListener('show.bs.modal', function (event) {
            // Button that triggered the modal
            const button = event.relatedTarget;
            
            // Extract info from data-* attributes
            const role = button.getAttribute('data-role');
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            
            // Update the modal's content.
            const modalUsername = passwordModal.querySelector('#modal-username');
            const modalRoleType = passwordModal.querySelector('#modal-role-type');
            const modalAccountId = passwordModal.querySelector('#modal-account-id');
            const passwordInput = passwordModal.querySelector('#password');
            const passwordConfirmInput = passwordModal.querySelector('#password_confirmation');
            
            // Fill data
            modalUsername.textContent = name;
            modalRoleType.value = role;
            modalAccountId.value = id;
            
            // Reset input fields
            passwordInput.value = '';
            passwordConfirmInput.value = '';
        });
    }
});
</script>
@endsection
