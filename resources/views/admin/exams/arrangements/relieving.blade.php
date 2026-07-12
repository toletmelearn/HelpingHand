@extends('layouts.admin')

@section('title', 'Exam Relieving Duties')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">🔄 Relieving Duties (Standby Coverage)</h1>
            <p class="text-muted mb-0">Assign relieving/relief shifts to standby teachers to cover breaks for exam invigilators.</p>
        </div>
        <a href="{{ route('admin.exams.arrangements.index') }}" class="btn btn-secondary shadow-sm">
            <i class="bi bi-arrow-left"></i> Back to Arrangements
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <!-- Relieving Assignment Card -->
        <div class="col-12 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 bg-light d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-arrow-repeat"></i> Standby Relieving Duties List
                    </h6>
                    <button type="button" class="btn btn-sm btn-primary" onclick="addRelieverRow()">
                        <i class="bi bi-plus-circle"></i> Add Reliever Slot
                    </button>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.exams.arrangements.relieving.save', $exam->id) }}" method="POST">
                        @csrf
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered align-middle" id="relievingTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Reliever Teacher (Standby)</th>
                                        <th>Relief Time Slot</th>
                                        <th>Target Coverage Area / Rooms</th>
                                        <th class="text-center" style="width: 100px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($duties as $index => $duty)
                                    <tr>
                                        <td>
                                            <select name="duties[{{ $index }}][teacher_id]" class="form-select" required>
                                                <option value="">-- Select Teacher --</option>
                                                @foreach($teachers as $teacher)
                                                    <option value="{{ $teacher->id }}" {{ $duty->teacher_id == $teacher->id ? 'selected' : '' }}>
                                                        {{ $teacher->name }} ({{ $teacher->designation ?? 'Teacher' }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="duties[{{ $index }}][time_slot]" class="form-control" value="{{ $duty->time_slot }}" placeholder="e.g. 10:00 AM - 10:30 AM" required>
                                        </td>
                                        <td>
                                            <input type="text" name="duties[{{ $index }}][room_number]" class="form-control" value="{{ $duty->room_number }}" placeholder="e.g. Rooms 101-105, Wing A" required>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeRow(this)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    @empty
                                    <!-- Fallback empty row for initial input -->
                                    <tr id="emptyRowPlaceholder">
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            No relieving duties assigned. Click "Add Reliever Slot" to configure coverage.
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-success px-4 shadow">
                                <i class="bi bi-save"></i> Save Relieving Duties
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Template Row Script -->
<script>
let rowIndex = {{ count($duties) }};

function addRelieverRow() {
    const placeholder = document.getElementById('emptyRowPlaceholder');
    if (placeholder) {
        placeholder.remove();
    }

    const tableBody = document.querySelector('#relievingTable tbody');
    const tr = document.createElement('tr');
    
    tr.innerHTML = `
        <td>
            <select name="duties[\${rowIndex}][teacher_id]" class="form-select" required>
                <option value="">-- Select Teacher --</option>
                @foreach($teachers as $teacher)
                    <option value="{{ $teacher->id }}">{{ $teacher->name }} ({{ $teacher->designation ?? 'Teacher' }})</option>
                @endforeach
            </select>
        </td>
        <td>
            <input type="text" name="duties[\${rowIndex}][time_slot]" class="form-control" placeholder="e.g. 10:00 AM - 10:30 AM" required>
        </td>
        <td>
            <input type="text" name="duties[\${rowIndex}][room_number]" class="form-control" placeholder="e.g. Rooms 101-105, Wing A" required>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeRow(this)">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;
    
    tableBody.appendChild(tr);
    rowIndex++;
}

function removeRow(btn) {
    btn.closest('tr').remove();
    
    // Check if table is empty, if so restore placeholder
    const tableBody = document.querySelector('#relievingTable tbody');
    if (tableBody.children.length === 0) {
        const tr = document.createElement('tr');
        tr.id = 'emptyRowPlaceholder';
        tr.innerHTML = `
            <td colspan="4" class="text-center py-4 text-muted">
                No relieving duties assigned. Click "Add Reliever Slot" to configure coverage.
            </td>
        `;
        tableBody.appendChild(tr);
    }
}
</script>
@endsection
