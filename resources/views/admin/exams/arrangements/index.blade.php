@extends('layouts.admin')

@section('title', 'Exam Cell Arrangements')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">🏫 Exam Cell Arrangements</h1>
            <p class="text-muted mb-0">Manage seating plans, invigilator duties, and standby relievers from this central command panel.</p>
        </div>
        <a href="{{ route('admin.exams.index') }}" class="btn btn-secondary shadow-sm">
            <i class="bi bi-arrow-left"></i> View Exams List
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Exams Arrangements Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-light">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="bi bi-calendar-event"></i> Active Exams & Scheduled Arrangements
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Exam Name</th>
                            <th>Class & Subject</th>
                            <th>Exam Date & Time</th>
                            <th class="text-center">Seating Plans</th>
                            <th class="text-center">Invigilators</th>
                            <th class="text-center">Relieving Duty</th>
                            <th class="text-center">Management Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($exams as $exam)
                        <tr>
                            <td class="fw-bold">{{ $exam->name }}</td>
                            <td>
                                <span class="badge bg-primary me-1">{{ $exam->class_name }}</span>
                                <span class="badge bg-info text-white">{{ $exam->subject }}</span>
                            </td>
                            <td>
                                <div><i class="bi bi-calendar"></i> {{ date('d M Y', strtotime($exam->exam_date)) }}</div>
                                <small class="text-muted"><i class="bi bi-clock"></i> {{ $exam->start_time }} - {{ $exam->end_time }}</small>
                            </td>
                            <td class="text-center">
                                @if($summaries[$exam->id]['seating_count'] > 0)
                                    <span class="badge bg-success">
                                        <i class="bi bi-people"></i> {{ $summaries[$exam->id]['seating_count'] }} Assigned
                                    </span>
                                @else
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-exclamation-triangle"></i> Pending
                                    </span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($summaries[$exam->id]['invigilator_count'] > 0)
                                    <span class="badge bg-success">
                                        <i class="bi bi-person-badge"></i> {{ $summaries[$exam->id]['invigilator_count'] }} Assigned
                                    </span>
                                @else
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-exclamation-triangle"></i> Pending
                                    </span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($summaries[$exam->id]['relieving_count'] > 0)
                                    <span class="badge bg-success">
                                        <i class="bi bi-arrow-repeat"></i> {{ $summaries[$exam->id]['relieving_count'] }} Relievers
                                    </span>
                                @else
                                    <span class="badge bg-secondary">
                                        None
                                    </span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="{{ route('admin.exams.arrangements.seating', $exam->id) }}" class="btn btn-sm btn-outline-primary" title="Manage Seating">
                                        <i class="bi bi-grid-3x3-gap"></i> Seating
                                    </a>
                                    <a href="{{ route('admin.exams.arrangements.invigilators', $exam->id) }}" class="btn btn-sm btn-outline-success" title="Manage Invigilators">
                                        <i class="bi bi-shield-check"></i> Invigilators
                                    </a>
                                    <a href="{{ route('admin.exams.arrangements.relieving', $exam->id) }}" class="btn btn-sm btn-outline-warning text-dark" title="Manage Relieving">
                                        <i class="bi bi-arrow-repeat"></i> Relieving
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="bi bi-journal-x" style="font-size: 2rem;"></i>
                                <div class="mt-2">No exams scheduled in the system.</div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $exams->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
