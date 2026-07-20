@extends('layouts.admin')

@section('title', 'Student Import Preview')

@section('content')
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Student Import Preview</h1>
            <a href="{{ route('students.index') }}" class="btn btn-secondary">Back to Students</a>
        </div>

        <div class="alert alert-warning">
            <strong>Preview only</strong> - no students have been imported.
        </div>

        <div class="alert alert-info">
            Apply is available only for clean previews. Duplicate warnings must be resolved before import.
        </div>

        @if(isset($applyPreview) && $summary['rows_with_errors'] === 0 && $summary['rows_with_warnings'] === 0)
            <form method="POST" action="{{ route('students.import.csv.apply') }}" class="mb-4">
                @csrf
                <input type="hidden" name="preview_id" value="{{ $applyPreview['preview_id'] }}">
                <input type="hidden" name="hash" value="{{ $applyPreview['hash'] }}">
                <button type="submit" class="btn btn-success">Apply Import</button>
            </form>
        @endif

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h6>Total Rows</h6>
                        <div class="h3">{{ $summary['total_rows'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h6>Valid Rows</h6>
                        <div class="h3 text-success">{{ $summary['valid_rows'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h6>Rows With Errors</h6>
                        <div class="h3 text-danger">{{ $summary['rows_with_errors'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h6>Rows With Warnings</h6>
                        <div class="h3 text-warning">{{ $summary['rows_with_warnings'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Row</th>
                        <th>Original Class</th>
                        <th>Original Section</th>
                        <th>Class ID</th>
                        <th>School Class ID</th>
                        <th>Class</th>
                        <th>Section ID</th>
                        <th>Section</th>
                        <th>Relationship Details</th>
                        <th>Errors</th>
                        <th>Warnings</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($previewRows as $row)
                        <tr class="{{ $row['is_valid'] ? '' : 'table-danger' }}">
                            <td>{{ $row['row_number'] }}</td>
                            <td>{{ $row['original']['class'] ?? $row['original']['Class'] ?? $row['original'][9] ?? 'N/A' }}</td>
                            <td>{{ $row['original']['section'] ?? $row['original']['Section'] ?? $row['original'][10] ?? 'N/A' }}</td>
                            <td>{{ $row['normalized']['class_id'] ?? 'N/A' }}</td>
                            <td>{{ $row['normalized']['school_class_id'] ?? 'N/A' }}</td>
                            <td>{{ $row['normalized']['class'] ?? 'N/A' }}</td>
                            <td>{{ $row['normalized']['section_id'] ?? 'N/A' }}</td>
                            <td>{{ $row['normalized']['section'] ?? 'N/A' }}</td>
                            <td>
                                @if(isset($row['sibling_group']) && $row['sibling_group'])
                                    <span class="badge bg-info d-block mb-1"><i class="bi bi-people-fill me-1"></i> Sibling Link</span>
                                @endif
                                @if(isset($row['parent_link_status']) && $row['parent_link_status'] === 'link')
                                    <span class="badge bg-success d-block"><i class="bi bi-link-45deg me-1"></i> Existing Parent</span>
                                @else
                                    <span class="badge bg-secondary d-block"><i class="bi bi-plus-circle me-1"></i> New Parent Account</span>
                                @endif
                            </td>
                            <td>
                                @forelse($row['errors'] as $error)
                                    <div class="text-danger">{{ $error }}</div>
                                @empty
                                    <span class="text-muted">None</span>
                                @endforelse
                            </td>
                            <td>
                                @forelse($row['warnings'] as $warning)
                                    <div class="text-warning">{{ $warning }}</div>
                                @empty
                                    <span class="text-muted">None</span>
                                @endforelse
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center text-muted">No data rows found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
