@extends('layouts.admin')

@section('title', 'Advance Rebate Rules')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Advance Rebate Rules</h4>
                <div class="page-title-right d-flex align-items-center gap-3">
                    <a href="{{ route('admin.advance-rebate-rules.create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus me-1"></i> New Rule
                    </a>
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Advance Rebate Rules</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Value</th>
                            <th>Cutoff (MM-DD)</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rules as $rule)
                            <tr>
                                <td>{{ $rule->name }}</td>
                                <td class="text-capitalize">{{ $rule->type }}</td>
                                <td>{{ $rule->type === 'percent' ? $rule->value . '%' : '₹' . number_format($rule->value, 2) }}</td>
                                <td>{{ $rule->cutoff_month_day }}</td>
                                <td>
                                    <span class="badge bg-{{ $rule->is_active ? 'success' : 'secondary' }}">
                                        {{ $rule->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <a href="{{ route('admin.advance-rebate-rules.edit', $rule->id) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <form method="POST" action="{{ route('admin.advance-rebate-rules.destroy', $rule->id) }}" onsubmit="return confirm('Delete this rule?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-4 text-muted">No advance rebate rules yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
