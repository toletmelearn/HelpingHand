@extends('layouts.admin')

@section('title', 'Student Financial Accounts')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Student Financial Accounts</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Financial Accounts</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Filters Row -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.financial-accounts.index') }}" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search Student</label>
                            <input type="text" name="search" id="search" class="form-control" placeholder="Name or Admission Number..." value="{{ request('search') }}">
                        </div>
                        <div class="col-md-4">
                            <label for="class_id" class="form-label">Class</label>
                            <select name="class_id" id="class_id" class="form-select">
                                <option value="">All Classes</option>
                                @foreach($classList as $classItem)
                                    <option value="{{ $classItem->id }}" {{ request('class_id') == $classItem->id ? 'selected' : '' }}>
                                        {{ $classItem->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-1"></i> Filter
                            </button>
                            <a href="{{ route('admin.financial-accounts.index') }}" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-1"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Accounts Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle dt-responsive nowrap">
                            <thead class="table-light">
                                <tr>
                                    <th>Account No</th>
                                    <th>Admission No</th>
                                    <th>Student Name</th>
                                    <th>Class</th>
                                    <th>Outstanding Balance</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($students as $student)
                                    @php
                                        $outstanding = \App\Services\FinanceAccountService::getOutstandingBalance($student->id);
                                        $accountNo = $student->financialAccount->account_no ?? 'FIN-' . str_pad($student->id, 6, '0', STR_PAD_LEFT);
                                        $status = $student->financialAccount->status ?? 'active';
                                    @endphp
                                    <tr>
                                        <td>
                                            <span class="badge bg-soft-info text-info font-size-12">
                                                {{ $accountNo }}
                                            </span>
                                        </td>
                                        <td>{{ $student->admission_no ?: 'N/A' }}</td>
                                        <td><strong>{{ $student->name }}</strong></td>
                                        <td>{{ $student->schoolClass->name ?? $student->class }}</td>
                                        <td>
                                            <span class="{{ $outstanding > 0 ? 'text-danger fw-bold' : ($outstanding < 0 ? 'text-success fw-bold' : 'text-muted') }}">
                                                ₹{{ number_format($outstanding, 2) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $status === 'active' ? 'success' : ($status === 'locked' ? 'warning' : 'danger') }}">
                                                {{ ucfirst($status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.financial-accounts.show', $student->id) }}" class="btn btn-primary btn-sm">
                                                <i class="fas fa-file-invoice-dollar me-1"></i> Statement / Account
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">No student financial records found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        {{ $students->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
