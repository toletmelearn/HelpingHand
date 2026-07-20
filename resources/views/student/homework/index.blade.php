@extends('layouts.student')

@section('content')
<div class="container-fluid px-4 py-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1 class="h2 text-dark font-weight-bold">My Homework Board</h1>
            <p class="text-secondary">View assigned homework tasks, check submission status, and upload files.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0 rounded-lg">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-secondary font-weight-bold text-uppercase" style="font-size: 0.8rem;">
                        <tr>
                            <th class="ps-4 py-3">Subject</th>
                            <th class="py-3">Assignment Details</th>
                            <th class="py-3">Due Date</th>
                            <th class="py-3">Submission Status</th>
                            <th class="py-3">Evaluation / Grade</th>
                            <th class="py-3 text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($homeworks as $homework)
                            @php
                                $submission = $homework->submissions->first();
                            @endphp
                            <tr>
                                <td class="ps-4">
                                    <span class="text-dark font-weight-bold text-uppercase">{{ $homework->subject_name ?? 'General' }}</span>
                                </td>
                                <td>
                                    <h6 class="mb-1 text-dark font-weight-bold">{{ $homework->title }}</h6>
                                    <p class="text-secondary mb-0 text-truncate" style="max-width: 300px;">{{ $homework->description }}</p>
                                </td>
                                <td>
                                    <div class="text-dark font-weight-bold">{{ \Carbon\Carbon::parse($homework->due_date)->format('M d, Y') }}</div>
                                    <small class="text-secondary">Assigned on {{ \Carbon\Carbon::parse($homework->assign_date)->format('M d, Y') }}</small>
                                </td>
                                <td>
                                    @if($submission)
                                        @if($submission->status == 'evaluated')
                                            <span class="badge bg-success px-3 py-2 rounded-pill">Evaluated</span>
                                        @else
                                            <span class="badge bg-info text-white px-3 py-2 rounded-pill">Submitted</span>
                                        @endif
                                    @else
                                        @if(\Carbon\Carbon::parse($homework->due_date)->isPast())
                                            <span class="badge bg-danger px-3 py-2 rounded-pill">Overdue</span>
                                        @else
                                            <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">Pending</span>
                                        @endif
                                    @endif
                                </td>
                                <td>
                                    @if($submission && $submission->status == 'evaluated')
                                        <div class="text-dark font-weight-bold">Grade: <span class="text-primary">{{ $submission->grade ?? '-' }}</span></div>
                                        <small class="text-secondary" title="{{ $submission->remarks }}">Remarks: {{ Str::limit($submission->remarks, 20) }}</small>
                                    @else
                                        <span class="text-secondary">-</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    @if($submission && $submission->status == 'evaluated')
                                        <button class="btn btn-sm btn-outline-secondary px-3 rounded-pill" disabled>Completed</button>
                                    @else
                                        <a href="{{ route('student.homework.submit', $homework->id) }}" class="btn btn-sm btn-primary px-4 rounded-pill font-weight-bold">
                                            {{ $submission ? 'Edit Submit' : 'Submit Now' }}
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="text-secondary mb-3">
                                        <i class="bi bi-journal-check" style="font-size: 3rem;"></i>
                                    </div>
                                    <h5 class="text-dark font-weight-bold">No Homework Assigned</h5>
                                    <p class="text-secondary mb-0">You do not have any homework assignments for your class at this time.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
