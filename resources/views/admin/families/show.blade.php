@extends('layouts.admin')

@section('title', 'Family — ' . $family->guardian_name)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">{{ $family->guardian_name }}</h4>
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.families.index') }}">Families</a></li>
                    <li class="breadcrumb-item active">{{ $family->guardian_name }}</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <p class="mb-1"><strong>Mobile:</strong> {{ $family->mobile }}</p>
                    <p class="mb-0"><strong>Aadhaar:</strong> {{ $family->aadhaar_number ?? '—' }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="card-title mb-0 text-primary fw-bold">Children ({{ $family->students->count() }})</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>Name</th><th>Admission No</th><th>Class</th><th>Date of Birth</th></tr>
                            </thead>
                            <tbody>
                                @forelse($family->students as $student)
                                    <tr>
                                        <td>{{ $student->name }}</td>
                                        <td>{{ $student->admission_no }}</td>
                                        <td>{{ $student->schoolClass->name ?? '—' }}</td>
                                        <td>{{ optional($student->date_of_birth)->format('Y-m-d') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center py-4 text-muted">No children linked.</td></tr>
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
