@extends('layouts.admin')

@section('title', 'Manage Permissions for ' . ($role->display_name ?? $role->name))

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h4 class="mb-0">Manage Permissions for {{ $role->display_name ?? $role->name }}</h4>
                    <input type="text" id="permissionSearch" class="form-control form-control-sm" style="max-width: 260px;" placeholder="Search duties...">
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <form action="{{ route('admin.role-permissions.update', $role->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="d-flex justify-content-end gap-2 mb-3">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllBtn">Select All</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAllBtn">Clear All</button>
                        </div>

                        <div class="accordion" id="permissionModules">
                            @foreach($groupedPermissions as $moduleKey => $group)
                                @php
                                    $grantedInModule = $group['permissions']->filter(fn($p) => $role->permissions->contains($p->id))->count();
                                    $totalInModule = $group['permissions']->count();
                                @endphp
                                <div class="accordion-item permission-module" data-module-label="{{ strtolower($group['label']) }}">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#module-{{ $loop->index }}">
                                            {{ $group['label'] }}
                                            <span class="badge bg-{{ $grantedInModule > 0 ? 'primary' : 'secondary' }} ms-2 module-badge">{{ $grantedInModule }}/{{ $totalInModule }}</span>
                                        </button>
                                    </h2>
                                    <div id="module-{{ $loop->index }}" class="accordion-collapse collapse" data-bs-parent="#permissionModules">
                                        <div class="accordion-body">
                                            <div class="form-check mb-2 border-bottom pb-2">
                                                <input class="form-check-input module-toggle-all" type="checkbox" id="module_all_{{ $loop->index }}"
                                                       {{ $grantedInModule === $totalInModule ? 'checked' : '' }}>
                                                <label class="form-check-label fw-semibold" for="module_all_{{ $loop->index }}">Grant all in this module</label>
                                            </div>
                                            <div class="row">
                                                @foreach($group['permissions'] as $permission)
                                                    <div class="col-md-6 mb-2">
                                                        <div class="form-check">
                                                            <input class="form-check-input permission-checkbox"
                                                                   type="checkbox"
                                                                   name="permissions[]"
                                                                   value="{{ $permission->id }}"
                                                                   id="permission_{{ $permission->id }}"
                                                                   {{ $role->permissions->contains($permission->id) ? 'checked' : '' }}>
                                                            <label class="form-check-label" for="permission_{{ $permission->id }}">
                                                                {{ $permission->label ?? $permission->name }}
                                                            </label>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <a href="{{ route('admin.role-permissions.index') }}" class="btn btn-secondary me-md-2">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Permissions</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.module-toggle-all').forEach(function (toggle) {
        toggle.addEventListener('change', function () {
            const body = this.closest('.accordion-body');
            body.querySelectorAll('.permission-checkbox').forEach(cb => { cb.checked = toggle.checked; });
            updateBadge(this.closest('.permission-module'));
        });
    });

    document.querySelectorAll('.permission-checkbox').forEach(function (cb) {
        cb.addEventListener('change', function () {
            updateBadge(this.closest('.permission-module'));
        });
    });

    function updateBadge(moduleEl) {
        const checkboxes = moduleEl.querySelectorAll('.permission-checkbox');
        const checked = moduleEl.querySelectorAll('.permission-checkbox:checked').length;
        const badge = moduleEl.querySelector('.module-badge');
        const toggleAll = moduleEl.querySelector('.module-toggle-all');
        badge.textContent = checked + '/' + checkboxes.length;
        badge.classList.toggle('bg-primary', checked > 0);
        badge.classList.toggle('bg-secondary', checked === 0);
        toggleAll.checked = checked === checkboxes.length;
    }

    document.getElementById('selectAllBtn').addEventListener('click', function () {
        document.querySelectorAll('.permission-checkbox').forEach(cb => { cb.checked = true; });
        document.querySelectorAll('.permission-module').forEach(updateBadge);
    });

    document.getElementById('clearAllBtn').addEventListener('click', function () {
        document.querySelectorAll('.permission-checkbox').forEach(cb => { cb.checked = false; });
        document.querySelectorAll('.permission-module').forEach(updateBadge);
    });

    document.getElementById('permissionSearch').addEventListener('input', function () {
        const term = this.value.toLowerCase().trim();
        document.querySelectorAll('.permission-module').forEach(function (moduleEl) {
            if (term === '') {
                moduleEl.style.display = '';
                return;
            }
            const labelMatch = moduleEl.dataset.moduleLabel.includes(term);
            const permissionMatch = Array.from(moduleEl.querySelectorAll('.form-check-label')).some(l => l.textContent.toLowerCase().includes(term));
            moduleEl.style.display = (labelMatch || permissionMatch) ? '' : 'none';
        });
    });
});
</script>
@endsection
