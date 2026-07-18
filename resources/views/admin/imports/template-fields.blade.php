@extends('layouts.admin')

@section('title', 'Manage Template Fields')

@section('content')
<style>
    .glass-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 12px;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.08);
    }

    .glass-header {
        background: linear-gradient(135deg, #3498db, #2c3e50);
        color: white;
        border-top-left-radius: 12px;
        border-top-right-radius: 12px;
    }

    .field-row {
        transition: background-color 0.15s ease;
    }

    .field-row:hover {
        background-color: #f8f9fa;
    }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1">Manage Template Fields</h3>
            <p class="text-muted mb-0">Add or remove the columns in the downloadable template for this import -- no code change needed.</p>
        </div>
        <a href="{{ route('imports.wizard', ['module' => $module]) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Wizard
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card glass-card">
        <div class="glass-header p-3">
            <h5 class="mb-0"><i class="bi bi-list-columns-reverse me-2"></i>{{ ucwords(str_replace('_', ' ', $module)) }} -- Template Columns</h5>
        </div>
        <div class="card-body p-4">
            <p class="text-muted small">
                These are the column headers a user sees when they click "Download CSV Template" on the upload page.
                Adding or removing a field here only changes the downloadable file's columns -- it does not change what
                data the import actually reads (only the specific columns this feature needs, e.g. admission number and
                total paid, are ever used regardless of how many extra reference columns the template has).
            </p>

            <form action="{{ route('imports.wizard.template-fields.update', ['module' => $module]) }}" method="POST" id="templateFieldsForm">
                @csrf

                <div id="fieldsContainer">
                    @foreach($headers as $header)
                        <div class="input-group mb-2 field-row">
                            <span class="input-group-text bg-light text-muted" style="width: 48px; justify-content: center;">{{ $loop->iteration }}</span>
                            <input type="text" name="fields[]" class="form-control" value="{{ $header }}" required>
                            <button type="button" class="btn btn-outline-danger remove-field-btn" title="Remove field">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    @endforeach
                </div>

                <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="addFieldBtn">
                    <i class="bi bi-plus-lg me-1"></i> Add Field
                </button>

                <hr class="my-4">

                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted"><span id="fieldCount">{{ count($headers) }}</span> field(s)</small>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check-lg me-1"></i> Save Template Fields
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('fieldsContainer');
    const addBtn = document.getElementById('addFieldBtn');
    const fieldCount = document.getElementById('fieldCount');

    function renumber() {
        const rows = container.querySelectorAll('.field-row');
        rows.forEach(function (row, index) {
            row.querySelector('.input-group-text').innerText = index + 1;
        });
        fieldCount.innerText = rows.length;
    }

    container.addEventListener('click', function (e) {
        const removeBtn = e.target.closest('.remove-field-btn');
        if (!removeBtn) return;

        // Always keep at least one field row so the form can't be submitted empty.
        if (container.querySelectorAll('.field-row').length <= 1) {
            return;
        }
        removeBtn.closest('.field-row').remove();
        renumber();
    });

    addBtn.addEventListener('click', function () {
        const row = document.createElement('div');
        row.className = 'input-group mb-2 field-row';
        row.innerHTML = `
            <span class="input-group-text bg-light text-muted" style="width: 48px; justify-content: center;"></span>
            <input type="text" name="fields[]" class="form-control" placeholder="New field name" required>
            <button type="button" class="btn btn-outline-danger remove-field-btn" title="Remove field">
                <i class="bi bi-x-lg"></i>
            </button>
        `;
        container.appendChild(row);
        renumber();
        row.querySelector('input').focus();
    });
});
</script>
@endsection
