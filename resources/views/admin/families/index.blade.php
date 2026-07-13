@extends('layouts.admin')

@section('title', 'Families')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Families</h4>
                <div class="page-title-right d-flex align-items-center gap-3">
                    <a href="{{ route('admin.family-link-suggestions.index') }}" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-user-friends me-1"></i> Pending Link Suggestions
                    </a>
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Families</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Guardian</th>
                                    <th>Mobile</th>
                                    <th>Children</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($families as $family)
                                    <tr>
                                        <td>{{ $family->guardian_name }}</td>
                                        <td>{{ $family->mobile }}</td>
                                        <td>{{ $family->students_count }}</td>
                                        <td class="text-center">
                                            <a href="{{ route('admin.families.show', $family->id) }}" class="btn btn-sm btn-outline-primary">View</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center py-4 text-muted">No families yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3">{{ $families->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
