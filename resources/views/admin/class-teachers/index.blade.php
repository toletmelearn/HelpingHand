@extends('layouts.admin')

@section('title', 'Class Teacher Assignment - Admin Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <div>
                    <h2 class="mb-0"><i class="fas fa-chalkboard-teacher"></i> Class Teacher Assignment</h2>
                    <p class="text-muted mb-0">Select a class to assign or change its class teacher, section by section.</p>
                </div>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item active">Class Teacher Assignment</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-list"></i> Classes ({{ $academicYear }})</h4>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Class</th>
                                    <th>Class Teacher Coverage</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($classes as $row)
                                    <tr>
                                        <td><span class="badge bg-primary">{{ $row['class']->name }}</span></td>
                                        <td>
                                            @if($row['total_slots'] === 0)
                                                <span class="badge bg-secondary">No sections configured for this class</span>
                                            @elseif($row['assigned_count'] === $row['total_slots'])
                                                <span class="badge bg-success">{{ $row['assigned_count'] }} / {{ $row['total_slots'] }} assigned</span>
                                            @elseif($row['assigned_count'] === 0)
                                                <span class="badge bg-danger">0 / {{ $row['total_slots'] }} assigned</span>
                                            @else
                                                <span class="badge bg-warning text-dark">{{ $row['assigned_count'] }} / {{ $row['total_slots'] }} assigned</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.class-teachers.show', $row['class']) }}" class="btn btn-sm btn-info">
                                                <i class="fas fa-user-edit"></i> Manage
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-5 text-muted">No classes found.</td>
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
@endsection
