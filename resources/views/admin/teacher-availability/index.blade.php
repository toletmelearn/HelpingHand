@extends('layouts.admin')

@section('title', 'Teacher Availability - Admin Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0"><i class="fas fa-calendar-check"></i> Teacher Availability</h2>
            <p class="text-muted mb-0">Select a teacher to view or edit which periods they're unavailable to teach.</p>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($teachers as $teacher)
                            <tr>
                                <td>{{ $teacher->name }}</td>
                                <td class="text-end">
                                    <a href="{{ route('teacher-availability.edit', $teacher) }}" class="btn btn-sm btn-primary">
                                        <i class="fas fa-clock"></i> View / Edit Availability
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-center text-muted py-4">No teachers found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
